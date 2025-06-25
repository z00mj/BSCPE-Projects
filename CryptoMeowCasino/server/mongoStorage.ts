import { IStorage } from "./storage";
import { connectToMongoDB } from "../mongodb-connection";
import { User, Deposit, Withdrawal, GameHistory, Jackpot, FarmCat } from "../mongodb-schemas";
import bcrypt from "bcrypt";
import mongoose from "mongoose";

export class MongoStorage implements IStorage {
  private isConnected = false;

  async init(): Promise<void> {
    try {
      this.isConnected = await connectToMongoDB();
      if (this.isConnected) {
        console.log('✅ MongoStorage initialized successfully');
        await this.initializeDefaultData();
      } else {
        console.log('❌ MongoStorage failed to initialize');
      }
    } catch (error) {
      console.error('❌ MongoStorage initialization failed:', error);
      this.isConnected = false;
    }
  }

  private async initializeDefaultData(): Promise<void> {
    try {
      // Initialize jackpot if it doesn't exist
      const existingJackpot = await Jackpot.findOne();
      if (!existingJackpot) {
        await new Jackpot({
          amount: "0.10000000",
          updatedAt: new Date()
        }).save();
        console.log('✅ Jackpot initialized');
      }

      // Create admin user if it doesn't exist
      const adminUser = await User.findOne({ username: "admin" });
      if (!adminUser) {
        const hashedPassword = await bcrypt.hash("admin1234", 10);
        await new User({
          username: "admin",
          password: hashedPassword,
          balance: "10000.00",
          meowBalance: "1.00000000",
          isAdmin: true,
          isBanned: false,
          createdAt: new Date()
        }).save();
        console.log('✅ Admin user created');
      }
    } catch (error) {
      console.error('❌ Failed to initialize default data:', error);
    }
  }

  async getUser(id: number): Promise<any> {
    if (!this.isConnected) return undefined;
    try {
      console.log("Looking up user with ID:", id, "type:", typeof id);
      
      // First try to find by the converted ID format (last 6 chars of ObjectId as hex)
      const users = await User.find();
      const user = users.find(u => {
        const convertedId = parseInt(u._id.toString().slice(-6), 16);
        return convertedId === id;
      });
      
      console.log("MongoDB query result:", user ? `Found user: ${user.username}` : "No user found");
      return user ? this.convertMongoUser(user) : undefined;
    } catch (error) {
      console.error('Error getting user:', error);
      return undefined;
    }
  }

  async getUserByUsername(username: string): Promise<any> {
    if (!this.isConnected) return undefined;
    try {
      const user = await User.findOne({ username });
      return user ? this.convertMongoUser(user) : undefined;
    } catch (error) {
      console.error('Error getting user by username:', error);
      return undefined;
    }
  }

  async createUser(insertUser: any): Promise<any> {
    if (!this.isConnected) throw new Error('MongoDB not connected');
    try {
      const hashedPassword = await bcrypt.hash(insertUser.password, 10);
      const user = new User({
        username: insertUser.username,
        password: hashedPassword,
        balance: "1000.00",
        meowBalance: "0.00000000",
        isAdmin: false,
        isBanned: false,
        createdAt: new Date()
      });
      const savedUser = await user.save();
      return this.convertMongoUser(savedUser);
    } catch (error) {
      console.error('Error creating user:', error);
      throw error;
    }
  }

  async updateUserBalance(userId: number, balance: string, meowBalance?: string): Promise<void> {
    if (!this.isConnected) return;
    try {
      const updateData: any = { balance };
      if (meowBalance !== undefined) {
        updateData.meowBalance = meowBalance;
      }
      
      // Find user by converted ID
      const users = await User.find();
      const user = users.find(u => {
        const convertedId = parseInt(u._id.toString().slice(-6), 16);
        return convertedId === userId;
      });
      
      if (user) {
        await User.findOneAndUpdate({ _id: user._id }, updateData);
      }
    } catch (error) {
      console.error('Error updating user balance:', error);
    }
  }

