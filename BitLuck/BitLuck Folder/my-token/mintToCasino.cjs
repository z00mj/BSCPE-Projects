// mintToCasino.cjs
const hre = require("hardhat");

async function main() {
    const ethers = hre.ethers; // ✅ extract ethers from hre

    const [deployer] = await ethers.getSigners();
    const tokenAddress = "0x5FbDB2315678afecb367f032d93F642f64180aa3"; // ✅ Replace with your deployed token address
    const casinoWallet = "0x70997970C51812dc3A010C7d01b50e0d17dc79C8";  // ✅ Your casino wallet

    const amount = ethers.utils.parseUnits("10", 18); // ✅ Convert 10 tokens to 18 decimals

    const Token = await ethers.getContractFactory("CasinoToken");
    const token = Token.attach(tokenAddress);

    const tx = await token.mint(casinoWallet, amount);
    await tx.wait();

    console.log(`✅ Minted 10 tokens to ${casinoWallet}`);
}

main().catch((err) => {
    console.error("❌ Error minting:", err);
});
