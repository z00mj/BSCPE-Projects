<?php
session_start();
require_once 'db_connect.php';

// Initialize variables
$message = '';
$error = '';
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$max_file_size = 10 * 1024 * 1024; // 10MB per file
$max_total_size = 500 * 1024 * 1024; // 500MB total
$max_files = 120;
$upload_dir = 'uploads/nfts/';
$collection_upload_dir = 'uploads/collections/';

// Increase PHP limits for large uploads
@ini_set('max_execution_time', 300); // 5 minutes
@ini_set('max_input_time', 300);
@ini_set('memory_limit', '256M');

// Create directories if they don't exist
if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
if (!file_exists($collection_upload_dir)) mkdir($collection_upload_dir, 0777, true);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Set JSON header for AJAX response
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    if ($isAjax) {
        header('Content-Type: application/json');
        // Disable HTML error display for AJAX requests
        ini_set('display_errors', 0);
        error_reporting(0);
    }
    
    try {
        $pdo->beginTransaction();

        // Get form data
        $creator_id = intval($_POST['creator_id'] ?? 0);
        $owner_id = intval($_POST['owner_id'] ?? $creator_id);
        $collection_option = $_POST['collection_option'] ?? 'new';
        
        // Handle AJAX response
        $response = [
            'success' => false,
            'message' => '',
            'redirect' => ''
        ];
        
        if ($collection_option === 'new') {
            // Handle new collection
            $collection_name = trim($_POST['collection_name'] ?? '');
            $collection_description = trim($_POST['collection_description'] ?? '');

            // Validate collection data
            if (empty($collection_name) || empty($_FILES['collection_logo']['name'])) {
                throw new Exception('Collection name and logo are required.');
            }

            // Handle collection logo upload
            $logo_file = $_FILES['collection_logo'];
            $logo_ext = strtolower(pathinfo($logo_file['name'], PATHINFO_EXTENSION));
            $logo_filename = 'collection_' . uniqid() . '.' . $logo_ext;
            $logo_target = $collection_upload_dir . $logo_filename;

            if (!in_array(mime_content_type($logo_file['tmp_name']), $allowed_types)) {
                throw new Exception('Invalid logo file type. Only JPG, PNG, GIF, and WebP are allowed.');
            }

            if ($logo_file['size'] > $max_file_size) {
                throw new Exception('Logo file size must be less than ' . ($max_file_size / (1024 * 1024)) . 'MB.');
            }

            if (!move_uploaded_file($logo_file['tmp_name'], $logo_target)) {
                throw new Exception('Failed to upload collection logo.');
            }

            // Create new collection
            $stmt = $pdo->prepare("
                INSERT INTO luck_wallet_nft_collections 
                (name, description, logo_url, creator_user_id, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$collection_name, $collection_description, $logo_target, $creator_id]);
            $collection_id = $pdo->lastInsertId();
            
            $response['message'] = "Successfully created new collection '{$collection_name}' with ";
            $response['success'] = true;
        } else {
            // Add to existing collection
            $collection_id = intval($_POST['collection_id'] ?? 0);
            
            if ($collection_id <= 0) {
                throw new Exception('Please select a valid collection.');
            }
            
            // Verify collection exists
            $stmt = $pdo->prepare("SELECT name FROM luck_wallet_nft_collections WHERE collection_id = ?");
            $stmt->execute([$collection_id]);
            $collection = $stmt->fetch();
            
            if (!$collection) {
                throw new Exception('Selected collection not found.');
            }
            
            $response['message'] = "Successfully added NFTs to collection '{$collection['name']}'.";
            $response['success'] = true;
        }

        // Process NFT uploads
        if (empty($_FILES['nft_images'])) {
            throw new Exception('Please upload at least one NFT image.');
        }
        
        // Validate total number of files
        $total_files = count($_FILES['nft_images']['name']);
        if ($total_files > $max_files) {
            throw new Exception("You can upload a maximum of {$max_files} files at once.");
        }
        
        // Validate total upload size
        $total_size = array_sum($_FILES['nft_images']['size']);
        if ($total_size > $max_total_size) {
            $max_total_mb = $max_total_size / (1024 * 1024);
            throw new Exception("Total upload size exceeds the maximum limit of {$max_total_mb}MB.");
        }
        
        // Reorganize the $_FILES array to handle multiple file inputs
        $file_ary = [];
        $file_count = count($_FILES['nft_images']['name']);
        $file_keys = array_keys($_FILES['nft_images']);
        
        for ($i = 0; $i < $file_count; $i++) {
            foreach ($file_keys as $key) {
                $file_ary[$i][$key] = $_FILES['nft_images'][$key][$i];
            }
        }
        
        $nft_count = count($file_ary);
        $success_count = 0;
        $file_indexes = $_POST['nft_file_indexes'] ?? [];
        
        // Process only the files that have previews (were not removed by the user)
        foreach ($file_indexes as $price_index => $file_index) {
            $file_index = intval($file_index);
            
            if (!isset($file_ary[$file_index]) || 
                $file_ary[$file_index]['error'] !== UPLOAD_ERR_OK ||
                empty($file_ary[$file_index]['tmp_name'])) {
                continue;
            }
            
            $nft_file = $file_ary[$file_index];

            // Handle NFT image upload
            if (!in_array(mime_content_type($nft_file['tmp_name']), $allowed_types)) {
                continue; // Skip invalid file types
            }

            if ($nft_file['size'] > $max_file_size) {
                continue; // Skip files that are too large
            }

            $nft_ext = strtolower(pathinfo($nft_file['name'], PATHINFO_EXTENSION));
            $nft_filename = 'nft_' . uniqid() . '.' . $nft_ext;
            $nft_target = $upload_dir . $nft_filename;

            if (move_uploaded_file($nft_file['tmp_name'], $nft_target)) {
                $price = floatval($_POST['nft_prices'][$price_index] ?? 0);
                
                $stmt = $pdo->prepare("
                    INSERT INTO luck_wallet_nfts 
                    (collection_id, name, description, image_url, price_luck, 
                     creator_user_id, current_owner_user_id, listed_for_sale, listing_date, is_unique) 
                    VALUES (?, NULL, NULL, ?, ?, ?, ?, 1, NOW(), 0)
                ");
                
                $stmt->execute([
                    $collection_id,
                    $nft_target,
                    $price,
                    $creator_id,
                    $owner_id
                ]);
                
                $success_count++;
            }
        }

        if ($success_count === 0) {
            throw new Exception('Failed to upload any NFT images. Please check file types and sizes.');
        }

        $pdo->commit();
        
        // Update success message with NFT count and set redirect URL
        $response['message'] .= "$success_count NFTs.";
        $response['redirect'] = 'marketplace/collection.php?collection_id=' . $collection_id;
        
        // Send JSON response for AJAX
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode($response);
            exit;
        }
        
        // For non-AJAX fallback
        $message = $response['message'];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
        
        // Send error response for AJAX
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode([
                'success' => false,
                'message' => $error
            ]);
            exit;
        }
    }
}

