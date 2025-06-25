import mongoose from 'mongoose';
import { User } from './mongodb-schemas.ts'; // Use the correct path to mongodb-schemas.ts
import bcrypt from 'bcrypt';

async function addAdminUser() {
    const MONGODB_URI = 'mongodb+srv://cryptomeow:cryptomeowadmin@cryptomeowcluster.8u0ufu3.mongodb.net/?retryWrites=true&w=majority&appName=CryptoMeowCluster';

    try {
        await mongoose.connect(MONGODB_URI, {
            useNewUrlParser: true,
            useUnifiedTopology: true
        });

        // Check if admin already exists
        const existingAdmin = await User.findOne({ username: "admin" });
        if (!existingAdmin) {
            const hashedPassword = await bcrypt.hash("admin1234", 10);
            const admin = new User({
                username: "admin",
                password: hashedPassword,
                balance: "10000.00",
                meowBalance: "1.00000000",
                isAdmin: true,
                isBanned: false
            });
            await admin.save();
            console.log("Admin user created successfully!");
        } else {
            console.log("Admin user already exists.");
        }
    } catch (error) {
        console.error("Error adding admin user:", error);
    } finally {
        mongoose.connection.close();
    }
}

addAdminUser();