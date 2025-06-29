// receiveToken.js
require("dotenv").config();
const { ethers } = require("ethers");

const provider = new ethers.JsonRpcProvider("http://127.0.0.1:8545");
const signer = new ethers.Wallet(process.env.USER_PRIVATE_KEY, provider); // player signs

const tokenAddress = "0x5FbDB2315678afecb367f032d93F642f64180aa3";
const tokenAbi = ["function transfer(address to, uint amount) public returns (bool)"];

const casinoWallet = "0x70997970C51812dc3A010C7d01b50e0d17dc79C8"; // your casino wallet
const amount = process.argv[2];

(async () => {
  try {
    const token = new ethers.Contract(tokenAddress, tokenAbi, signer);
    const parsedAmount = ethers.utils.parseUnits(amount, 18);
    const tx = await token.transfer(casinoWallet, parsedAmount);
    await tx.wait();
    console.log(`✅ Casino wallet received ${amount} tokens`);
  } catch (err) {
    console.error("❌ Transfer failed:", err.message);
  }
})();