  async createDeposit(deposit: any): Promise<any> {
    if (!this.isConnected) throw new Error('MongoDB not connected');
    try {
      // Find user by converted ID to get real ObjectId
      const users = await User.find();
      const user = users.find(u => {
        const convertedId = parseInt(u._id.toString().slice(-6), 16);
        return convertedId === deposit.userId;
      });
      
      if (!user) {
        throw new Error('User not found');
      }
      
      const newDeposit = new Deposit({
        userId: user._id,
        amount: deposit.amount,
        paymentMethod: deposit.paymentMethod,
        receiptUrl: deposit.receiptUrl || null,
        status: "pending",
        createdAt: new Date()
      });
      const savedDeposit = await newDeposit.save();
      return this.convertMongoDeposit(savedDeposit);
    } catch (error) {
      console.error('Error creating deposit:', error);
      throw error;
    }
  }

  async getDeposits(): Promise<any[]> {
    if (!this.isConnected) return [];
    try {
      const deposits = await Deposit.find().sort({ createdAt: -1 });
      return deposits.map(d => this.convertMongoDeposit(d));
    } catch (error) {
      console.error('Error getting deposits:', error);
      return [];
    }
  }

  async updateDepositStatus(id: number, status: string): Promise<void> {
    if (!this.isConnected) return;
    try {
      // Find deposit by converted ID
      const deposits = await Deposit.find();
      const deposit = deposits.find(d => {
        const convertedId = parseInt(d._id.toString().slice(-6), 16);
        return convertedId === id;
      });

      if (!deposit) {
        console.error('Deposit not found with ID:', id);
        return;
      }

      // Update the deposit status
      await Deposit.findOneAndUpdate({ _id: deposit._id }, { status });

      if (status === "approved") {
        const user = await User.findById(deposit.userId);
        if (user) {
          const newBalance = (parseFloat(user.balance) + parseFloat(deposit.amount)).toFixed(2);
          // Convert back to our ID format
          const convertedUserId = parseInt(user._id.toString().slice(-6), 16);
          await this.updateUserBalance(convertedUserId, newBalance);
        }
      }
    } catch (error) {
      console.error('Error updating deposit status:', error);
    }
  }

  async createWithdrawal(withdrawal: any): Promise<any> {
    if (!this.isConnected) throw new Error('MongoDB not connected');
    try {
      // Find user by converted ID to get real ObjectId
      const users = await User.find();
      const user = users.find(u => {
        const convertedId = parseInt(u._id.toString().slice(-6), 16);
        return convertedId === withdrawal.userId;
      });
      
      if (!user) {
        throw new Error('User not found');
      }
      
      const newWithdrawal = new Withdrawal({
        userId: user._id,
        amount: withdrawal.amount,
        platform: withdrawal.platform,
        accountInfo: withdrawal.accountInfo,
        status: "pending",
        createdAt: new Date()
      });
      const savedWithdrawal = await newWithdrawal.save();
      return this.convertMongoWithdrawal(savedWithdrawal);
    } catch (error) {
      console.error('Error creating withdrawal:', error);
      throw error;
    }
  }

  async getWithdrawal(id: number): Promise<any> {
    if (!this.isConnected) return undefined;
    try {
      // Find withdrawal by converted ID
      const withdrawals = await Withdrawal.find();
      const withdrawal = withdrawals.find(w => {
        const convertedId = parseInt(w._id.toString().slice(-6), 16);
        return convertedId === id;
      });
      
      return withdrawal ? this.convertMongoWithdrawal(withdrawal) : undefined;
    } catch (error) {
      console.error('Error getting withdrawal:', error);
      return undefined;
    }
  }

  async getWithdrawals(): Promise<any[]> {
    if (!this.isConnected) return [];
    try {
      const withdrawals = await Withdrawal.find().sort({ createdAt: -1 });
      return withdrawals.map(w => this.convertMongoWithdrawal(w));
    } catch (error) {
      console.error('Error getting withdrawals:', error);
      return [];
    }
  }

