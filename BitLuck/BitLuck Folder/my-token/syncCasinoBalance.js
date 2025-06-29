// syncCasinoBalance.js

import { JsonRpcProvider, Contract, formatUnits } from "ethers";
import * as dotenv from "dotenv";
import mysql from "mysql2/promise";

// ✅ Load environment variables
dotenv.config();

// ✅ Ethers setup
const provider = new JsonRpcProvider("http://127.0.0.1:8545");
const tokenAddress = "0xDc64a140Aa3E981100a9becA4E685f962f0cF6C9"; // Your token address
const tokenAbi = ["function balanceOf(address account) view returns (uint256)"];

// ✅ Casino wallet address (the one holding funds)
const casinoWallet = "0x70997970C51812dc3A010C7d01b50e0d17dc79C8";

async function main() {
  try {
    const token = new Contract(tokenAddress, tokenAbi, provider);

    // ✅ Get on-chain token balance
    const rawBalance = await token.balanceOf(casinoWallet);
    const readableBalance = parseFloat(formatUnits(rawBalance, 18)); // convert BigNumber → float

    console.log(`✅ On-chain balance: ${readableBalance} tokens`);

    // ✅ Connect to MySQL
    const conn = await mysql.createConnection({
      host: "localhost",
      user: "root",
      password: "",
      database: "ecasinosite",
    });

    // ✅ Update casino_status table
    await conn.execute(
      "UPDATE casino_status SET token_balance = ? WHERE id = 1",
      [readableBalance]
    );

    console.log("✅ Casino DB token balance updated.");
    await conn.end();
  } catch (err) {
    console.error("❌ Failed to sync casino balance:", err.message);
    process.exit(1);
  }
}

main();
