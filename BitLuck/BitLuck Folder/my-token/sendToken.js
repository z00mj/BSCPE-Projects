// sendToken.js

import { JsonRpcProvider, Wallet, Contract, isAddress, parseUnits } from "ethers";
import * as dotenv from "dotenv";
import fs from "fs";

// ✅ Load .env file
dotenv.config();

// ✅ Setup provider and signer
const provider = new JsonRpcProvider("http://127.0.0.1:8545");
const PRIVATE_KEY = process.env.CASINO_PRIVATE_KEY;
const signer = new Wallet(PRIVATE_KEY, provider);

// ✅ ERC-20 token details
const tokenAddress = "0x5FbDB2315678afecb367f032d93F642f64180aa3";
const tokenAbi = [
  "function transfer(address to, uint amount) public returns (bool)"
];

// ✅ Read recipient and amount from CLI
const recipient = process.argv[2];
const amount = process.argv[3];

if (!isAddress(recipient)) {
  console.error("Invalid recipient address.");
  process.exit(1);
}

if (!amount || isNaN(amount) || Number(amount) <= 0) {
  console.error("Invalid amount.");
  process.exit(1);
}

(async () => {
  try {
    const token = new Contract(tokenAddress, tokenAbi, signer);
    const parsedAmount = parseUnits(amount, 18);

    const tx = await token.transfer(recipient, parsedAmount);
    await tx.wait();

    console.log(`Successfully sent ${amount} tokens to ${recipient}`);
    process.exit(0);
  } catch (err) {
    console.error("Token transfer failed:", err.message);
    process.exit(1);
  }
})();
