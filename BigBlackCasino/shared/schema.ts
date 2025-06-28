import { pgTable, text, serial, integer, boolean, timestamp, decimal, json } from "drizzle-orm/pg-core";
import { relations } from "drizzle-orm";
import { createInsertSchema } from "drizzle-zod";
import { z } from "zod";

export const users = pgTable("users", {
  id: serial("id").primaryKey(),
  username: text("username").notNull().unique(),
  email: text("email").notNull().unique(),
  password: text("password").notNull(),
  balance: decimal("balance", { precision: 12, scale: 2 }).notNull().default("0.00"),
  bbcTokens: decimal("bbc_tokens", { precision: 12, scale: 6 }).notNull().default("0.000000"),
  status: text("status").notNull().default("active"), // active, suspended, banned
  createdAt: timestamp("created_at").defaultNow().notNull(),
  updatedAt: timestamp("updated_at").defaultNow().notNull(),
});

export const admins = pgTable("admins", {
  id: serial("id").primaryKey(),
  username: text("username").notNull().unique(),
  password: text("password").notNull(),
  createdAt: timestamp("created_at").defaultNow().notNull(),
});

export const deposits = pgTable("deposits", {
  id: serial("id").primaryKey(),
  userId: integer("user_id").notNull().references(() => users.id),
  amount: decimal("amount", { precision: 12, scale: 2 }).notNull(),
  paymentMethod: text("payment_method").notNull(),
  receiptImage: text("receipt_image"),
  status: text("status").notNull().default("pending"), // pending, approved, rejected
  createdAt: timestamp("created_at").defaultNow().notNull(),
  updatedAt: timestamp("updated_at").defaultNow().notNull(),
});

export const withdrawals = pgTable("withdrawals", {
  id: serial("id").primaryKey(),
  userId: integer("user_id").notNull().references(() => users.id),
  amount: decimal("amount", { precision: 12, scale: 2 }).notNull(),
  currency: text("currency").notNull(), // coins, bbc
  withdrawalMethod: text("withdrawal_method").notNull(),
  accountDetails: text("account_details").notNull(),
  status: text("status").notNull().default("pending"), // pending, approved, rejected
  createdAt: timestamp("created_at").defaultNow().notNull(),
  updatedAt: timestamp("updated_at").defaultNow().notNull(),
});

export const gameResults = pgTable("game_results", {
  id: serial("id").primaryKey(),
  userId: integer("user_id").notNull().references(() => users.id),
  gameType: text("game_type").notNull(), // luck-and-roll, flip-it-jonathan, paldo, ipis-sipi, blow-it-bolims
  betAmount: decimal("bet_amount", { precision: 12, scale: 2 }).notNull(),
  winAmount: decimal("win_amount", { precision: 12, scale: 2 }).notNull().default("0.00"),
  bbcWon: decimal("bbc_won", { precision: 12, scale: 6 }).notNull().default("0.000000"),
  gameData: json("game_data"), // Store game-specific data (wheel result, flip sequence, etc.)
  createdAt: timestamp("created_at").defaultNow().notNull(),
});

export const adminLogs = pgTable("admin_logs", {
  id: serial("id").primaryKey(),
  adminId: integer("admin_id").notNull().references(() => admins.id),
  action: text("action").notNull(),
  targetUserId: integer("target_user_id").references(() => users.id),
  details: text("details"),
  createdAt: timestamp("created_at").defaultNow().notNull(),
});

export const systemSettings = pgTable("system_settings", {
  id: serial("id").primaryKey(),
  jackpotPool: decimal("jackpot_pool", { precision: 12, scale: 6 }).notNull().default("0.100000"),
  totalBbcDistributed: decimal("total_bbc_distributed", { precision: 12, scale: 6 }).notNull().default("0.000000"),
  updatedAt: timestamp("updated_at").defaultNow().notNull(),
});

export const miningActivity = pgTable("mining_activity", {
  id: serial("id").primaryKey(),
  userId: integer("user_id").notNull().references(() => users.id),
  bbcMined: decimal("bbc_mined", { precision: 12, scale: 6 }).notNull(),
  createdAt: timestamp("created_at").defaultNow().notNull(),
});

// Relations
export const usersRelations = relations(users, ({ many }) => ({
  deposits: many(deposits),
  withdrawals: many(withdrawals),
  gameResults: many(gameResults),
  miningActivity: many(miningActivity),
}));

export const depositsRelations = relations(deposits, ({ one }) => ({
  user: one(users, {
    fields: [deposits.userId],
    references: [users.id],
  }),
}));

export const withdrawalsRelations = relations(withdrawals, ({ one }) => ({
  user: one(users, {
    fields: [withdrawals.userId],
    references: [users.id],
  }),
}));

export const gameResultsRelations = relations(gameResults, ({ one }) => ({
  user: one(users, {
    fields: [gameResults.userId],
    references: [users.id],
  }),
}));

export const adminLogsRelations = relations(adminLogs, ({ one }) => ({
  admin: one(admins, {
    fields: [adminLogs.adminId],
    references: [admins.id],
  }),
  targetUser: one(users, {
    fields: [adminLogs.targetUserId],
    references: [users.id],
  }),
}));

export const miningActivityRelations = relations(miningActivity, ({ one }) => ({
  user: one(users, {
    fields: [miningActivity.userId],
    references: [users.id],
  }),
}));

// Schemas for validation
export const insertUserSchema = createInsertSchema(users).pick({
  username: true,
  email: true,
  password: true,
});

export const insertDepositSchema = createInsertSchema(deposits).pick({
  amount: true,
  paymentMethod: true,
  receiptImage: true,
});

export const insertWithdrawalSchema = createInsertSchema(withdrawals).pick({
  amount: true,
  currency: true,
  withdrawalMethod: true,
  accountDetails: true,
});

export const insertGameResultSchema = createInsertSchema(gameResults).pick({
  gameType: true,
  betAmount: true,
  winAmount: true,
  bbcWon: true,
  gameData: true,
});

export const loginSchema = z.object({
  username: z.string().min(1, "Username is required"),
  password: z.string().min(1, "Password is required"),
});

export const adminLoginSchema = z.object({
  username: z.string().min(1, "Username is required"),
  password: z.string().min(1, "Password is required"),
});

// Types
export type InsertUser = z.infer<typeof insertUserSchema>;
export type User = typeof users.$inferSelect;
export type Admin = typeof admins.$inferSelect;
export type InsertDeposit = z.infer<typeof insertDepositSchema>;
export type Deposit = typeof deposits.$inferSelect;
export type InsertWithdrawal = z.infer<typeof insertWithdrawalSchema>;
export type Withdrawal = typeof withdrawals.$inferSelect;
export type InsertGameResult = z.infer<typeof insertGameResultSchema>;
export type GameResult = typeof gameResults.$inferSelect;
export type AdminLog = typeof adminLogs.$inferSelect;
export type SystemSettings = typeof systemSettings.$inferSelect;
export type MiningActivity = typeof miningActivity.$inferSelect;