// Get users for owner/creator selection
$users = $pdo->query("SELECT user_id, username FROM luck_wallet_users ORDER BY username")->fetchAll();

// Get existing collections
$collections = [];
$selected_collection_id = null;

// Check if collection_id is provided in the URL
if (isset($_GET['collection_id'])) {
    $selected_collection_id = intval($_GET['collection_id']);
    // Verify the collection exists and belongs to the current user
    if ($creator_id = $_SESSION['user_id'] ?? null) {
        $stmt = $pdo->prepare("SELECT collection_id, name FROM luck_wallet_nft_collections WHERE collection_id = ? AND creator_user_id = ?");
        $stmt->execute([$selected_collection_id, $creator_id]);
        if ($stmt->rowCount() === 0) {
            $selected_collection_id = null; // Reset if not authorized
        }
    } else {
        $selected_collection_id = null;
    }
}

// Get all collections for the dropdown
$stmt = $pdo->query("SELECT collection_id, name, creator_user_id FROM luck_wallet_nft_collections ORDER BY name");
$collections = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create NFT Collection - Luck Wallet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .container { max-width: 800px; margin: 50px auto; }
        .form-container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
        .form-title { text-align: center; margin-bottom: 30px; color: #333; }
        .btn-primary { background-color: #6f42c1; border: none; padding: 10px 20px; }
        .btn-primary:hover { background-color: #5a32a3; }
        .preview-image { max-width: 150px; max-height: 150px; margin: 10px; border-radius: 5px; }
        .nft-preview-container { display: flex; flex-wrap: wrap; margin: 15px 0; }
        .nft-item { margin: 10px; width: 200px; position: relative; }
        .price-input { width: 100%; margin-top: 5px; }
        .remove-nft {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(0,0,0,0.7);
            color: white;
            border: none;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 10;
        }
        .remove-nft:hover {
            background: rgba(255,0,0,0.8);
        }
        .nft-preview-container {
            display: flex;
            flex-wrap: wrap;
            margin: 15px 0;
            gap: 15px;
        }
        .add-more-container {
            margin: 15px 0;
        }
        .card-img-top {
            height: 150px;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <h2 class="form-title">Create NFT Collection</h2>
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="" enctype="multipart/form-data" id="collectionForm">
                <div class="mb-4">
                    <h4>Collection</h4>
                    <div class="mb-4">
                        <h5 class="mb-3">Collection Options</h5>
                        
                        <div class="card mb-3">
                            <div class="card-body">
                                <?php if (empty($selected_collection_id)): ?>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="radio" name="collection_option" id="new_collection" value="new" checked>
                                        <label class="form-check-label fw-bold" for="new_collection" style="font-size: 1.05rem;">
                                            <i class="material-icons align-middle" style="font-size: 1.2rem; margin-right: 8px;">create_new_folder</i>
                                            Create New Collection
                                        </label>
                                    </div>
                                    
                                    <?php if (!empty($collections)): ?>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="radio" name="collection_option" id="existing_collection" value="existing">
                                        <label class="form-check-label fw-bold" for="existing_collection" style="font-size: 1.05rem;">
                                            <i class="material-icons align-middle" style="font-size: 1.2rem; margin-right: 8px;">add_photo_alternate</i>
                                            Add to Existing Collection
                                        </label>
                                    </div>
                                <?php endif; ?>
                                <?php else: ?>
                                    <input type="hidden" name="collection_option" value="existing">
                                    <div class="alert alert-info d-flex align-items-center">
                                        <i class="material-icons me-2" style="font-size: 1.5rem;">info</i>
                                        <div>
                                            <h5 class="alert-heading mb-1">Adding to Existing Collection</h5>
                                            <p class="mb-0">You're adding NFTs to an existing collection. The collection details cannot be modified here.</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($collections)): ?>
                                <div id="existingCollectionContainer" class="mt-3 p-3 border rounded" style="background-color: #f8f9fa; display: <?php echo $selected_collection_id ? 'block' : 'none'; ?>">
                                    <label for="collection_id" class="form-label fw-bold mb-2">Select Collection</label>
                                    <select class="form-select" id="collection_id" name="collection_id" <?php echo $selected_collection_id ? 'disabled' : ''; ?>>
                                        <?php foreach ($collections as $collection): ?>
                                            <option value="<?php echo $collection['collection_id']; ?>" <?php echo ($selected_collection_id == $collection['collection_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($collection['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if ($selected_collection_id): ?>
                                        <input type="hidden" name="collection_id" value="<?php echo $selected_collection_id; ?>">
                                    <?php endif; ?>
                                    <div class="form-text mt-2">
                                        <i class="material-icons align-middle" style="font-size: 1rem;">info</i>
                                        Select the collection you want to add these NFTs to.
                                    </div>
                                </div>
                        <?php endif; ?>
                    </div>
                    
                    </div>
                    
                    <div id="newCollectionFields">
                        <div class="mb-3">
                            <label for="collection_name" class="form-label">Collection Name *</label>
                            <input type="text" class="form-control" id="collection_name" name="collection_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="collection_description" class="form-label">Description</label>
                            <textarea class="form-control" id="collection_description" name="collection_description" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="collection_logo" class="form-label">Collection Logo *</label>
                            <input type="file" class="form-control" id="collection_logo" name="collection_logo" accept="image/*" required>
                            <div class="form-text">This will be displayed as the main image for your collection.</div>
                            <div id="logoPreview" class="mt-2"></div>
                        </div>
                    </div>
                </div>
                
                <div class="mb-4">
                    <h4>Ownership</h4>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="creator_id" class="form-label">Creator *</label>
                            <select class="form-select" id="creator_id" name="creator_id" required>
                                <option value="">Select Creator</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['user_id']; ?>">
                                        <?php echo htmlspecialchars($user['username']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="owner_id" class="form-label">Owner *</label>
                            <select class="form-select" id="owner_id" name="owner_id" required>
                                <option value="">Select Owner</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['user_id']; ?>">
                                        <?php echo htmlspecialchars($user['username']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Leave blank to set as creator</div>
                        </div>
                    </div>
                </div>
                
                <div class="mb-4">
                    <h4>NFT Items</h4>
                    <div class="mb-3">
                        <label for="nft_images" class="form-label">NFT Images (Max 120 files, 500MB total) *</label>
                        <div class="input-group">
                            <input type="file" class="form-control" id="nft_images" name="nft_images[]" multiple accept="image/*" style="display: none;">
                            <button type="button" class="btn btn-primary" onclick="document.getElementById('nft_images').click()">
                                <i class="material-icons" style="font-size: 1rem; vertical-align: middle; margin-right: 4px;">upload</i>
                                Choose Files
                            </button>
                            <span class="input-group-text" id="fileCountDisplay">No files selected</span>
                        </div>
                        <div id="fileInfo" class="form-text">Select one or more images to upload</div>
                        <button type="button" id="addMoreNfts" class="btn btn-outline-secondary btn-sm mt-2">
                            <i class="material-icons" style="font-size: 1rem; vertical-align: middle; margin-right: 4px;">add</i>
                            Add More Files
                        </button>
                        <div class="progress mt-2 d-none" id="uploadProgress">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                        </div>
                    </div>
                    
                    <div id="nftPreviews" class="nft-preview-container">
                        <!-- NFT previews will be added here -->
                    </div>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg">Create Collection</button>
                </div>
            </form>
            
            <div class="mt-3 text-center" style="display: flex; justify-content: center; gap: 20px;">
                <a href="dashboard.php" class="btn btn-outline-primary">← Back to Dashboard</a>
                <a href="marketplace/profile.php" class="btn btn-outline-secondary">← Back to Profile</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Preview collection logo
        document.getElementById('collection_logo').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('logoPreview');
                    preview.innerHTML = `<img src="${e.target.result}" class="img-thumbnail" style="max-width: 200px; max-height: 200px;">`;
                }
                reader.readAsDataURL(file);
            }
        });

        // Handle NFT image uploads and previews
        const nftPreviews = document.getElementById('nftPreviews');
        const nftImagesInput = document.getElementById('nft_images');
        const addMoreButton = document.getElementById('addMoreNfts');
        
        // Store all uploaded files
        let uploadedFiles = [];
        let fileInputs = [nftImagesInput];
        
        // Function to create NFT preview
        function createNftPreview(file, fileIndex) {
            return new Promise((resolve) => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const nftId = 'nft-' + Date.now() + '-' + Math.floor(Math.random() * 1000);
                    const nftItem = document.createElement('div');
                    nftItem.className = 'nft-item col-md-3 col-6';
                    nftItem.dataset.nftId = nftId;
                    nftItem.dataset.fileIndex = fileIndex;
                    nftItem.innerHTML = `
                        <div class="card h-100">
                            <button type="button" class="remove-nft" data-nft-id="${nftId}" aria-label="Remove NFT">
                                &times;
                            </button>
                            <img src="${e.target.result}" class="card-img-top" alt="NFT Preview">
                            <div class="card-body p-2">
                                <label class="form-label small">Price (LUCK):</label>
                                <input type="number" class="form-control form-control-sm price-input" 
                                       name="nft_prices[]" min="0" step="0.01" required>
                                <input type="hidden" name="nft_file_indexes[]" value="${fileIndex}">
                            </div>
                        </div>
                    `;
                    resolve(nftItem);
                };
                reader.readAsDataURL(file);
            });
        }
        
        // Update file info display
        function updateFileInfo() {
            const validFiles = uploadedFiles.filter(f => f !== null);
            const totalFiles = validFiles.length;
            const totalSize = validFiles.reduce((sum, file) => sum + file.file.size, 0);
            
            // Update file count display
            const fileCountDisplay = document.getElementById('fileCountDisplay');
            if (totalFiles === 0) {
                fileCountDisplay.textContent = 'No files selected';
                fileCountDisplay.className = 'input-group-text';
            } else {
                const sizeMB = (totalSize / (1024 * 1024)).toFixed(2);
                fileCountDisplay.textContent = `${totalFiles} file${totalFiles !== 1 ? 's' : ''} selected (${sizeMB} MB)`;
                fileCountDisplay.className = 'input-group-text bg-light';
            }
            
            // Update detailed file info
            const fileInfo = document.getElementById('fileInfo');
            if (totalFiles > 0) {
                const sizeMB = (totalSize / (1024 * 1024)).toFixed(2);
                fileInfo.textContent = `${totalFiles} file${totalFiles !== 1 ? 's' : ''} selected (${sizeMB} MB) • Click on × to remove`;
                
                // Show warning if approaching limits
                if (totalFiles > 100) {
                    fileInfo.classList.add('text-warning');
                    fileInfo.innerHTML += ' <i class="material-icons" style="font-size: 1rem; vertical-align: middle;">warning</i> Close to maximum files limit';
                } else {
                    fileInfo.classList.remove('text-warning');
                }
                
                // Show warning if approaching size limit
                if (totalSize > 400 * 1024 * 1024) { // 400MB
                    fileInfo.classList.add('text-danger');
                    fileInfo.innerHTML += ' <i class="material-icons" style="font-size: 1rem; vertical-align: middle;">error</i> Close to maximum size limit';
                } else {
                    fileInfo.classList.remove('text-danger');
                }
            } else {
                fileInfo.textContent = 'Select one or more images to upload';
                fileInfo.className = 'form-text';
            }
        }

        // Handle file selection
        function handleFileSelection(files, inputIndex) {
            const progressBar = document.getElementById('uploadProgress');
            const progressBarInner = progressBar.querySelector('.progress-bar');
            const totalFiles = files.length;
            let processed = 0;
            
            progressBar.classList.remove('d-none');
            
            const processNextFile = (index) => {
                if (index >= files.length) {
                    // All files processed
                    progressBar.classList.add('d-none');
                    progressBarInner.style.width = '0%';
                    progressBarInner.removeAttribute('aria-valuenow');
                    updateFileInfo();
                    return;
                }
                
                const file = files[index];
                
                // Update progress
                processed++;
                const progress = Math.round((processed / totalFiles) * 100);
                progressBarInner.style.width = `${progress}%`;
                progressBarInner.setAttribute('aria-valuenow', progress);
                progressBarInner.textContent = `Processing ${processed} of ${totalFiles}...`;
                
                if (!file.type.startsWith('image/')) {
                    processNextFile(index + 1);
                    return;
                }
                
                // Add file to our tracking
                const fileIndex = uploadedFiles.length;
                uploadedFiles.push({
                    file: file,
                    inputIndex: inputIndex,
                    previewId: 'nft-' + fileIndex
                });
                
                // Create and append preview
                createNftPreview(file, fileIndex).then(nftItem => {
                    nftPreviews.appendChild(nftItem);
                    processNextFile(index + 1);
                });
            };
            
            // Start processing files
            processNextFile(0);
            
            // Update the file info immediately
            updateFileInfo();
        }
        
        // Handle initial file input change
        nftImagesInput.addEventListener('change', function(e) {
            if (e.target.files.length > 0) {
                handleFileSelection(Array.from(e.target.files), 0);
                // Clear the input value to allow selecting the same file again if needed
                this.value = '';
            }
        });
        
        // Add more files button - triggers the hidden file input
        addMoreButton.addEventListener('click', function() {
            document.getElementById('nft_images').click();
        });
        
        // Also handle the main file input
        nftImagesInput.addEventListener('change', function(e) {
            if (e.target.files.length > 0) {
                handleFileSelection(Array.from(e.target.files), 0);
                // Clear the input to allow selecting the same files again if needed
                this.value = '';
            }
        });
        
        // Handle NFT removal
        document.addEventListener('click', function(e) {
            if (e.target.closest('.remove-nft')) {
                e.preventDefault();
                const nftId = e.target.closest('.remove-nft').dataset.nftId;
                const nftItem = document.querySelector(`.nft-item[data-nft-id="${nftId}"]`);
                const fileIndex = parseInt(nftItem.dataset.fileIndex);
                
                // Remove from DOM
                nftItem.remove();
                
                // Remove from our tracking
                uploadedFiles[fileIndex] = null;
                
                // Update file info
                updateFileInfo();
                
                // No need to clear the input value as we're tracking files ourselves
            }
        });
        
        // Update form submission to include our tracked files
        document.querySelector('form').addEventListener('submit', function(e) {
            // Check if we have any files to upload
            const validFiles = uploadedFiles.filter(file => file !== null);
            if (validFiles.length === 0) {
                e.preventDefault();
                alert('Please select at least one NFT image to upload.');
                return;
            }
            
            // Show loading state
            const submitButton = this.querySelector('button[type="submit"]');
            const originalButtonText = submitButton.innerHTML;
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Uploading...';
            
            // Create a new FormData object
            const formData = new FormData(this);
            
            // Remove the original file input
            formData.delete('nft_images[]');
            
            // Add each file to the form data
            validFiles.forEach((fileData, index) => {
                if (fileData && fileData.file) {
                    formData.append(`nft_images[${index}]`, fileData.file);
                }
            });
            
            // Submit the form with the updated form data
            e.preventDefault();
            
            // Submit using fetch instead of form submission
            fetch(this.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                credentials: 'same-origin'
            })
            .then(async response => {
                const text = await response.text();
                try {
                    return text ? JSON.parse(text) : {};
                } catch (e) {
                    console.error('Failed to parse JSON response:', text);
                    throw new Error('Invalid server response. Please try again.');
                }
            })
            .then(data => {
                if (data.success) {
                    window.location.href = data.redirect || 'dashboard.php';
                } else {
                    throw new Error(data.message || 'An error occurred while uploading the files.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert(error.message || 'An error occurred while uploading the files. Please check the console for details.');
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonText;
                
                // Re-enable the form
                const form = document.getElementById('collectionForm');
                if (form) {
                    form.querySelectorAll('button, input, select, textarea').forEach(el => {
                        el.disabled = false;
                    });
                }
            });
        });

        // Toggle between new and existing collection fields
        const toggleCollectionFields = (isNewCollection) => {
            const newCollectionFields = document.getElementById('newCollectionFields');
            const existingCollectionContainer = document.getElementById('existingCollectionContainer');
            
            console.log('Toggling collection fields:', { isNewCollection, newCollectionFields, existingCollectionContainer });
            
            if (newCollectionFields) {
                newCollectionFields.style.display = isNewCollection ? 'block' : 'none';
            }
            
            if (existingCollectionContainer) {
                existingCollectionContainer.style.display = !isNewCollection ? 'block' : 'none';
            }
            
            // Toggle required attribute on new collection fields
            if (newCollectionFields) {
                newCollectionFields.querySelectorAll('[required]').forEach(field => {
                    field.required = isNewCollection;
                });
            }
            
            const logoField = document.getElementById('collection_logo');
            if (logoField) {
                logoField.required = isNewCollection;
            }
        };
        
        // Initialize based on current selection
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM fully loaded');
            
            const initialOption = document.querySelector('input[name="collection_option"]:checked');
            const hasCollectionId = new URLSearchParams(window.location.search).has('collection_id');
            
            console.log('Initial state:', { initialOption, hasCollectionId });
            
            if (initialOption) {
                toggleCollectionFields(initialOption.value === 'new');
            } else if (hasCollectionId) {
                // If we're in add-to-existing mode (via URL parameter)
                console.log('In add-to-existing mode via URL parameter');
                toggleCollectionFields(false);
            } else if (document.querySelector('input[name="collection_option"][value="existing"]')) {
                // If we're in add-to-existing mode (no radio buttons)
                console.log('In add-to-existing mode (no radio buttons)');
                toggleCollectionFields(false);
            } else {
                // Default to showing new collection fields
                console.log('Defaulting to new collection fields');
                toggleCollectionFields(true);
            }
            
            // Add change listeners for radio buttons if they exist
            document.querySelectorAll('input[name="collection_option"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    console.log('Collection option changed:', this.value);
                    toggleCollectionFields(this.value === 'new');
                });
            });
        });

        // Copy creator to owner when creator changes
        document.getElementById('creator_id').addEventListener('change', function() {
            const ownerSelect = document.getElementById('owner_id');
            if (!ownerSelect.value) {
                ownerSelect.value = this.value;
            }
        });
    </script>
</body>
</html>
