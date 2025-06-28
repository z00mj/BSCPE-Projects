import { 
  users, 
  admins, 
  deposits, 
  withdrawals, 
  gameResults, 
  adminLogs, 
  systemSettings, 
  miningActivity,
  type User, 
  type Admin,
  type InsertUser, 
  type Deposit,
  type InsertDeposit,
  type Withdrawal,
  type InsertWithdrawal,
  type GameResult,
  type InsertGameResult,
  type AdminLog,
  type SystemSettings,
  type MiningActivity
} from "@shared/schema";
import { db } from "./db";
import { eq, desc, and } from "drizzle-orm";

export interface IStorage {
  // User management
  getUser(id: number): Promise<User | undefined>;
  getUserByUsername(username: string): Promise<User | undefined>;
  getUserByEmail(email: string): Promise<User | undefined>;
  createUser(user: InsertUser): Promise<User>;
  updateUserBalance(userId: number, balance: string, bbcTokens: string): Promise<void>;
  updateUserStatus(userId: number, status: string): Promise<void>;
  getAllUsers(): Promise<User[]>;
  
  // Admin management
  getAdminByUsername(username: string): Promise<Admin | undefined>;
  createAdmin(username: string, password: string): Promise<Admin>;
  
  // Deposit management
  createDeposit(userId: number, deposit: InsertDeposit): Promise<Deposit>;
  getDepositsByStatus(status: string): Promise<Deposit[]>;
  updateDepositStatus(depositId: number, status: string): Promise<void>;
  getAllDeposits(): Promise<Deposit[]>;
  
  // Withdrawal management
  createWithdrawal(userId: number, withdrawal: InsertWithdrawal): Promise<Withdrawal>;
  getWithdrawalsByStatus(status: string): Promise<Withdrawal[]>;
  updateWithdrawalStatus(withdrawalId: number, status: string): Promise<void>;
  getAllWithdrawals(): Promise<Withdrawal[]>;
  
  // Game results
  createGameResult(userId: number, result: InsertGameResult): Promise<GameResult>;
  getUserGameResults(userId: number): Promise<GameResult[]>;
  
  // Admin logs
  createAdminLog(adminId: number, action: string, targetUserId?: number, details?: string): Promise<AdminLog>;
  getAdminLogs(): Promise<AdminLog[]>;
  
  // System settings
  getSystemSettings(): Promise<SystemSettings>;
  updateJackpotPool(amount: string): Promise<void>;
  
  // Mining
  createMiningActivity(userId: number, bbcMined: string): Promise<MiningActivity>;
  getUserMiningActivity(userId: number): Promise<MiningActivity[]>;
}

export class DatabaseStorage implements IStorage {
  async getUser(id: number): Promise<User | undefined> {
    const [user] = await db.select().from(users).where(eq(users.id, id));
    return user || undefined;
  }

  async getUserByUsername(username: string): Promise<User | undefined> {
    const [user] = await db.select().from(users).where(eq(users.username, username));
    return user || undefined;
  }

  async getUserByEmail(email: string): Promise<User | undefined> {
    const [user] = await db.select().from(users).where(eq(users.email, email));
    return user || undefined;
  }

  async createUser(insertUser: InsertUser): Promise<User> {
    const [user] = await db
      .insert(users)
      .values(insertUser)
      .returning();
    return user;
  }

  async updateUserBalance(userId: number, balance: string, bbcTokens: string): Promise<void> {
    await db
      .update(users)
      .set({ balance, bbcTokens, updatedAt: new Date() })
      .where(eq(users.id, userId));
  }

  async updateUserStatus(userId: number, status: string): Promise<void> {
    await db
      .update(users)
      .set({ status, updatedAt: new Date() })
      .where(eq(users.id, userId));
  }

  async getAllUsers(): Promise<User[]> {
    return await db.select().from(users).orderBy(desc(users.createdAt));
  }