  async getUserWithdrawals(userId: number): Promise<any[]> {
    if (!this.isConnected) return [];
    try {
      // Find user by converted ID to get real ObjectId
      const users = await User.find();
      const user = users.find(u => {
        const convertedId = parseInt(u._id.toString().slice(-6), 16);
        return convertedId === userId;
      });
      
      if (!user) {
        return []; // User not found
      }
      
      const withdrawals = await Withdrawal.find({
        userId: user._id
      }).sort({ createdAt: -1 });
      return withdrawals.map(w => this.convertMongoWithdrawal(w));
    } catch (error) {
      console.error('Error getting user withdrawals:', error);
      return [];
    }
  }

  async updateWithdrawalStatus(id: number, status: string): Promise<void> {
    if (!this.isConnected) return;
    try {
      // Find withdrawal by converted ID
      const withdrawals = await Withdrawal.find();
      const withdrawal = withdrawals.find(w => {
        const convertedId = parseInt(w._id.toString().slice(-6), 16);
        return convertedId === id;
      });

      if (!withdrawal) {
        console.error('Withdrawal not found with ID:', id);
        return;
      }

      // Update the withdrawal status
      await Withdrawal.findOneAndUpdate({ _id: withdrawal._id }, { status });
    } catch (error) {
      console.error('Error updating withdrawal status:', error);
    }
  }

  async createGameHistory(game: any): Promise<any> {
    if (!this.isConnected) throw new Error('MongoDB not connected');
    try {
      // Find user by converted ID to get real ObjectId
      const users = await User.find();
      const user = users.find(u => {
        const convertedId = parseInt(u._id.toString().slice(-6), 16);
        return convertedId === game.userId;
      });
      
      if (!user) {
        throw new Error('User not found');
      }
      
      const newGame = new GameHistory({
        userId: user._id,
        gameType: game.gameType,
        betAmount: game.betAmount,
        winAmount: game.winAmount || "0.00",
        meowWon: game.meowWon || "0.00000000",
        result: game.result || null,
        nonce: game.nonce || 1,
        createdAt: new Date()
      });
      const savedGame = await newGame.save();

      // Update jackpot based on losses
      if (parseFloat(game.winAmount || "0") === 0) {
        const jackpot = await Jackpot.findOne();
        if (jackpot) {
          const currentJackpot = parseFloat(jackpot.amount);
          const betAmount = parseFloat(game.betAmount);
          // 0.1 MEOW per 1000 coins bet = 0.0001 MEOW per coin
          const jackpotIncrease = betAmount * 0.0001;
          jackpot.amount = (currentJackpot + jackpotIncrease).toFixed(8);
          jackpot.updatedAt = new Date();
          await jackpot.save();
        }
      }

      return this.convertMongoGameHistory(savedGame);
    } catch (error) {
      console.error('Error creating game history:', error);
      throw error;
    }
  }

  async getGameHistory(userId?: number): Promise<any[]> {
    if (!this.isConnected) return [];
    try {
      let query = {};
      if (userId) {
        // Find user by converted ID to get real ObjectId
        const users = await User.find();
        const user = users.find(u => {
          const convertedId = parseInt(u._id.toString().slice(-6), 16);
          return convertedId === userId;
        });
        
        if (user) {
          query = { userId: user._id };
        } else {
          return []; // User not found
        }
      }
      const history = await GameHistory.find(query).sort({ createdAt: -1 });
      return history.map(h => this.convertMongoGameHistory(h));
    } catch (error) {
      console.error('Error getting game history:', error);
      return [];
    }
  }

  async getJackpot(): Promise<any> {
    if (!this.isConnected) {
      return {
        id: 1,
        amount: "0.10000000",
        lastWinnerId: null,
        lastWonAt: null,
        updatedAt: new Date()
      };
    }
    try {
      let jackpot = await Jackpot.findOne();
      if (!jackpot) {
        jackpot = new Jackpot({
          amount: "0.10000000",
          updatedAt: new Date()
        });
        await jackpot.save();
      }
      return {
        id: 1,
        amount: jackpot.amount,
        lastWinnerId: jackpot.lastWinnerId ? parseInt(jackpot.lastWinnerId.toString()) : null,
        lastWonAt: jackpot.lastWonAt,
        updatedAt: jackpot.updatedAt
      };
    } catch (error) {
      console.error('Error getting jackpot:', error);
      return {
        id: 1,
        amount: "0.10000000",
        lastWinnerId: null,
        lastWonAt: null,
        updatedAt: new Date()
      };
    }
  }

