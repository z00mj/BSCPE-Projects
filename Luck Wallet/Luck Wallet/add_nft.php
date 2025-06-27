<?php
// Start session and include database connection
session_start();
require_once 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get PDO instance
try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=login;charset=utf8mb4", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Create uploads directory if it doesn't exist
$upload_dir = 'uploads/nfts/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$message = '';
$error = '';
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$max_file_size = 5 * 1024 * 1024; // 5MB

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price_luck = floatval($_POST['price_luck'] ?? 0);
    $owner_id = intval($_POST['owner_id'] ?? $_SESSION['user_id']);
    $creator_id = $_SESSION['user_id'];
    $image_url = '';
    
    // Handle file upload
    if (isset($_FILES['nft_image']) && $_FILES['nft_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['nft_image'];
        $file_type = mime_content_type($file['tmp_name']);
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $new_filename = uniqid('nft_', true) . '.' . $file_ext;
        $target_path = $upload_dir . $new_filename;
        
        // Validate file type
        if (!in_array($file_type, $allowed_types)) {
            $error = 'Only JPG, PNG, GIF, and WebP images are allowed.';
        }
        // Validate file size
        elseif ($file['size'] > $max_file_size) {
            $error = 'File size must be less than 5MB.';
        }
        // Move uploaded file
        elseif (move_uploaded_file($file['tmp_name'], $target_path)) {
            $image_url = $target_path;
        } else {
            $error = 'Error uploading file. Please try again.';
        }
    } else {
        $error = 'Please select an image file to upload. ';
        if (isset($_FILES['nft_image']['error'])) {
            switch ($_FILES['nft_image']['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $error .= 'File is too large.';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $error .= 'File was only partially uploaded.';
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $error .= 'No file was uploaded.';
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $error .= 'Missing temporary folder.';
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $error .= 'Failed to write file to disk.';
                    break;
                case UPLOAD_ERR_EXTENSION:
                    $error .= 'A PHP extension stopped the file upload.';
                    break;
                default:
                    $error .= 'Unknown upload error.';
            }
        } else {
            $error .= 'No file was selected.';
        }
    }
    
    // If no errors, proceed with database insertion
    if (empty($error)) {
        if (empty($name) || $price_luck <= 0) {
            $error = 'Please fill in all required fields with valid data.';
        } else {
            try {
                // Verify owner exists
                $check_user = $pdo->prepare("SELECT user_id FROM luck_wallet_users WHERE user_id = ?");
                $check_user->execute([$owner_id]);
                
                if ($check_user->rowCount() === 0) {
                    $error = 'Invalid owner user ID. Please select a valid user.';
                } else {
                    // Insert the NFT
                    $stmt = $pdo->prepare("INSERT INTO luck_wallet_nfts 
                                         (name, description, image_url, price_luck, creator_user_id, current_owner_user_id, listed_for_sale, listing_date) 
                                         VALUES (?, ?, ?, ?, ?, ?, 1, NOW())");
                    
                    if ($stmt->execute([$name, $description, $image_url, $price_luck, $creator_id, $owner_id])) {
                        $_SESSION['success_message'] = 'NFT added successfully!';
                        header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
                        exit();
                    } else {
                        $error = 'Failed to insert NFT into database';
                    }
                }
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
                error_log("PDO Error: " . $e->getMessage());
            }
        }
    }
}

// Check for success message from redirect
if (isset($_GET['success']) && $_GET['success'] == 1 && isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New NFT - Luck Wallet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container {
            max-width: 600px;
            margin-top: 50px;
        }
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .form-title {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
        }
        .btn-submit {
            background-color: #6f42c1;
            border: none;
            padding: 10px 20px;
            width: 100%;
        }
        .btn-submit:hover {
            background-color: #5a32a3;
        }
        .nft-preview {
            max-width: 100%;
            max-height: 200px;
            margin: 15px 0;
            display: none;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <h2 class="form-title">Add New NFT</h2>
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" enctype="multipart/form-data" onsubmit="return validateForm()">
                <div class="mb-3">
                    <label for="name" class="form-label">NFT Name *</label>
                    <input type="text" class="form-control" id="name" name="name" required 
                           value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                </div>
                
                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?php 
                        echo htmlspecialchars($_POST['description'] ?? ''); 
                    ?></textarea>
                </div>
                
                <div class="mb-3">
                    <label for="nft_image" class="form-label">NFT Image *</label>
                    <input type="file" class="form-control" id="nft_image" name="nft_image" 
                           accept="image/jpeg,image/png,image/gif,image/webp" required>
                    <div class="form-text">Max file size: 5MB. Allowed formats: JPG, PNG, GIF, WebP</div>
                    <img id="image_preview" class="nft-preview" src="#" alt="Image preview" style="display: none; max-width: 100%; margin-top: 10px;">
                </div>
                
                <div class="mb-3">
                    <label for="price_luck" class="form-label">Price (LUCK) *</label>
                    <div class="input-group">
                        <input type="number" step="0.01" min="0.01" class="form-control" id="price_luck" 
                               name="price_luck" required value="<?php echo htmlspecialchars($_POST['price_luck'] ?? '1.00'); ?>">
                        <span class="input-group-text">LUCK</span>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Ownership Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="owner_id" class="form-label">Owner User ID *</label>
                                <input type="number" class="form-control" id="owner_id" name="owner_id" required 
                                       value="<?php echo htmlspecialchars($_SESSION['user_id']); ?>">
                                <div class="form-text">Enter the user ID who will own this NFT</div>
                            </div>
                            <?php
                            // Get current user's information for reference
                            $user_id = $_SESSION['user_id'];
                            $user_stmt = $pdo->prepare("SELECT username, wallet_address FROM luck_wallet_users WHERE user_id = ?");
                            $user_stmt->execute([$user_id]);
                            $user = $user_stmt->fetch();
                            
                            // Get all users for reference
                            $users_stmt = $pdo->query("SELECT user_id, username, wallet_address FROM luck_wallet_users ORDER BY username");
                            $all_users = $users_stmt->fetchAll();
                            ?>
                            <div class="mb-3">
                                <label class="form-label">Available Users</label>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Username</th>
                                                <th>Wallet Address</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($all_users as $u): ?>
                                            <tr style="cursor: pointer;" onclick="document.getElementById('owner_id').value = '<?php echo $u['user_id']; ?>'">
                                                <td><?php echo htmlspecialchars($u['user_id']); ?></td>
                                                <td><?php echo htmlspecialchars($u['username']); ?></td>
                                                <td><small class="text-muted"><?php echo htmlspecialchars($u['wallet_address']); ?></small></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-submit">Add NFT</button>
            </form>
            
            <div class="mt-3 text-center">
                <a href="dashboard.php" class="text-decoration-none">← Back to Dashboard</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function validateForm() {
            const nftName = document.getElementById('name').value.trim();
            const nftPrice = document.getElementById('price_luck').value;
            const nftImage = document.getElementById('nft_image').value;
            
            if (!nftName) {
                alert('Please enter an NFT name');
                return false;
            }
            
            if (!nftPrice || parseFloat(nftPrice) <= 0) {
                alert('Please enter a valid price');
                return false;
            }
            
            if (!nftImage) {
                alert('Please select an image');
                return false;
            }
            
            return true;
        }
        
        // Debug: Log form submission
        document.querySelector('form').addEventListener('submit', function(e) {
            console.log('Form submitted');
            console.log('Form data:', new FormData(this));
        });
        
        // Debug: Show any PHP errors
        window.onerror = function(msg, url, lineNo, columnNo, error) {
            console.error('Error:', msg, 'at', url, 'line', lineNo);
            return false;
        };
        // Preview image when file is selected
        document.getElementById('nft_image').addEventListener('change', function(e) {
            const preview = document.getElementById('image_preview');
            const file = e.target.files[0];
            
            if (file) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                
                reader.readAsDataURL(file);
            } else {
                preview.style.display = 'none';
            }
        });
        
        // Clear success message after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const successMessage = document.querySelector('.alert-success');
            if (successMessage) {
                setTimeout(function() {
                    successMessage.style.transition = 'opacity 1s';
                    successMessage.style.opacity = '0';
                    setTimeout(() => successMessage.remove(), 1000);
                }, 5000);
            }
        });
    </script>
</body>
</html>
