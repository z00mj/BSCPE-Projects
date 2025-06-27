

        // --- DOM Elements ---
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const tabBtns = document.querySelectorAll('.tab-btn');
        const tabContents = document.querySelectorAll('.tab-content');

        // Overview Tab Elements
        const overviewRawrBalance = document.getElementById('overviewRawrBalance');
        const overviewTicketsBalance = document.getElementById('overviewTicketsBalance');
        const overviewRank = document.getElementById('overviewRank');
        const overviewTotalWins = document.getElementById('overviewTotalWins');
        const currentLoginStreak = document.getElementById('currentLoginStreak');
        const longestLoginStreak = document.getElementById('longestLoginStreak');
        const totalRawrMined = document.getElementById('totalRawrMined');
        const miningBoostLevel = document.getElementById('miningBoostLevel');

        // Settings Tab Elements
        const profileUsername = document.getElementById('profileUsername');
        const settingsUsername = document.getElementById('settingsUsername');
        const settingsBio = document.getElementById('settingsBio');
        const saveProfileBtn = document.getElementById('saveProfileBtn');
        const currentPasswordInput = document.getElementById('currentPassword');
        const newPasswordInput = document.getElementById('newPassword');
        const confirmNewPasswordInput = document.getElementById('confirmNewPassword');
        const changePasswordBtn = document.getElementById('changePasswordBtn');
        const uploadAvatarBtn = document.getElementById('uploadAvatarBtn');
        const avatarFileInput = document.getElementById('avatarFileInput');

        // KYC Tab Elements
        const kycProgressFill = document.getElementById('kycProgress');
        const progressPercentage = document.getElementById('progressPercentage');
        const fullNameInput = document.getElementById('fullName');
        const dobInput = document.getElementById('dob');
        const countrySelect = document.getElementById('country');
        const contactNumberInput = document.getElementById('contactNumber');
        const addressInput = document.getElementById('address');
        const cityInput = document.getElementById('city');
        const stateProvinceInput = document.getElementById('stateProvince');
        const postalCodeInput = document.getElementById('postalCode');
        const savePersonalInfoBtn = document.getElementById('savePersonalInfoBtn');

        const sendCodeBtn = document.getElementById('sendCodeBtn');
        const codeSection = document.getElementById('codeSection');
        const codeInputs = document.querySelectorAll('.code-input');
        const verifyEmailCodeBtn = document.getElementById('verifyEmailCodeBtn');

        const connectWalletBtn = document.getElementById('connectWalletBtn');
        const walletInfo = document.getElementById('walletInfo');
        const walletAddressInput = document.getElementById('walletAddress');

        const idTypeSelect = document.getElementById('idType');
        const idNumberInput = document.getElementById('idNumber');
        const idFrontUploadArea = document.getElementById('idFrontUploadArea');
        const idFrontDocumentInput = document.getElementById('idFrontDocument');
        const idFrontPreview = document.getElementById('idFrontPreview');
        const idBackUploadArea = document.getElementById('idBackUploadArea');
        const idBackDocumentInput = document.getElementById('idBackDocument');
        const idBackPreview = document.getElementById('idBackPreview');
        const saveIdDocumentBtn = document.getElementById('saveIdDocumentBtn');

        const selfieUploadArea = document.getElementById('selfieUploadArea');
        const selfieDocumentInput = document.getElementById('selfieDocument');
        const selfiePreview = document.getElementById('selfiePreview');
        const saveSelfieBtn = document.getElementById('saveSelfieBtn');

        const reviewKycBtn = document.getElementById('reviewKycBtn');
        const submitKycBtn = document.getElementById('submitKycBtn');

        // Modals
        const kycReviewModal = document.getElementById('kycReviewModal');
        const closeModalBtn = document.getElementById('closeModal');
        const cancelReviewBtn = document.getElementById('cancelReviewBtn');
        const agreeTermsCheckbox = document.getElementById('agreeTerms');
        const confirmSubmitBtn = document.getElementById('confirmSubmitBtn');
        const kycSubmittedModal = document.getElementById('kycSubmittedModal');
        const closeSubmittedModalBtn = document.getElementById('closeSubmittedModal');

        // Review Modal Elements
        const reviewFullName = document.getElementById('reviewFullName');
        const reviewDob = document.getElementById('reviewDob');
        const reviewCountry = document.getElementById('reviewCountry');
        const reviewContact = document.getElementById('reviewContact');
        const reviewAddress = document.getElementById('reviewAddress');
        const reviewCity = document.getElementById('reviewCity');
        const reviewStateProvince = document.getElementById('reviewStateProvince');
        const reviewPostalCode = document.getElementById('reviewPostalCode');
        const reviewIdType = document.getElementById('reviewIdType');
        const reviewIdNumber = document.getElementById('reviewIdNumber');
        const reviewIdFront = document.getElementById('reviewIdFront');
        const reviewIdBack = document.getElementById('reviewIdBack');
        const reviewSelfie = document.getElementById('reviewSelfie');
        const reviewWallet = document.getElementById('reviewWallet');


        // --- Global State / Flags ---
        let currentKycProgressStatus = phpData.kycProgressStatus;
        let currentKycOverallProgress = phpData.kycOverallProgress;
        let verificationCode = null; // Store the generated code

        // --- Functions ---

        // Helper to display messages (replaces alert)
        function showNotification(message, type = "info") {
            const notification = document.createElement('div');
            notification.style.position = 'fixed';
            notification.style.bottom = '20px';
            notification.style.right = '20px';
            notification.style.backgroundColor = type === "success" ? 'rgba(0, 128, 0, 0.2)' :
                                                type === "error" ? 'rgba(255, 0, 0, 0.2)' : 'rgba(30, 30, 30, 0.9)';
            notification.style.color = type === "success" ? '#0f0' :
                                      type === "error" ? '#f55' : 'var(--primary)';
            notification.style.padding = '15px 25px';
            notification.style.borderRadius = '8px';
            notification.style.border = type === "success" ? '1px solid #0f0' :
                                      type === "error" ? '1px solid #f55' : '1px solid var(--primary)';
            notification.style.boxShadow = '0 5px 15px rgba(0, 0, 0, 0.3)';
            notification.style.zIndex = '1000';
            notification.style.transition = 'transform 0.3s ease';
            notification.style.transform = 'translateX(120%)';
            notification.innerHTML = `<i class="fas fa-${type === "success" ? "check-circle" :
                                                       type === "error" ? "exclamation-circle" : "info-circle"}"></i> ${message}`;

            document.body.appendChild(notification);

            // Animate in
            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 10);

            // Remove after 3 seconds
            setTimeout(() => {
                notification.style.transform = 'translateX(120%)';
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 3000);
        }

        // Update overall UI with latest PHP data and calculated states
        function updateUI() {
            overviewRawrBalance.textContent = phpData.rawrBalance.toFixed(2);
            overviewTicketsBalance.textContent = phpData.ticketBalance;
            overviewRank.textContent = phpData.leaderboardRank;
            overviewTotalWins.textContent = phpData.totalGameWins;
            currentLoginStreak.textContent = `${phpData.currentLoginStreak} Days`;
            longestLoginStreak.textContent = `${phpData.longestLoginStreak} Days`;
            totalRawrMined.textContent = `${phpData.totalRawrMined} RAWR`;
            miningBoostLevel.textContent = `x${phpData.miningBoostLevel}`;

            profileUsername.textContent = phpData.username;
            settingsUsername.value = phpData.username;

            // Update KYC progress bar and percentage
            kycProgressFill.style.width = `${phpData.kycOverallProgress}%`;
            progressPercentage.textContent = `${Math.round(phpData.kycOverallProgress)}%`;

            // Update KYC status badge
            const kycBadge = document.querySelector('.profile-header .verification-badge');
            if (kycBadge) {
                kycBadge.remove(); // Remove existing badge to update
            }
            const userInfoDiv = document.querySelector('.user-info');
            const newBadge = document.createElement('div');
            newBadge.classList.add('verification-badge');

            if (phpData.kycStatus === 'approved') {
                newBadge.style.background = 'rgba(0, 128, 0, 0.2)';
                newBadge.style.color = '#0f0';
                newBadge.innerHTML = '<i class="fas fa-shield-alt"></i> Verified Account';
                submitKycBtn.disabled = true; // If already approved, disable final submit
            } else if (phpData.kycStatus === 'pending') {
                newBadge.style.background = 'rgba(255, 165, 0, 0.2)';
                newBadge.style.color = '#FFA500';
                newBadge.innerHTML = '<i class="fas fa-hourglass-half"></i> KYC Pending';
                submitKycBtn.disabled = true; // If pending, disable final submit
            } else { // rejected or not_submitted
                newBadge.style.background = 'rgba(255, 0, 0, 0.2)';
                newBadge.style.color = '#FF6347';
                newBadge.innerHTML = '<i class="fas fa-times-circle"></i> KYC Not Verified';
                submitKycBtn.disabled = false; // Enable if not verified/rejected
            }
            userInfoDiv.appendChild(newBadge);

            // Pre-fill KYC form fields if data exists
            fullNameInput.value = phpData.kycRequestData.fullName;
            dobInput.value = phpData.kycRequestData.dob;
            countrySelect.value = phpData.kycRequestData.country;
            contactNumberInput.value = phpData.kycRequestData.contactNumber;
            addressInput.value = phpData.kycRequestData.address;
            cityInput.value = phpData.kycRequestData.city;
            stateProvinceInput.value = phpData.kycRequestData.stateProvince;
            postalCodeInput.value = phpData.kycRequestData.postalCode;
            idTypeSelect.value = phpData.kycRequestData.idType;
            idNumberInput.value = phpData.kycRequestData.idNumber;
            walletAddressInput.value = phpData.kycRequestData.walletAddress;

            // Display uploaded images if paths exist
            if (phpData.kycRequestData.idFrontPath) {
                idFrontPreview.innerHTML = `<div class="file-item"><img src="${phpData.kycRequestData.idFrontPath}" alt="ID Front"><div class="file-remove" onclick="this.parentElement.remove(); phpData.kycRequestData.idFrontPath = ''; updateKycProgress();"><i class="fas fa-times"></i></div></div>`;
            }
            if (phpData.kycRequestData.idBackPath) {
                idBackPreview.innerHTML = `<div class="file-item"><img src="${phpData.kycRequestData.idBackPath}" alt="ID Back"><div class="file-remove" onclick="this.parentElement.remove(); phpData.kycRequestData.idBackPath = ''; updateKycProgress();"><i class="fas fa-times"></i></div></div>`;
            }
            if (phpData.kycRequestData.selfiePath) {
                selfiePreview.innerHTML = `<div class="file-item"><img src="${phpData.kycRequestData.selfiePath}" alt="Selfie with ID"><div class="file-remove" onclick="this.parentElement.remove(); phpData.kycRequestData.selfiePath = ''; updateKycProgress();"><i class="fas fa-times"></i></div></div>`;
            }

            // Update status of KYC step buttons/sections
            // This is largely driven by `currentKycProgressStatus` but also `phpData.kycStatus` (overall from DB)
            // For example, if wallet is connected:
            if (phpData.kycRequestData.walletAddress) {
                connectWalletBtn.innerHTML = '<i class="fas fa-check"></i> Connected';
                connectWalletBtn.disabled = true;
                walletInfo.style.display = 'block';
            } else {
                 connectWalletBtn.innerHTML = '<i class="fab fa-metamask"></i> Connect MetaMask';
                 connectWalletBtn.disabled = false;
                 walletInfo.style.display = 'none';
            }

            // If email is verified, disable send code btn and show badge
            const emailVerifiedBadge = document.querySelector('.kyc-step:nth-child(2) .verification-badge');
            if (phpData.kycProgressStatus.emailVerified) {
                if (emailVerifiedBadge) emailVerifiedBadge.remove(); // Remove old one if it exists
                 const emailSection = document.querySelector('.kyc-step:nth-child(2) .step-content');
                 const newEmailBadge = document.createElement('div');
                 newEmailBadge.classList.add('verification-badge');
                 newEmailBadge.style.cssText = 'background: rgba(0, 128, 0, 0.2); color: #0f0; padding: 0.5rem 1rem; border-radius: 5px;';
                 newEmailBadge.innerHTML = '<i class="fas fa-check-circle"></i> Email Verified';
                 emailSection.insertBefore(newEmailBadge, codeSection); // Insert before codeSection
                 sendCodeBtn.style.display = 'none';
                 codeSection.style.display = 'none';
            } else {
                 if (emailVerifiedBadge) emailVerifiedBadge.remove();
                 sendCodeBtn.style.display = 'inline-flex'; // Show if not verified
            }

            // Update submit KYC button state based on overall KYC status
            if (phpData.kycStatus === 'approved' || phpData.kycStatus === 'pending') {
                submitKycBtn.disabled = true;
                reviewKycBtn.classList.remove('pulse');
            } else if (currentKycOverallProgress === 100) {
                 submitKycBtn.disabled = false;
                 reviewKycBtn.classList.add('pulse');
            } else {
                 submitKycBtn.disabled = true;
                 reviewKycBtn.classList.remove('pulse');
            }
        }


        // Menu Toggle for Mobile
        menuToggle.addEventListener('click', () => {
            sidebar.classList.toggle('active');
        });

        // Close sidebar when clicking outside
        document.addEventListener('click', (e) => {
            if (sidebar.classList.contains('active') &&
                !sidebar.contains(e.target) &&
                !menuToggle.contains(e.target)) {
                sidebar.classList.remove('active');
            }
        });

        // Tab functionality
        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                tabBtns.forEach(b => b.classList.remove('active'));
                tabContents.forEach(c => c.classList.remove('active'));
                btn.classList.add('active');
                document.getElementById(btn.dataset.tab + 'Tab').classList.add('active');
            });
        });

        // Avatar upload simulation (for UI, not actual file upload to server)
        document.getElementById('editAvatarBtn').addEventListener('click', function() {
            const avatars = ["🦁", "🐯", "🐆", "🐘", "🦏", "🦒", "🦧", "🐊", "🦜"];
            const randomAvatar = avatars[Math.floor(Math.random() * avatars.length)];
            document.getElementById('profileAvatar').textContent = randomAvatar;
            showNotification("Avatar updated successfully!");
        });

        uploadAvatarBtn.addEventListener('click', () => {
             avatarFileInput.click();
        });

        avatarFileInput.addEventListener('change', (e) => {
             if (e.target.files.length > 0) {
                 const file = e.target.files[0];
                 const reader = new FileReader();
                 reader.onload = function(event) {
                     document.getElementById('profileAvatar').innerHTML = `<img src="${event.target.result}" alt="Profile Avatar" style="width:100%; height:100%; object-fit:cover; border-radius:50%;">`;
                     showNotification("Avatar uploaded (client-side only).");
                 };
                 reader.readAsDataURL(file);
             }
        });


        // File upload handlers for KYC documents (client-side preview only)
        function setupUploadArea(uploadAreaElement, fileInputElement, previewElement) {
            uploadAreaElement.addEventListener('click', () => {
                fileInputElement.click();
            });

            fileInputElement.addEventListener('change', (e) => {
                if (e.target.files.length > 0) {
                    const file = e.target.files[0];
                    const reader = new FileReader();

                    reader.onload = function(event) {
                        const fileItem = document.createElement('div');
                        fileItem.className = 'file-item';
                        fileItem.innerHTML = `
                            <img src="${event.target.result}" alt="Uploaded file">
                            <div class="file-remove" onclick="this.parentElement.remove(); updateKycProgress();">
                                <i class="fas fa-times"></i>
                            </div>
                        `;
                        previewElement.innerHTML = ''; // Clear previous preview
                        previewElement.appendChild(fileItem);
                        updateKycProgress(); // Update progress after file selected (client-side)
                    };
                    reader.readAsDataURL(file);
                }
            });
        }

        // Initialize file upload areas
        setupUploadArea(idFrontUploadArea, idFrontDocumentInput, idFrontPreview);
        setupUploadArea(idBackUploadArea, idBackDocumentInput, idBackPreview);
        setupUploadArea(selfieUploadArea, selfieDocumentInput, selfiePreview);


        // Update KYC progress logic (client-side)
        function updateKycProgress() {
            let completedSteps = 0;
            // Check if personal info fields are filled
            if (fullNameInput.value && dobInput.value && countrySelect.value && contactNumberInput.value &&
                addressInput.value && cityInput.value && stateProvinceInput.value && postalCodeInput.value) {
                currentKycProgressStatus.personalInfo = true;
            } else {
                currentKycProgressStatus.personalInfo = false;
            }

            // Email status is handled by backend response in real app, simulated here
            // For now, if emailVerified is true from PHP, it remains true.
            // Otherwise, it's set by verifyEmailCode success.

            // Check if wallet is connected
            if (walletAddressInput.value && walletAddressInput.value !== '') {
                currentKycProgressStatus.walletConnected = true;
            } else {
                currentKycProgressStatus.walletConnected = false;
            }

            // Check if ID documents are previewed (implies selected by user)
            if (idFrontPreview.children.length > 0) {
                currentKycProgressStatus.idDocument = true;
            } else {
                currentKycProgressStatus.idDocument = false;
            }

            // Check if selfie is previewed
            if (selfiePreview.children.length > 0) {
                currentKycProgressStatus.selfie = true;
            } else {
                currentKycProgressStatus.selfie = false;
            }

            // Count truly completed steps
            Object.values(currentKycProgressStatus).forEach(isComplete => {
                if (isComplete) {
                    completedSteps++;
                }
            });

            currentKycOverallProgress = (completedSteps / 5) * 100; // Total 5 steps for progress calculation
            kycProgressFill.style.width = currentKycOverallProgress + '%';
            progressPercentage.textContent = Math.round(currentKycOverallProgress) + '%';

            // Enable/disable final submit button based on all steps completion
            if (currentKycOverallProgress === 100 && phpData.kycStatus !== 'approved' && phpData.kycStatus !== 'pending') {
                submitKycBtn.disabled = false;
                reviewKycBtn.classList.add('pulse');
            } else {
                submitKycBtn.disabled = true;
                reviewKycBtn.classList.remove('pulse');
            }
        }


        // --- KYC Step Functions (AJAX calls to backend) ---

        savePersonalInfoBtn.addEventListener('click', () => {
            const formData = new FormData();
            formData.append('action', 'save_personal_info');
            formData.append('fullName', fullNameInput.value);
            formData.append('dob', dobInput.value);
            formData.append('country', countrySelect.value);
            formData.append('contactNumber', contactNumberInput.value);
            formData.append('address', addressInput.value);
            formData.append('city', cityInput.value);
            formData.append('stateProvince', stateProvinceInput.value);
            formData.append('postalCode', postalCodeInput.value);


            fetch('profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showNotification(data.message, 'success');
                    phpData.kycRequestData.fullName = fullNameInput.value;
                    phpData.kycRequestData.dob = dobInput.value;
                    phpData.kycRequestData.country = countrySelect.value;
                    phpData.kycRequestData.contactNumber = contactNumberInput.value;
                    phpData.kycRequestData.address = addressInput.value;
                    phpData.kycRequestData.city = cityInput.value;
                    phpData.kycRequestData.stateProvince = stateProvinceInput.value;
                    phpData.kycRequestData.postalCode = postalCodeInput.value;

                    phpData.kycStatus = data.kyc_status; // Update overall status
                    updateKycProgress(); // Re-evaluate progress
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error saving personal info:', error);
                showNotification('An error occurred saving personal info.', 'error');
            });
        });

        sendCodeBtn.addEventListener('click', () => {
            const email = document.getElementById('verifyEmail').value;
            if (!email) {
                showNotification("Email address is required", "error");
                return;
            }

            fetch('profile.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=send_email_code&email=${encodeURIComponent(email)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showNotification(data.message, 'info');
                    codeSection.style.display = 'block';
                    // In a real app, the backend would generate and store the code securely.
                    // For demo, we simulate a code here:
                    verificationCode = Math.floor(100000 + Math.random() * 900000).toString();
                    console.log("Simulated verification code: " + verificationCode); // For testing
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error sending code:', error);
                showNotification('An error occurred sending verification code.', 'error');
            });
        });

        verifyEmailCodeBtn.addEventListener('click', () => {
            let enteredCode = '';
            codeInputs.forEach(input => {
                enteredCode += input.value;
            });

            if (enteredCode.length !== 6) {
                showNotification("Please enter all 6 digits of the code.", "error");
                return;
            }

            // For demo, we compare with the simulated code
            if (enteredCode === verificationCode) {
                showNotification("Verification code verified successfully!", "success");
                // In a real app, you would also update the email_verified status in the database
                // For demo, we just update the progress status
                phpData.kycProgressStatus.emailVerified = true;
                updateKycProgress();
                // Optionally, you could auto-fill the email in the personal info step
                fullNameInput.focus(); // Focus on the next input for convenience
            } else {
                showNotification("Invalid verification code. Please try again.", "error");
            }
        });


        connectWalletBtn.addEventListener('click', async () => {
            if (typeof window.ethereum === 'undefined') {
                showNotification("MetaMask is not installed. Please install it to connect your wallet.", "error");
                return;
            }

            try {
                const accounts = await window.ethereum.request({ method: 'eth_requestAccounts' });
                const walletAddress = accounts[0]; // Get the first connected account

                // Send wallet address to backend
                const formData = new FormData();
                formData.append('action', 'connect_wallet');
                formData.append('walletAddress', walletAddress);

                fetch('profile.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        showNotification(data.message, 'success');
                        walletAddressInput.value = walletAddress;
                        phpData.kycRequestData.walletAddress = walletAddress;
                        phpData.kycStatus = data.kyc_status || phpData.kycStatus;
                        updateKycProgress();
                        updateUI();
                    } else {
                        showNotification(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error connecting wallet:', error);
                    showNotification('An error occurred connecting wallet.', 'error');
                });
            } catch (err) {
                showNotification("Wallet connection failed: " + err.message, "error");
            }
        });

        // Password change
        changePasswordBtn.addEventListener('click', () => {
            const currentPassword = currentPasswordInput.value;
            const newPassword = newPasswordInput.value;
            const confirmNewPassword = confirmNewPasswordInput.value;

            const formData = new FormData();
            formData.append('action', 'change_password');
            formData.append('current_password', currentPassword);
            formData.append('new_password', newPassword);
            formData.append('confirm_new_password', confirmNewPassword);

            fetch('profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showNotification(data.message, 'success');
                    // Clear password fields
                    currentPasswordInput.value = '';
                    newPasswordInput.value = '';
                    confirmNewPasswordInput.value = '';
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error changing password:', error);
                showNotification('An error occurred while changing password.', 'error');
            });
        });

        // --- KYC Review Modal Logic ---
        reviewKycBtn.addEventListener('click', () => {
            if (phpData.kycStatus === 'approved' || phpData.kycStatus === 'pending') {
                showNotification(`KYC is already ${phpData.kycStatus}.`, 'info');
                return;
            }
            if (currentKycOverallProgress < 100) {
                showNotification("Please complete all KYC steps before reviewing.", "error");
                return;
            }

            // Populate review modal with current form data and file previews
            reviewFullName.textContent = fullNameInput.value;
            reviewDob.textContent = dobInput.value;
            reviewCountry.textContent = countrySelect.options[countrySelect.selectedIndex].text;
            reviewContact.textContent = contactNumberInput.value;
            reviewAddress.textContent = `${addressInput.value}, ${cityInput.value}, ${stateProvinceInput.value}, ${postalCodeInput.value}`;
            reviewCity.textContent = cityInput.value;
            reviewStateProvince.textContent = stateProvinceInput.value;
            reviewPostalCode.textContent = postalCodeInput.value;

            reviewIdType.textContent = idTypeSelect.options[idTypeSelect.selectedIndex].text;
            reviewIdNumber.textContent = idNumberInput.value;

            reviewWallet.textContent = walletAddressInput.value;

            // Image previews
            reviewIdFront.src = idFrontPreview.querySelector('img')?.src || 'https://placehold.co/150x150/000000/FFFFFF?text=No+Image';
            reviewIdBack.src = idBackPreview.querySelector('img')?.src || 'https://placehold.co/150x150/000000/FFFFFF?text=No+Image';
            reviewSelfie.src = selfiePreview.querySelector('img')?.src || 'https://placehold.co/150x150/000000/FFFFFF?text=No+Image';


            kycReviewModal.style.display = 'block';
        });

        closeModalBtn.addEventListener('click', () => {
            kycReviewModal.style.display = 'none';
        });

        cancelReviewBtn.addEventListener('click', () => {
            kycReviewModal.style.display = 'none';
        });

        agreeTermsCheckbox.addEventListener('change', function() {
            confirmSubmitBtn.disabled = !this.checked;
        });

        confirmSubmitBtn.addEventListener('click', () => {
            // Final submission to backend
            fetch('profile.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=final_kyc_submit`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showNotification(data.message, 'success');
                    kycReviewModal.style.display = 'none';
                    kycSubmittedModal.style.display = 'block';
                    phpData.kycStatus = 'pending'; // Update local status to pending
                    updateUI(); // Refresh UI
                } else {
                    showNotification(data.message, 'error');
                    kycReviewModal.style.display = 'none'; // Close review modal even on error
                }
            })
            .catch(error => {
                console.error('Error submitting KYC:', error);
                showNotification('An error occurred during KYC submission.', 'error');
                kycReviewModal.style.display = 'none';
            });
        });

        closeSubmittedModalBtn.addEventListener('click', () => {
            kycSubmittedModal.style.display = 'none';
        });

        // Verification code input navigation
        codeInputs.forEach((input, index, inputs) => {
            input.addEventListener('input', function() {
                if (this.value.length === 1 && index < inputs.length - 1) {
                    inputs[index + 1].focus();
                }
            });

            input.addEventListener('keydown', function(e) {
                if (e.key === 'Backspace' && this.value === '' && index > 0) {
                    inputs[index - 1].focus();
                }
            });
        });

        // Initial UI update on page load
        updateUI();
        updateKycProgress(); // Also call this to calculate initial client-side progress based on PHP data