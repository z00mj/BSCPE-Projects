const fs = require("fs");
const path = require("path");

async function main() {
  const [deployer] = await ethers.getSigners();
  console.log("Deploying contract with:", deployer.address);

  const CasinoToken = await ethers.getContractFactory("CasinoToken");
  const token = await CasinoToken.deploy(1000000); // 1 million tokens
  await token.waitForDeployment();

  const address = await token.getAddress();
  console.log("CasinoToken deployed to:", address);


}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
