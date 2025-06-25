
import mongoose from 'mongoose';
import { User, Deposit, Withdrawal, GameHistory, Jackpot, FarmCat } from './mongodb-schemas.ts';
import bcrypt from 'bcrypt';
import fs from 'fs/promises';

async function migrateData() {
    const MONGODB_URI = 'mongodb+srv://cryptomeow:cryptomeowadmin@cryptomeowcluster.8u0ufu3.mongodb.net/?retryWrites=true&w=majority&appName=CryptoMeowCluster';

    try {
        await mongoose.connect(MONGODB_URI);
        console.log('✅ Connected to MongoDB');

        // Read casino-data.json
        const casinoData = JSON.parse(await fs.readFile('./casino-data.json', 'utf8'));
        console.log('📁 Loaded casino-data.json');

        // Clear existing data (optional - remove these lines if you want to keep existing data)
        await User.deleteMany({});
        await Deposit.deleteMany({});
        await Withdrawal.deleteMany({});
        await GameHistory.deleteMany({});
        await Jackpot.deleteMany({});
        await FarmCat.deleteMany({});
        console.log('🗑️ Cleared existing data');

        // Migrate Users
        const userIdMap = new Map();
        for (const user of casinoData.users) {
            const newUser = new User({
                username: user.username,
                password: user.password, // Already hashed
                balance: user.balance,
                meowBalance: user.meowBalance,
                isAdmin: user.isAdmin,
                isBanned: user.isBanned,
                createdAt: new Date(user.createdAt)
            });
            const savedUser = await newUser.save();
            userIdMap.set(user.id, savedUser._id);
            console.log(`👤 Migrated user: ${user.username}`);
        }

        // Migrate Deposits
        for (const deposit of casinoData.deposits) {
            const newDeposit = new Deposit({
                userId: userIdMap.get(deposit.userId),
                amount: deposit.amount,
                paymentMethod: deposit.paymentMethod,
                receiptUrl: deposit.receiptUrl,
                status: deposit.status,
                createdAt: new Date(deposit.createdAt)
            });
            await newDeposit.save();
            console.log(`💰 Migrated deposit: ${deposit.id}`);
        }

        // Migrate Withdrawals
        for (const withdrawal of casinoData.withdrawals) {
            const newWithdrawal = new Withdrawal({
                userId: userIdMap.get(withdrawal.userId),
                amount: withdrawal.amount,
                platform: withdrawal.platform || 'unknown',
                accountInfo: withdrawal.accountInfo || '{}',
                status: withdrawal.status,
                createdAt: new Date(withdrawal.createdAt)
            });
            await newWithdrawal.save();
            console.log(`💸 Migrated withdrawal: ${withdrawal.id}`);
        }

        // Migrate Game History
        for (const game of casinoData.gameHistory) {
            const newGame = new GameHistory({
                userId: userIdMap.get(game.userId),
                gameType: game.gameType,
                betAmount: game.betAmount,
                winAmount: game.winAmount,
                meowWon: game.meowWon,
                result: game.result,
                nonce: game.nonce,
                createdAt: new Date(game.createdAt)
            });
            await newGame.save();
            console.log(`🎮 Migrated game history: ${game.id}`);
        }

        // Migrate Jackpot
        if (casinoData.jackpot) {
            const jackpot = casinoData.jackpot;
            const newJackpot = new Jackpot({
                amount: jackpot.amount,
                lastWinnerId: jackpot.lastWinnerId ? userIdMap.get(jackpot.lastWinnerId) : null,
                lastWonAt: jackpot.lastWonAt ? new Date(jackpot.lastWonAt) : null,
                updatedAt: new Date(jackpot.updatedAt)
            });
            await newJackpot.save();
            console.log('🎰 Migrated jackpot data');
        }

        // Migrate Farm Cats
        if (casinoData.farmCats) {
            for (const farmCat of casinoData.farmCats) {
                const newFarmCat = new FarmCat({
                    userId: userIdMap.get(farmCat.userId),
                    catId: farmCat.catId,
                    level: farmCat.level,
                    production: farmCat.production,
                    lastClaimed: new Date(farmCat.lastClaim),
                    createdAt: new Date(farmCat.createdAt)
                });
                await newFarmCat.save();
                console.log(`🐱 Migrated farm cat: ${farmCat.id}`);
            }
        }

        console.log('✅ Migration completed successfully!');
        console.log(`📊 Migration Summary:`);
        console.log(`   Users: ${casinoData.users.length}`);
        console.log(`   Deposits: ${casinoData.deposits.length}`);
        console.log(`   Withdrawals: ${casinoData.withdrawals.length}`);
        console.log(`   Game History: ${casinoData.gameHistory.length}`);
        console.log(`   Farm Cats: ${casinoData.farmCats?.length || 0}`);

    } catch (error) {
        console.error('❌ Migration failed:', error);
    } finally {
        mongoose.connection.close();
        console.log('🔌 Database connection closed');
    }
}

migrateData();
