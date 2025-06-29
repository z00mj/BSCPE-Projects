// scripts/accounts.js
async function main() {
  const accounts = await ethers.getSigners();

  for (const account of accounts) {
    console.log(`Address: ${account.address}`);
  }
}

main()
  .then(() => process.exit(0))
  .catch((error) => {
    console.error(error);
    process.exit(1);
  });
