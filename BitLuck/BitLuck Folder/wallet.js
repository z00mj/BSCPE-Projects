window.onload = () => {
    const connectBtn = document.getElementById("connectBtn");
    const addressEl = document.getElementById("address");
    const balanceEl = document.getElementById("balance");

    const walletInput = document.getElementById("walletAddressInput");
    const balanceInput = document.getElementById("tokenBalanceInput");
    const depositAmountInput = document.getElementById("depositAmountInput");
    const depositForm = document.getElementById("depositForm");

    const tokenAddress = "0x5FbDB2315678afecb367f032d93F642f64180aa3";
    const casinoWallet = "0x70997970C51812dc3A010C7d01b50e0d17dc79C8"; // ⛳ Replace with actual recipient 0xf39Fd6e51aad88F6F4ce6aB8827279cffFb92266

    const tokenABI = [
        "function balanceOf(address) view returns (uint)",
        "function transfer(address to, uint amount) returns (bool)"
    ];

    let provider, signer, token, userAddress;

    connectBtn.onclick = async () => {
        if (window.ethereum) {
            provider = new ethers.providers.Web3Provider(window.ethereum);
            await provider.send("eth_requestAccounts", []);
            signer = provider.getSigner();
            userAddress = await signer.getAddress();

            token = new ethers.Contract(tokenAddress, tokenABI, signer);
            const rawBalance = await token.balanceOf(userAddress);
            const balance = ethers.utils.formatUnits(rawBalance, 18);

            addressEl.innerText = userAddress;
            balanceEl.innerText = balance;
            walletInput.value = userAddress;
            balanceInput.value = balance;

        } else {
            alert("Please install MetaMask.");
        }
    };

    // Intercept form submission
    // Intercept form submission
    depositForm.onsubmit = async (e) => {
        e.preventDefault();

        const depositAmount = parseFloat(depositAmountInput.value);
        if (isNaN(depositAmount) || depositAmount <= 0) {
            alert("❌ Enter a valid deposit amount.");
            return;
        }

        const parsedAmount = ethers.utils.parseUnits(depositAmount.toString(), 18);

        try {
            const tx = await token.transfer(casinoWallet, parsedAmount);
            await tx.wait(); // Wait for transaction to be mined
            console.log("✅ Tokens sent on-chain.");

            // Send deposit info to PHP
            const formData = new FormData();
            formData.append("deposit_amount", depositAmount);
            formData.append("wallet_address", walletInput.value); // ✅ Add this line

            fetch("backend/deposit.php", {
                method: "POST",
                body: formData
            })
                .then(res => res.text())
                .then(html => {
                    document.body.innerHTML = html; // Replace content with confirmation
                })
                .catch(err => {
                    console.error("❌ Failed to sync with backend:", err);
                    alert("❌ Deposit failed to sync with backend.");
                });

        } catch (err) {
            console.error("❌ Token transfer failed:", err);
            alert("❌ On-chain token transfer failed. Make sure you have enough balance.");
        }
    };

};
