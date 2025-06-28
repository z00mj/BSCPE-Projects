import type { Express } from "express";
import { createServer, type Server } from "http";
import { storage } from "./storage";
import bcrypt from "bcryptjs";
import session from "express-session";
import multer from "multer";
import path from "path";
import fs from "fs";
import { insertUserSchema, loginSchema, adminLoginSchema, insertDepositSchema, insertWithdrawalSchema, insertGameResultSchema } from "@shared/schema";
import express from "express";

// Configure multer for file uploads
const uploadDir = path.join(process.cwd(), 'uploads');
if (!fs.existsSync(uploadDir)) {
  fs.mkdirSync(uploadDir, { recursive: true });
}

const upload = multer({ 
  dest: uploadDir,
  limits: { fileSize: 5 * 1024 * 1024 }, // 5MB limit
  fileFilter: (req, file, cb) => {
    if (file.mimetype.startsWith('image/')) {
      cb(null, true);
    } else {
      cb(new Error('Only image files are allowed'));
    }
  }
});

declare module "express-session" {
  interface SessionData {
    userId?: number;
    adminId?: number;
    username?: string;
  }
}

export async function registerRoutes(app: Express): Promise<Server> {
  // Session configuration
  app.use(session({
    secret: process.env.SESSION_SECRET || 'bigblackcoin-secret-key',
    resave: false,
    saveUninitialized: false,
    cookie: { 
      secure: false, // Set to true in production with HTTPS
      maxAge: 24 * 60 * 60 * 1000 // 24 hours
    }
  }));

  // Serve uploaded files
  app.use('/uploads', express.static(uploadDir));

  // Initialize default admin if not exists
  const initializeAdmin = async () => {
    const existingAdmin = await storage.getAdminByUsername('admin');
    if (!existingAdmin) {
      const hashedPassword = await bcrypt.hash('admin1234', 10);
      await storage.createAdmin('admin', hashedPassword);
      console.log('Default admin created: admin/admin1234');
    }
  };
  initializeAdmin();

  // Auth middleware
  const requireAuth = (req: any, res: any, next: any) => {
    if (!req.session.userId) {
      return res.status(401).json({ error: 'Authentication required' });
    }
    next();
  };

  const requireAdmin = (req: any, res: any, next: any) => {
    if (!req.session.adminId) {
      return res.status(401).json({ error: 'Admin authentication required' });
    }
    next();
  };

  // User Authentication Routes
  app.post('/api/auth/register', async (req, res) => {
    try {
      const { username, email, password } = insertUserSchema.parse(req.body);
      
      // Check if user already exists
      const existingUser = await storage.getUserByUsername(username);
      if (existingUser) {
        return res.status(400).json({ error: 'Username already exists' });
      }

      const existingEmail = await storage.getUserByEmail(email);
      if (existingEmail) {
        return res.status(400).json({ error: 'Email already exists' });
      }

      const hashedPassword = await bcrypt.hash(password, 10);
      const user = await storage.createUser({ username, email, password: hashedPassword });
      
      req.session.userId = user.id;
      req.session.username = user.username;
      
      res.json({ user: { id: user.id, username: user.username, email: user.email } });
    } catch (error) {
      res.status(400).json({ error: 'Registration failed' });
    }
  });

  app.post('/api/auth/login', async (req, res) => {
    try {
      const { username, password } = loginSchema.parse(req.body);
      
      const user = await storage.getUserByUsername(username);
      if (!user || !(await bcrypt.compare(password, user.password))) {
        return res.status(401).json({ error: 'Invalid credentials' });
      }

      if (user.status !== 'active') {
        return res.status(403).json({ error: 'Account is suspended or banned' });
      }

      req.session.userId = user.id;
      req.session.username = user.username;
      
      res.json({ user: { id: user.id, username: user.username, email: user.email, balance: user.balance, bbcTokens: user.bbcTokens } });
    } catch (error) {
      res.status(400).json({ error: 'Login failed' });
    }
  });

  app.post('/api/auth/logout', (req, res) => {
    req.session.destroy((err) => {
      if (err) {
        return res.status(500).json({ error: 'Logout failed' });
      }
      res.json({ message: 'Logged out successfully' });
    });
  });

  app.get('/api/auth/me', requireAuth, async (req, res) => {
    try {
      const user = await storage.getUser(req.session.userId!);
      if (!user) {
        return res.status(404).json({ error: 'User not found' });
      }
      res.json({ user: { id: user.id, username: user.username, email: user.email, balance: user.balance, bbcTokens: user.bbcTokens } });
    } catch (error) {
      res.status(500).json({ error: 'Failed to get user info' });
    }
  });

  // Admin Authentication Routes
  app.post('/api/admin/login', async (req, res) => {
    try {
      const { username, password } = adminLoginSchema.parse(req.body);
      
      const admin = await storage.getAdminByUsername(username);
      if (!admin || !(await bcrypt.compare(password, admin.password))) {
        return res.status(401).json({ error: 'Invalid admin credentials' });
      }

      req.session.adminId = admin.id;
      req.session.username = admin.username;
      
      res.json({ admin: { id: admin.id, username: admin.username } });
    } catch (error) {
      res.status(400).json({ error: 'Admin login failed' });
    }
  });

  app.post('/api/admin/logout', (req, res) => {
    req.session.destroy((err) => {
      if (err) {
        return res.status(500).json({ error: 'Logout failed' });
      }
      res.json({ message: 'Admin logged out successfully' });
    });
  });

  // Game Routes
  app.post('/api/games/play', requireAuth, async (req, res) => {
    try {
      const gameData = insertGameResultSchema.parse(req.body);
      const user = await storage.getUser(req.session.userId!);
      
      if (!user) {
        return res.status(404).json({ error: 'User not found' });
      }

      const betAmount = parseFloat(gameData.betAmount);
      const currentBalance = parseFloat(user.balance);
      
      if (betAmount > currentBalance) {
        return res.status(400).json({ error: 'Insufficient balance' });
      }

      // Calculate game result based on game type
      let result = calculateGameResult(gameData.gameType, betAmount, gameData.gameData);
      
      // Update user balance
      const newBalance = (currentBalance - betAmount + result.winAmount).toFixed(2);
      const newBbcTokens = (parseFloat(user.bbcTokens) + result.bbcWon).toFixed(6);
      
      await storage.updateUserBalance(req.session.userId!, newBalance, newBbcTokens);
      
      // Save game result
      await storage.createGameResult(req.session.userId!, {
        ...gameData,
        winAmount: result.winAmount.toFixed(2),
        bbcWon: result.bbcWon.toFixed(6)
      });

      res.json({ 
        result: result.gameResult,
        winAmount: result.winAmount,
        bbcWon: result.bbcWon,
        newBalance,
        newBbcTokens
      });
    } catch (error) {
      res.status(400).json({ error: 'Game play failed' });
    }
  });

  // Wallet Routes
  app.post('/api/wallet/deposit', requireAuth, upload.single('receipt'), async (req, res) => {
    try {
      const { amount, paymentMethod } = req.body;
      let receiptImage = '';
      
      if (req.file) {
        receiptImage = `/uploads/${req.file.filename}`;
      }

      const deposit = await storage.createDeposit(req.session.userId!, {
        amount,
        paymentMethod,
        receiptImage
      });

      res.json({ deposit });
    } catch (error) {
      res.status(400).json({ error: 'Deposit request failed' });
    }
  });

  app.post('/api/wallet/withdrawal', requireAuth, async (req, res) => {
    try {
      const withdrawalData = insertWithdrawalSchema.parse(req.body);
      const user = await storage.getUser(req.session.userId!);
      
      if (!user) {
        return res.status(404).json({ error: 'User not found' });
      }

      const withdrawAmount = parseFloat(withdrawalData.amount);
      let availableBalance = 0;

      if (withdrawalData.currency === 'coins') {
        availableBalance = parseFloat(user.balance);
      } else if (withdrawalData.currency === 'bbc') {
        availableBalance = parseFloat(user.bbcTokens);
      }

      if (withdrawAmount > availableBalance) {
        return res.status(400).json({ error: 'Insufficient balance' });
      }

      const withdrawal = await storage.createWithdrawal(req.session.userId!, withdrawalData);
      res.json({ withdrawal });
    } catch (error) {
      res.status(400).json({ error: 'Withdrawal request failed' });
    }
  });

  app.post('/api/wallet/convert', requireAuth, async (req, res) => {
    try {
      const { amount, fromCurrency, toCurrency } = req.body;
      const user = await storage.getUser(req.session.userId!);
      
      if (!user) {
        return res.status(404).json({ error: 'User not found' });
      }

      const convertAmount = parseFloat(amount);
      const conversionRate = 5000; // 1 BBC = 5000 coins

      let newBalance = user.balance;
      let newBbcTokens = user.bbcTokens;

      if (fromCurrency === 'bbc' && toCurrency === 'coins') {
        const currentBbc = parseFloat(user.bbcTokens);
        if (convertAmount > currentBbc) {
          return res.status(400).json({ error: 'Insufficient BBC tokens' });
        }
        
        newBbcTokens = (currentBbc - convertAmount).toFixed(6);
        newBalance = (parseFloat(user.balance) + (convertAmount * conversionRate)).toFixed(2);
      } else if (fromCurrency === 'coins' && toCurrency === 'bbc') {
        const currentBalance = parseFloat(user.balance);
        if (convertAmount > currentBalance) {
          return res.status(400).json({ error: 'Insufficient coins' });
        }
        
        newBalance = (currentBalance - convertAmount).toFixed(2);
        newBbcTokens = (parseFloat(user.bbcTokens) + (convertAmount / conversionRate)).toFixed(6);
      }

      await storage.updateUserBalance(req.session.userId!, newBalance, newBbcTokens);
      
      res.json({ newBalance, newBbcTokens });
    } catch (error) {
      res.status(400).json({ error: 'Currency conversion failed' });
    }
  });

  // Mining Routes
  app.post('/api/mining/claim', requireAuth, async (req, res) => {
    try {
      const bbcMined = 0.003; // Fixed amount for demo
      const user = await storage.getUser(req.session.userId!);
      
      if (!user) {
        return res.status(404).json({ error: 'User not found' });
      }

      const newBbcTokens = (parseFloat(user.bbcTokens) + bbcMined).toFixed(6);
      await storage.updateUserBalance(req.session.userId!, user.balance, newBbcTokens);
      
      await storage.createMiningActivity(req.session.userId!, bbcMined.toFixed(6));

      res.json({ bbcMined, newBbcTokens });
    } catch (error) {
      res.status(500).json({ error: 'Mining claim failed' });
    }
  });

  // Admin Routes
  app.get('/api/admin/users', requireAdmin, async (req, res) => {
    try {
      const users = await storage.getAllUsers();
      res.json({ users });
    } catch (error) {
      res.status(500).json({ error: 'Failed to get users' });
    }
  });

  app.put('/api/admin/users/:id/status', requireAdmin, async (req, res) => {
    try {
      const userId = parseInt(req.params.id);
      const { status } = req.body;
      
      await storage.updateUserStatus(userId, status);
      await storage.createAdminLog(req.session.adminId!, `User ${status}`, userId, `User status changed to ${status}`);
      
      res.json({ message: 'User status updated' });
    } catch (error) {
      res.status(500).json({ error: 'Failed to update user status' });
    }
  });

  app.get('/api/admin/deposits', requireAdmin, async (req, res) => {
    try {
      const deposits = await storage.getAllDeposits();
      res.json({ deposits });
    } catch (error) {
      res.status(500).json({ error: 'Failed to get deposits' });
    }
  });

  app.post('/api/admin/deposits/:id/approve', requireAdmin, async (req, res) => {
    try {
      const depositId = parseInt(req.params.id);
      await storage.updateDepositStatus(depositId, 'approved');
      await storage.createAdminLog(req.session.adminId!, 'Deposit Approved', undefined, `Deposit ID ${depositId} approved`);
      
      res.json({ message: 'Deposit approved' });
    } catch (error) {
      res.status(500).json({ error: 'Failed to approve deposit' });
    }
  });

  app.post('/api/admin/deposits/:id/reject', requireAdmin, async (req, res) => {
    try {
      const depositId = parseInt(req.params.id);
      await storage.updateDepositStatus(depositId, 'rejected');
      await storage.createAdminLog(req.session.adminId!, 'Deposit Rejected', undefined, `Deposit ID ${depositId} rejected`);
      
      res.json({ message: 'Deposit rejected' });
    } catch (error) {
      res.status(500).json({ error: 'Failed to reject deposit' });
    }
  });

  app.get('/api/admin/withdrawals', requireAdmin, async (req, res) => {
    try {
      const withdrawals = await storage.getAllWithdrawals();
      res.json({ withdrawals });
    } catch (error) {
      res.status(500).json({ error: 'Failed to get withdrawals' });
    }
  });

  app.post('/api/admin/withdrawals/:id/approve', requireAdmin, async (req, res) => {
    try {
      const withdrawalId = parseInt(req.params.id);
      await storage.updateWithdrawalStatus(withdrawalId, 'approved');
      await storage.createAdminLog(req.session.adminId!, 'Withdrawal Approved', undefined, `Withdrawal ID ${withdrawalId} approved`);
      
      res.json({ message: 'Withdrawal approved' });
    } catch (error) {
      res.status(500).json({ error: 'Failed to approve withdrawal' });
    }
  });

  app.post('/api/admin/withdrawals/:id/reject', requireAdmin, async (req, res) => {
    try {
      const withdrawalId = parseInt(req.params.id);
      await storage.updateWithdrawalStatus(withdrawalId, 'rejected');
      await storage.createAdminLog(req.session.adminId!, 'Withdrawal Rejected', undefined, `Withdrawal ID ${withdrawalId} rejected`);
      
      res.json({ message: 'Withdrawal rejected' });
    } catch (error) {
      res.status(500).json({ error: 'Failed to reject withdrawal' });
    }
  });

  app.get('/api/admin/logs', requireAdmin, async (req, res) => {
    try {
      const logs = await storage.getAdminLogs();
      res.json({ logs });
    } catch (error) {
      res.status(500).json({ error: 'Failed to get admin logs' });
    }
  });

  app.get('/api/system/settings', async (req, res) => {
    try {
      const settings = await storage.getSystemSettings();
      res.json({ settings });
    } catch (error) {
      res.status(500).json({ error: 'Failed to get system settings' });
    }
  });

  const httpServer = createServer(app);
  return httpServer;
}

