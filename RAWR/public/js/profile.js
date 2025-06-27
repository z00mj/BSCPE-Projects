document.addEventListener('DOMContentLoaded', () => {
    // --- DOM Elements ---
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');

    const saveProfileBtn = document.getElementById('saveProfileBtn');
    const changePasswordBtn = document.getElementById('changePasswordBtn');
    
    // Avatar Elements
    const editAvatarBtn = document.getElementById('editAvatarBtn');
    const uploadAvatarBtn = document.getElementById('uploadAvatarBtn');
    const avatarFileInput = document.getElementById('avatarFileInput');

    // KYC Elements
    const savePersonalInfoBtn = document.getElementById('savePersonalInfoBtn');
    const saveIdDocumentBtn = document.getElementById('saveIdDocumentBtn');
    const saveSelfieBtn = document.getElementById('saveSelfieBtn');
    const reviewKycBtn = document.getElementById('reviewKycBtn');
    
    const idFrontUploadArea = document.getElementById('idFrontUploadArea');
    const idFrontDocumentInput = document.getElementById('idFrontDocument');
    const idFrontPreview = document.getElementById('idFrontPreview');
    const idBackUploadArea = document.getElementById('idBackUploadArea');
    const idBackDocumentInput = document.getElementById('idBackDocument');
    const idBackPreview = document.getElementById('idBackPreview');
    const selfieUploadArea = document.getElementById('selfieUploadArea');
    const selfieDocumentInput = document.getElementById('selfieDocument');
    const selfiePreview = document.getElementById('selfiePreview');
    
    // Modal Elements
    const kycReviewModal = document.getElementById('kycReviewModal');
    const closeModalBtn = document.getElementById('closeModal');
    const cancelReviewBtn = document.getElementById('cancelReviewBtn');
    const agreeTermsCheckbox = document.getElementById('agreeTerms');
    const confirmSubmitBtn = document.getElementById('confirmSubmitBtn');

    // Wallet Elements
    const connectWalletBtn = document.getElementById('connectWalletBtn');


    // --- Helper Functions ---
    function showNotification(message, type = "info") {
        const notification = document.createElement('div');
        // Using a more specific class to avoid conflicts
        notification.className = `custom-notification-toast ${type}`;
        notification.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
        document.body.appendChild(notification);
        
        // Add styles dynamically to avoid needing a separate CSS file for this
        Object.assign(notification.style, {
            position: 'fixed', bottom: '20px', right: '-300px',
            backgroundColor: type === 'success' ? '#28a745' : '#dc3545',
            color: 'white', padding: '15px 20px', borderRadius: '8px',
            boxShadow: '0 4px 15px rgba(0,0,0,0.2)', transition: 'right 0.5s ease-in-out',
            zIndex: '2000', display: 'flex', alignItems: 'center', gap: '10px'
        });

        setTimeout(() => {
            notification.style.right = '20px';
            setTimeout(() => {
                notification.style.right = '-350px';
                setTimeout(() => notification.remove(), 500);
            }, 3000);
        }, 100);
    }

    function handleFetch(action, formData) {
        return fetch('profile.php', { method: 'POST', body: formData })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => Promise.reject(err));
                }
                return response.json();
            })
            .then(data => {
                if (data.status === 'success') {
                    showNotification(data.message, 'success');
                    return data; // Pass data for further processing
                } else {
                    showNotification(data.message, 'error');
                    return Promise.reject(data);
                }
            })
            .catch(error => {
                console.error(`Error during ${action}:`, error);
                const errorMessage = error.message || 'An unknown error occurred.';
                showNotification(errorMessage, 'error');
                return Promise.reject(error);
            });
    }

    function setupUploadArea(area, input, preview) {
        if (!area || !input || !preview) return;
        area.addEventListener('click', () => input.click());
        input.addEventListener('change', () => {
            if (input.files.length > 0) {
                const file = input.files[0];
                const reader = new FileReader();
                reader.onload = (e) => {
                    preview.innerHTML = `<div class="file-item"><img src="${e.target.result}" alt="Preview"><div class="file-remove" onclick="this.parentElement.remove();"><i class="fas fa-times"></i></div></div>`;
                };
                reader.readAsDataURL(file);
            }
        });
    }
    
    // --- Event Listeners ---
    menuToggle?.addEventListener('click', () => sidebar.classList.toggle('active'));
    document.addEventListener('click', (e) => {
        if (sidebar?.classList.contains('active') && !sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
            sidebar.classList.remove('active');
        }
    });

    tabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            tabBtns.forEach(b => b.classList.remove('active'));
            tabContents.forEach(c => c.classList.remove('active'));
            btn.classList.add('active');
            const activeTab = document.getElementById(btn.dataset.tab + 'Tab');
            if (activeTab) activeTab.classList.add('active');
        });
    });

    // --- Avatar Upload Logic ---
    function uploadAvatar(file) {
        const formData = new FormData();
        formData.append('action', 'upload_avatar');
        formData.append('avatar', file);

        handleFetch('upload_avatar', formData)
            .then(data => {
                const newAvatarUrl = data.new_avatar_path;
                
                // Update header avatar
                const headerAvatarContainer = document.getElementById('profileAvatar');
                if (headerAvatarContainer) {
                    headerAvatarContainer.innerHTML = `<img src="${newAvatarUrl}" alt="Profile Avatar" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">`;
                }

                // Update settings preview avatar
                const settingsAvatarContainer = document.getElementById('settingsAvatarPreview');
                 if (settingsAvatarContainer) {
                    settingsAvatarContainer.innerHTML = `<img src="${newAvatarUrl}" alt="Profile Avatar" style="width:100%; height:100%; object-fit:cover; border-radius:50%;">`;
                }
            })
            .catch(err => console.error("Avatar upload failed.", err));
    }
    
    uploadAvatarBtn?.addEventListener('click', () => avatarFileInput.click());
    editAvatarBtn?.addEventListener('click', () => {
        // Switch to settings tab and trigger file input
        document.querySelector('.tab-btn[data-tab="settings"]').click();
        avatarFileInput.click();
    });
    
    avatarFileInput?.addEventListener('change', e => {
        if (e.target.files.length > 0) {
            const file = e.target.files[0];
            if (file.size > 2 * 1024 * 1024) { // 2MB client-side check
                showNotification('File is too large. Max size is 2MB.', 'error');
                return;
            }
            if (!['image/jpeg', 'image/png', 'image/gif'].includes(file.type)) {
                showNotification('Invalid file type. Use JPG, PNG, or GIF.', 'error');
                return;
            }
            uploadAvatar(file);
        }
    });

    // --- KYC and Profile Form Submission ---
    saveProfileBtn?.addEventListener('click', () => {
        const formData = new FormData();
        formData.append('action', 'update_profile');
        formData.append('username', document.getElementById('settingsUsername').value);
        formData.append('bio', document.getElementById('settingsBio').value);
        handleFetch('update_profile', formData).then(data => {
            document.getElementById('profileUsername').textContent = data.new_username;
        });
    });

    savePersonalInfoBtn?.addEventListener('click', () => {
        const formData = new FormData();
        formData.append('action', 'save_personal_info');
        formData.append('fullName', document.getElementById('fullName').value);
        formData.append('dob', document.getElementById('dob').value);
        formData.append('country', document.getElementById('country').value);
        formData.append('contactNumber', document.getElementById('contactNumber').value);
        formData.append('address', document.getElementById('address').value);
        formData.append('city', document.getElementById('city').value);
        formData.append('stateProvince', document.getElementById('stateProvince').value);
        formData.append('postalCode', document.getElementById('postalCode').value);
        handleFetch('save_personal_info', formData);
    });

    saveIdDocumentBtn?.addEventListener('click', () => {
        const formData = new FormData();
        formData.append('action', 'submit_kyc_document');
        formData.append('idType', document.getElementById('idType').value);
        formData.append('idNumber', document.getElementById('idNumber').value);
        
        if (idFrontDocumentInput.files.length > 0) {
            formData.append('id_front_document', idFrontDocumentInput.files[0]);
        } else {
            showNotification('Front of ID document is required.', 'error');
            return;
        }
        
        if (idBackDocumentInput.files.length > 0) {
            formData.append('id_back_document', idBackDocumentInput.files[0]);
        }
        
        handleFetch('submit_kyc_document', formData);
    });

    saveSelfieBtn?.addEventListener('click', () => {
        const formData = new FormData();
        formData.append('action', 'submit_selfie');

        if (selfieDocumentInput.files.length > 0) {
            formData.append('selfie_document', selfieDocumentInput.files[0]);
        } else {
            showNotification('Selfie document is required.', 'error');
            return;
        }

        handleFetch('submit_selfie', formData);
    });
    
    // --- MetaMask Wallet Connection Logic ---
    function updateWalletUI(account) {
        const walletStepContent = document.querySelector('.kyc-step:nth-child(3) .step-content');
        if (!walletStepContent) return;

        // Remove the initial connection button/div
        const connectionDiv = walletStepContent.querySelector('.wallet-connection');
        if (connectionDiv) connectionDiv.remove();

        // Remove any old status divs to prevent duplicates
        walletStepContent.querySelector('.verification-badge')?.remove();
        walletStepContent.querySelector('.form-group')?.remove();
        
        const shortAddress = `${account.substring(0, 6)}...${account.substring(account.length - 4)}`;

        // Add the new "Connected" status badge
        const statusBadge = document.createElement('div');
        statusBadge.className = 'verification-badge';
        statusBadge.style.cssText = "background: rgba(0, 128, 0, 0.2); color: #0f0; padding: 0.5rem 1rem; border-radius: 5px;";
        statusBadge.innerHTML = `<i class="fas fa-check-circle"></i> Wallet Connected: <span style="font-weight: bold;">${shortAddress}</span>`;
        
        // Add the new readonly input field with the full address
        const addressGroup = document.createElement('div');
        addressGroup.className = 'form-group';
        addressGroup.style.marginTop = '1rem';
        addressGroup.innerHTML = `
            <label class="form-label" for="walletAddress">Connected Wallet Address</label>
            <input type="text" class="form-input" id="walletAddress" value="${account}" readonly>
        `;

        walletStepContent.appendChild(statusBadge);
        walletStepContent.appendChild(addressGroup);
    }

    async function connectMetaMask() {
        if (typeof window.ethereum === 'undefined') {
            showNotification('Please install MetaMask to use this feature!', 'error');
            return;
        }
        
        try {
            const accounts = await window.ethereum.request({ method: 'eth_requestAccounts' });
            const account = accounts[0];

            const formData = new FormData();
            formData.append('action', 'save_wallet_address');
            formData.append('wallet_address', account);

            handleFetch('save_wallet_address', formData)
                .then(data => {
                    if (data.status === 'success') {
                        updateWalletUI(data.wallet_address);
                        // Here you could add logic to update the KYC progress bar dynamically
                    }
                });
        } catch (error) {
            console.error('MetaMask connection error:', error);
            if (error.code === 4001) { // User rejected the request
                showNotification('MetaMask connection request was rejected.', 'error');
            } else {
                showNotification('Failed to connect MetaMask.', 'error');
            }
        }
    }

    connectWalletBtn?.addEventListener('click', connectMetaMask);

    // Initialize all upload areas
    setupUploadArea(idFrontUploadArea, idFrontDocumentInput, idFrontPreview);
    setupUploadArea(idBackUploadArea, idBackDocumentInput, idBackPreview);
    setupUploadArea(selfieUploadArea, selfieDocumentInput, selfiePreview);
});s