  async getAdminByUsername(username: string): Promise<Admin | undefined> {
    const [admin] = await db.select().from(admins).where(eq(admins.username, username));
    return admin || undefined;
  }

  async createAdmin(username: string, password: string): Promise<Admin> {
    const [admin] = await db
      .insert(admins)
      .values({ username, password })
      .returning();
    return admin;
  }

  async createDeposit(userId: number, deposit: InsertDeposit): Promise<Deposit> {
    const [newDeposit] = await db
      .insert(deposits)
      .values({ userId, ...deposit })
      .returning();
    return newDeposit;
  }

  async getDepositsByStatus(status: string): Promise<Deposit[]> {
    return await db
      .select()
      .from(deposits)
      .where(eq(deposits.status, status))
      .orderBy(desc(deposits.createdAt));
  }

  async updateDepositStatus(depositId: number, status: string): Promise<void> {
    await db
      .update(deposits)
      .set({ status, updatedAt: new Date() })
      .where(eq(deposits.id, depositId));
  }

  async getAllDeposits(): Promise<Deposit[]> {
    return await db.select().from(deposits).orderBy(desc(deposits.createdAt));
  }

  async createWithdrawal(userId: number, withdrawal: InsertWithdrawal): Promise<Withdrawal> {
    const [newWithdrawal] = await db
      .insert(withdrawals)
      .values({ userId, ...withdrawal })
      .returning();
    return newWithdrawal;
  }

  async getWithdrawalsByStatus(status: string): Promise<Withdrawal[]> {
    return await db
      .select()
      .from(withdrawals)
      .where(eq(withdrawals.status, status))
      .orderBy(desc(withdrawals.createdAt));
  }

  async updateWithdrawalStatus(withdrawalId: number, status: string): Promise<void> {
    await db
      .update(withdrawals)
      .set({ status, updatedAt: new Date() })
      .where(eq(withdrawals.id, withdrawalId));
  }

  async getAllWithdrawals(): Promise<Withdrawal[]> {
    return await db.select().from(withdrawals).orderBy(desc(withdrawals.createdAt));
  }

  async createGameResult(userId: number, result: InsertGameResult): Promise<GameResult> {
    const [gameResult] = await db
      .insert(gameResults)
      .values({ userId, ...result })
      .returning();
    return gameResult;
  }

  async getUserGameResults(userId: number): Promise<GameResult[]> {
    return await db
      .select()
      .from(gameResults)
      .where(eq(gameResults.userId, userId))
      .orderBy(desc(gameResults.createdAt));
  }

  async createAdminLog(adminId: number, action: string, targetUserId?: number, details?: string): Promise<AdminLog> {
    const [log] = await db
      .insert(adminLogs)
      .values({ adminId, action, targetUserId, details })
      .returning();
    return log;
  }

  async getAdminLogs(): Promise<AdminLog[]> {
    return await db.select().from(adminLogs).orderBy(desc(adminLogs.createdAt));
  }

  async getSystemSettings(): Promise<SystemSettings> {
    const [settings] = await db.select().from(systemSettings).limit(1);
    if (!settings) {
      const [newSettings] = await db.insert(systemSettings).values({}).returning();
      return newSettings;
    }
    return settings;
  }

  async updateJackpotPool(amount: string): Promise<void> {
    const settings = await this.getSystemSettings();
    await db
      .update(systemSettings)
      .set({ jackpotPool: amount, updatedAt: new Date() })
      .where(eq(systemSettings.id, settings.id));
  }

  async createMiningActivity(userId: number, bbcMined: string): Promise<MiningActivity> {
    const [activity] = await db
      .insert(miningActivity)
      .values({ userId, bbcMined })
      .returning();
    return activity;
  }

  async getUserMiningActivity(userId: number): Promise<MiningActivity[]> {
    return await db
      .select()
      .from(miningActivity)
      .where(eq(miningActivity.userId, userId))
      .orderBy(desc(miningActivity.createdAt));
  }
}

export const storage = new DatabaseStorage();