// Game logic functions
function calculateGameResult(gameType: string, betAmount: number, gameData: any) {
  let winAmount = 0;
  let bbcWon = 0;
  let gameResult: any = {};

  switch (gameType) {
    case 'luck-and-roll':
      const wheelResult = Math.floor(Math.random() * 16);
      const wheelOutcomes = [
        'bankrupt', 'bankrupt', 'bankrupt', 'bankrupt', 'bankrupt', 'bankrupt',
        1.1, 1.3, 1.5, 1.8, 2.0, 4.0, 5.0, 8.0, 10.0,
        'jackpot'
      ];
      
      const outcome = wheelOutcomes[wheelResult];
      if (outcome === 'bankrupt') {
        winAmount = 0;
      } else if (outcome === 'jackpot') {
        winAmount = betAmount;
        bbcWon = betAmount * 0.05;
      } else {
        winAmount = betAmount * (outcome as number);
      }
      
      gameResult = { wheelResult, outcome };
      break;

    case 'flip-it-jonathan':
      // Coin flip logic: use player's choice and return actual flip result
      const flip = Math.random() > 0.5 ? 'heads' : 'tails';
      const playerChoice = gameData && gameData.choice;
      const isCorrect = playerChoice === flip;
      if (isCorrect) {
        winAmount = betAmount * 1.5;
      }
      gameResult = { isCorrect, flipResult: flip, playerChoice };
      break;

    case 'paldo': {
      // Real slot logic with 50 paylines
      const isFreeSpin = !!(gameData && gameData.freeSpins);
      const reels = randomReels();
      const { win, winPositions, winningPaylines, scatterWin, freeSpinsAwarded } = evaluateSpin(reels, betAmount, isFreeSpin);

      // Example: No $BBC bonus logic here, but you can add it
      let bbcWon = 0;

      // $BBC logic
      const totalWin = win + scatterWin;
      if (freeSpinsAwarded === 20 || winPositions.length >= 5 && reels.flat().filter(s => s === "⭐").length >= 5) {
        // Jackpot: 5 scatters or 5 wilds
        bbcWon = betAmount * 0.05;
      } else if (totalWin >= betAmount * 10) {
        // Big win: 10x or more
        bbcWon = betAmount * 0.01;
      } else if (Math.random() < 0.1) {
        // Random small bonus
        bbcWon = betAmount * 0.001;
      }

      gameResult = {
        reels,
        winAmount: totalWin,
        bbcWon,
        freeSpinsAwarded,
        winningPositions: winPositions,
        winningPaylines,
      };
      winAmount = totalWin;
      break;
    }

    case 'ipis-sipi':
      // Progressive multiplier game
      const steps = Math.floor(Math.random() * 9) + 1;
      const multipliers = [1.2, 1.5, 2.0, 3.0, 5.0, 8.0, 12.0, 16.0, 20.0];
      winAmount = betAmount * multipliers[steps - 1];
      if (steps === 9) {
        bbcWon = betAmount * 0.2;
      }
      gameResult = { steps, multiplier: multipliers[steps - 1] };
      break;

    case 'blow-it-bolims':
      // Crash game logic - faster multiplier growth
      // Instead of a linear 0.01x, use 0.05x per tick for a faster pace
      const crashPoint = 1 + Math.random() * 10; // Where the balloon bursts
      const cashoutPoint = gameData.cashoutPoint || 1.5;
      // The frontend should increment the multiplier by 0.05x per tick
      if (cashoutPoint <= crashPoint) {
        winAmount = betAmount * cashoutPoint;
      }
      gameResult = { crashPoint, cashoutPoint, success: cashoutPoint <= crashPoint, increment: 0.05 };
      break;

    case 'starburst':
      // Starburst slot game logic
      const reels = randomReels();
      const { win, winPositions, winningPaylines, scatterCount, scatterWin, freeSpinsAwarded } = evaluateSpin(reels, betAmount, false);
      
      winAmount = win + scatterWin;
      gameResult = { reels, winAmount, winPositions, winningPaylines, scatterCount, freeSpinsAwarded };
      break;
  }

  // Random BBC jackpot chance (0-10%)
  if (Math.random() < 0.1 && bbcWon === 0) {
    bbcWon = betAmount * 0.001;
  }

  return { winAmount, bbcWon, gameResult };
}

