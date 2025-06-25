import mongoose, { Schema, Document } from 'mongoose';

// User Interface
export interface IUser extends Document {
  username: string;
  password: string;
  balance: string;
  meowBalance: string;
  isAdmin: boolean;
  isBanned: boolean;
  createdAt: Date;
}

// User Schema
const userSchema = new Schema<IUser>({
  username: { type: String, required: true, unique: true },
  password: { type: String, required: true },
  balance: { type: String, default: "1000.00" },
  meowBalance: { type: String, default: "0.00000000" },
  isAdmin: { type: Boolean, default: false },
  isBanned: { type: Boolean, default: false },
  createdAt: { type: Date, default: Date.now }
});

// Deposit Interface
export interface IDeposit extends Document {
  userId: mongoose.Types.ObjectId;
  amount: string;
  paymentMethod: string;
  receiptUrl?: string;
  status: string;
  createdAt: Date;
}

// Deposit Schema
const depositSchema = new Schema<IDeposit>({
  userId: { type: Schema.Types.ObjectId, ref: 'User', required: true },
  amount: { type: String, required: true },
  paymentMethod: { type: String, required: true },
  receiptUrl: { type: String },
  status: { type: String, default: 'pending' },
  createdAt: { type: Date, default: Date.now }
});

// Withdrawal Interface
export interface IWithdrawal extends Document {
  userId: mongoose.Types.ObjectId;
  amount: string;
  platform: string;
  accountInfo: string;
  status: string;
  createdAt: Date;
}

// Withdrawal Schema
const withdrawalSchema = new Schema<IWithdrawal>({
  userId: { type: Schema.Types.ObjectId, ref: 'User', required: true },
  amount: { type: String, required: true },
  platform: { type: String, required: true },
  accountInfo: { type: String, required: true },
  status: { type: String, default: 'pending' },
  createdAt: { type: Date, default: Date.now }
});

// Game History Interface
export interface IGameHistory extends Document {
  userId: mongoose.Types.ObjectId;
  gameType: string;
  betAmount: string;
  winAmount: string;
  meowWon: string;
  result?: string;
  nonce: number;
  createdAt: Date;
}

// Game History Schema
const gameHistorySchema = new Schema<IGameHistory>({
  userId: { type: Schema.Types.ObjectId, ref: 'User', required: true },
  gameType: { type: String, required: true },
  betAmount: { type: String, required: true },
  winAmount: { type: String, default: "0.00" },
  meowWon: { type: String, default: "0.00000000" },
  result: { type: String },
  nonce: { type: Number, required: true },
  createdAt: { type: Date, default: Date.now }
});

// Jackpot Interface
export interface IJackpot extends Document {
  amount: string;
  lastWinnerId?: mongoose.Types.ObjectId;
  lastWonAt?: Date;
  updatedAt: Date;
}

// Jackpot Schema
const jackpotSchema = new Schema<IJackpot>({
  amount: { type: String, default: "0.10000000" },
  lastWinnerId: { type: Schema.Types.ObjectId, ref: 'User' },
  lastWonAt: { type: Date },
  updatedAt: { type: Date, default: Date.now }
});

// Farm Cat Interface
export interface IFarmCat extends Document {
  userId: mongoose.Types.ObjectId;
  catId: string;
  level: number;
  production: number;
  lastClaimed: Date;
  createdAt: Date;
}

// Farm Cat Schema
const farmCatSchema = new mongoose.Schema({
  userId: { type: mongoose.Schema.Types.ObjectId, ref: 'User', required: true },
  catId: { type: String, required: true },
  level: { type: Number, default: 1 },
  production: { type: Number, required: true },
  lastClaimed: { type: Date, default: Date.now },
  happiness: { type: Number, default: 50 },
  name: { type: String, default: null },
  createdAt: { type: Date, default: Date.now }
});

// Export Models
export const User = mongoose.model<IUser>('User', userSchema);
export const Deposit = mongoose.model<IDeposit>('Deposit', depositSchema);
export const Withdrawal = mongoose.model<IWithdrawal>('Withdrawal', withdrawalSchema);
export const GameHistory = mongoose.model<IGameHistory>('GameHistory', gameHistorySchema);
export const Jackpot = mongoose.model<IJackpot>('Jackpot', jackpotSchema);
export const FarmCat = mongoose.model<IFarmCat>('FarmCat', farmCatSchema);