  async updateJackpot(amount: string, winnerId?: number): Promise<void> {
    if (!this.isConnected) return;
    try {
      const updateData: any = { amount, updatedAt: new Date() };
      if (winnerId) {
        updateData.lastWinnerId = new mongoose.Types.ObjectId(winnerId.toString().padStart(24, '0'));
        updateData.lastWonAt = new Date();
      }
      await Jackpot.findOneAndUpdate({}, updateData, { upsert: true });
    } catch (error) {
      console.error('Error updating jackpot:', error);
    }
  }

  async getAllUsers(): Promise<any[]> {
    if (!this.isConnected) return [];
    try {
      const users = await User.find().sort({ createdAt: -1 });
      return users.map(u => this.convertMongoUser(u));
    } catch (error) {
      console.error('Error getting all users:', error);
      return [];
    }
  }

  async banUser(userId: number, banned: boolean): Promise<void> {
    if (!this.isConnected) return;
    try {
      // Find user by converted ID
      const users = await User.find();
      const user = users.find(u => {
        const convertedId = parseInt(u._id.toString().slice(-6), 16);
        return convertedId === userId;
      });
      
      if (user) {
        await User.findOneAndUpdate({ _id: user._id }, { isBanned: banned });
      }
    } catch (error) {
      console.error('Error banning user:', error);
    }
  }

  async getFarmData(userId: number): Promise<any> {
    if (!this.isConnected) {
      return {
        cats: [],
        totalProduction: 0,
        unclaimedMeow: "0.00000000"
      };
    }
    try {
      // Find user by converted ID to get real ObjectId
      const users = await User.find();
      const user = users.find(u => {
        const convertedId = parseInt(u._id.toString().slice(-6), 16);
        return convertedId === userId;
      });
      
      if (!user) {
        return {
          cats: [],
          totalProduction: 0,
          unclaimedMeow: "0.00000000"
        };
      }

      const cats = await FarmCat.find({
        userId: user._id
      });

      let totalProduction = 0;
      let unclaimedMeow = 0;
      const now = new Date();

      const catsWithRewards = cats.map(cat => {
        const timeSinceLastClaim = (now.getTime() - new Date(cat.lastClaimed).getTime()) / (1000 * 60 * 60); // hours
        const rewards = parseFloat(cat.production.toString()) * timeSinceLastClaim;
        totalProduction += parseFloat(cat.production.toString());
        unclaimedMeow += rewards;

        return {
          id: parseInt(cat._id.toString().slice(-6), 16),
          userId: parseInt(cat.userId.toString().slice(-6), 16),
          catId: cat.catId,
          level: cat.level,
          production: parseFloat(cat.production.toString()),
          lastClaim: cat.lastClaimed.toISOString(),
          createdAt: cat.createdAt
        };
      });

      return {
        cats: catsWithRewards,
        totalProduction,
        unclaimedMeow: unclaimedMeow.toFixed(8)
      };
    } catch (error) {
      console.error('Error getting farm data:', error);
      return {
        cats: [],
        totalProduction: 0,
        unclaimedMeow: "0.00000000"
      };
    }
  }