// --- SYMBOLS ---
const SYMBOLS = [
  "A", "K", "Q", "J", "10", "9", "♠", "♥", "♦", "♣", "⭐", "💎"
];

// --- PAYTABLE (original multipliers) ---
const PAYTABLE: Record<string, number[]> = {
  "⭐":   [0, 0, 5, 15, 50],
  "💎":  [0, 0, 0, 0, 0],
  "A":   [0, 0, 3, 10, 30],
  "K":   [0, 0, 1.5, 4, 10],
  "Q":   [0, 0, 0.5, 1, 2.5],
  "J":   [0, 0, 0.5, 1, 2.5],
  "10":  [0, 0, 0.5, 1, 2.5],
  "9":   [0, 0, 0.5, 1, 2.5],
  "♠":   [0, 0, 1.5, 4, 10],
  "♥":   [0, 0, 1.5, 4, 10],
  "♦":   [0, 0, 3, 10, 30],
  "♣":   [0, 0, 1.5, 4, 10],
};

// --- 50 PAYLINES ---
const PAYLINES = [
  [0,0,0,0,0], [1,1,1,1,1], [2,2,2,2,2], // horizontal
  [0,1,2,1,0], [2,1,0,1,2], // V and inverted V
  [0,0,1,0,0], [2,2,1,2,2], // small V
  [1,0,0,0,1], [1,2,2,2,1], // small inverted V
  [0,1,1,1,0], [2,1,1,1,2], // middle lines
  [0,1,0,1,0], [2,1,2,1,2], // zigzags
  [1,0,1,0,1], [1,2,1,2,1], // zigzags
  [0,2,0,2,0], [2,0,2,0,2], // zigzags
  [0,2,1,0,2], [2,0,1,2,0], // zigzags
  [1,0,2,0,1], // custom
];

