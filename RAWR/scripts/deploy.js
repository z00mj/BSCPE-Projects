async function main() {
    const [deployer] = await ethers.getSigners();
    const Token = await ethers.getContractFactory("RAWRToken");
    const token = await Token.deploy(1000000); // 1,000,000 RAWR
    await token.deployed();
    console.log("RAWRToken deployed to:", token.address);
}

main().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});

