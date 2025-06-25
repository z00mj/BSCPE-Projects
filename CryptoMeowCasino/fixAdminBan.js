
import mongoose from 'mongoose';
import { User } from './mongodb-schemas.ts';

async function fixAdminBan() {
    const MONGODB_URI = 'mongodb+srv://cryptomeow:cryptomeowadmin@cryptomeowcluster.8u0ufu3.mongodb.net/?retryWrites=true&w=majority&appName=CryptoMeowCluster';

    try {
        await mongoose.connect(MONGODB_URI);
        console.log('Connected to MongoDB');

        // Find and update admin user to unban them
        const result = await User.findOneAndUpdate(
            { username: "admin" },
            { isBanned: false },
            { new: true }
        );

        if (result) {
            console.log('✅ Admin user unbanned successfully');
            console.log('Admin status:', {
                username: result.username,
                isAdmin: result.isAdmin,
                isBanned: result.isBanned
            });
        } else {
            console.log('❌ Admin user not found');
        }

        await mongoose.disconnect();
    } catch (error) {
        console.error('Error fixing admin ban:', error);
    }
}

addAdminUser();