  async createFarmCat(data: { userId: number; catId: string; production: number }): Promise<any> {
    if (!this.isConnected) return null;
    try {
      // Find user by converted ID to get real ObjectId
      const users = await User.find();
      const user = users.find(u => {
        const convertedId = parseInt(u._id.toString().slice(-6), 16);
        return convertedId === data.userId;
      });
      
      if (!user) {
        throw new Error('User not found');
      }
      
      const farmCat = new FarmCat({
        userId: user._id,
        catId: data.catId,
        production: data.production,
        level: 1,
        lastClaimed: new Date(),
        createdAt: new Date()
      });
      const saved = await farmCat.save();
      return {
        id: parseInt(saved._id.toString().slice(-6), 16),
        userId: data.userId,
        catId: saved.catId,
        level: saved.level,
        production: saved.production,
        lastClaimed: saved.lastClaimed,
        createdAt: saved.createdAt
      };
    } catch (error) {
      console.error('Error creating farm cat:', error);
      return null;
    }
  }

  async getFarmCat(id: number): Promise<any> {
    if (!this.isConnected) return null;
    try {
      // Find cat by converted ID
      const cats = await FarmCat.find();
      const cat = cats.find(c => {
        const convertedId = parseInt(c._id.toString().slice(-6), 16);
        return convertedId === id;
      });
      
      if (!cat) return null;
      
      return {
        id: parseInt(cat._id.toString().slice(-6), 16),
        userId: parseInt(cat.userId.toString().slice(-6), 16),
        catId: cat.catId,
        level: cat.level,
        production: parseFloat(cat.production.toString()),
        lastClaimed: cat.lastClaimed,
        createdAt: cat.createdAt
      };
    } catch (error) {
      console.error('Error getting farm cat:', error);
      return null;
    }
  }

  async upgradeFarmCat(id: number, level: number, production: number): Promise<void> {
    if (!this.isConnected) return;
    try {
      // Find cat by converted ID to get real ObjectId
      const cats = await FarmCat.find();
      const cat = cats.find(c => {
        const convertedId = parseInt(c._id.toString().slice(-6), 16);
        return convertedId === id;
      });
      
      if (cat) {
        await FarmCat.findOneAndUpdate(
          { _id: cat._id },
          { level, production }
        );
      }
    } catch (error) {
      console.error('Error upgrading farm cat:', error);
    }
  }

  async claimFarmRewards(userId: number): Promise<void> {
    if (!this.isConnected) return;
    try {
      // Find user by converted ID to get real ObjectId
      const users = await User.find();
      const user = users.find(u => {
        const convertedId = parseInt(u._id.toString().slice(-6), 16);
        return convertedId === userId;
      });
      
      if (user) {
        await FarmCat.updateMany(
          { userId: user._id },
          { lastClaimed: new Date() }
        );
      }
    } catch (error) {
      console.error('Error claiming farm rewards:', error);
    }
  }

  // Helper methods to convert MongoDB documents to the expected format
  private convertMongoUser(user: any): any {
    return {
      id: parseInt(user._id.toString().slice(-6), 16),
      username: user.username,
      password: user.password,
      balance: user.balance,
      meowBalance: user.meowBalance,
      isAdmin: user.isAdmin,
      isBanned: user.isBanned,
      createdAt: user.createdAt
    };
  }

  private convertMongoDeposit(deposit: any): any {
    return {
      id: parseInt(deposit._id.toString().slice(-6), 16),
      userId: parseInt(deposit.userId.toString().slice(-6), 16),
      amount: deposit.amount,
      status: deposit.status,
      receiptUrl: deposit.receiptUrl,
      createdAt: deposit.createdAt
    };
  }

  private convertMongoWithdrawal(withdrawal: any): any {
    return {
      id: parseInt(withdrawal._id.toString().slice(-6), 16),
      userId: parseInt(withdrawal.userId.toString().slice(-6), 16),
      amount: withdrawal.amount,
      platform: withdrawal.platform,
      accountInfo: withdrawal.accountInfo,
      status: withdrawal.status,
      createdAt: withdrawal.createdAt
    };
  }

  private convertMongoGameHistory(game: any): any {
    return {
      id: parseInt(game._id.toString().slice(-6), 16),
      userId: parseInt(game.userId.toString().slice(-6), 16),
      gameType: game.gameType,
      betAmount: game.betAmount,
      winAmount: game.winAmount,
      meowWon: game.meowWon,
      result: game.result,
      createdAt: game.createdAt
    };
  }
}