// --- RANDOM REELS ---
function randomReels(): string[][] {
  // Make wilds and scatters extremely rare
  const weightedSymbols = [
    ...Array(18).fill("A"),
    ...Array(18).fill("K"),
    ...Array(18).fill("Q"),
    ...Array(18).fill("J"),
    ...Array(18).fill("10"),
    ...Array(18).fill("9"),
    ...Array(12).fill("♠"),
    ...Array(12).fill("♥"),
    ...Array(12).fill("♦"),
    ...Array(12).fill("♣"),
    ...Array(1).fill("⭐"),   // Wild (extremely rare)
    ...Array(1).fill("💎"),  // Scatter (extremely rare)
  ];
  return Array.from({ length: 5 }, () =>
    Array.from({ length: 3 }, () => weightedSymbols[Math.floor(Math.random() * weightedSymbols.length)])
  );
}

// --- WIN DETECTION ---
function evaluateSpin(reels: string[][], bet: number, isFreeSpin: boolean) {
  let win = 0;
  let winPositions: {reel: number, row: number}[] = [];
  let winningPaylines: {reel: number, row: number}[][] = [];
  let scatterCount = 0;
  let scatterWin = 0;
  let freeSpinsAwarded = 0;

  // Count scatters
  for (let r = 0; r < 5; r++) for (let c = 0; c < 3; c++)
    if (reels[r][c] === "💎") scatterCount++;

  // Scatter logic
  if (scatterCount >= 3) {
    if (scatterCount === 3) { freeSpinsAwarded = 10; }
    if (scatterCount === 4) { freeSpinsAwarded = 15; scatterWin = bet * 2; }
    if (scatterCount === 5) { freeSpinsAwarded = 20; scatterWin = bet * 5; }
  }

  // Payline wins
  for (const payline of PAYLINES) {
    let match = 1;
    let symbol = reels[0][payline[0]];
    let positions = [{reel: 0, row: payline[0]}];

    // Wild logic
    for (let i = 1; i < 5; i++) {
      const current = reels[i][payline[i]];
      if (current === symbol || current === "⭐" || symbol === "⭐") {
        match++;
        positions.push({reel: i, row: payline[i]});
        if (symbol === "⭐" && current !== "⭐") symbol = current;
      } else break;
    }

    if (match >= 3 && symbol !== "💎") {
      let payout = PAYTABLE[symbol]?.[match] || 0;
      if (isFreeSpin) payout *= 2; // Free spin multiplier
      win += payout * bet;
      winPositions = winPositions.concat(positions.slice(0, match));
      winningPaylines.push(positions.slice(0, match));
    }
  }

  return { win, winPositions, winningPaylines, scatterCount, scatterWin, freeSpinsAwarded };
}
