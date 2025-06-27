<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't show errors to users
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/purchase_errors.log');

header('Content-Type: application/json');

// Function to send JSON error response
function sendErrorResponse($message, $statusCode = 400) {
    http_response_code($statusCode);
    echo json_encode(['success' => false, 'message' => $message]);
    error_log("Error Response ($statusCode): $message");
    exit;
}

// Log the request
error_log("Purchase request: " . print_r($_POST, true));
error_log("Raw input: " . file_get_contents('php://input'));

try {
    // Start session and check if user is logged in
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        sendErrorResponse('You must be logged in to make a purchase', 401);
    }

    // Get the raw POST data
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    // Validate JSON data
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendErrorResponse('Invalid JSON data: ' . json_last_error_msg());
    }

    // Validate required fields
    if (!isset($data['nftId'], $data['price'])) {
        sendErrorResponse('Missing required fields. Required: nftId, price');
    }

    $nftId = filter_var($data['nftId'], FILTER_VALIDATE_INT);
    $price = filter_var($data['price'], FILTER_VALIDATE_FLOAT);
    $buyerId = (int)$_SESSION['user_id'];

    // Validate inputs
    if ($nftId === false || $nftId <= 0) {
        sendErrorResponse('Invalid NFT ID');
    }
    
    if ($price === false || $price <= 0) {
        sendErrorResponse('Invalid price');
    }
} catch (Exception $e) {
    error_log("Initialization error: " . $e->getMessage());
    sendErrorResponse('An error occurred while processing your request', 500);
}

// Include the database connection file from the root directory
require_once __DIR__ . '/../../db_connect.php';


try {
    // Check if $pdo is set and is a PDO instance
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        error_log('Database connection not established: $pdo is ' . gettype($pdo));
        throw new Exception('Database connection not established');
    }

    // Set PDO to throw exceptions on error
    try {
        // Use string constants in case PDO constants aren't defined
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        // Test the connection
        $pdo->query('SELECT 1');
    } catch (PDOException $e) {
        error_log('Database connection test failed: ' . $e->getMessage());
        throw new Exception('Database connection failed: ' . $e->getMessage());
    }

    // Start transaction
    $pdo->beginTransaction();
    error_log("Transaction started");

    try {
        // 1. Get NFT details with FOR UPDATE to lock the row
        try {
            $query = "SELECT nft_id as id, name, description, image_url, price_luck as price, current_owner_user_id as owner_id, 
                     listed_for_sale as for_sale, listing_date as created_at, creator_user_id 
                     FROM luck_wallet_nfts WHERE nft_id = ? FOR UPDATE";
            error_log("Executing NFT query: $query with ID: $nftId");
            
            $stmt = $pdo->prepare($query);
            $stmt->execute([$nftId]);
            $nft = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$nft) {
                error_log("NFT not found with ID: $nftId");
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                sendErrorResponse('The NFT you are trying to purchase does not exist or has been removed.', 404);
                exit;
            }
            
            // Map the column names to match the expected format
            $nft['price'] = $nft['price'];
            $nft['owner_id'] = $nft['owner_id'];
            $nft['for_sale'] = $nft['for_sale'];
            
            error_log("Found NFT: " . json_encode($nft));
            
        } catch (PDOException $e) {
            error_log("Error in NFT query: " . $e->getMessage());
            throw new Exception('Error fetching NFT details');
        }

        // Verify NFT is for sale and has a valid owner
        if (!$nft['for_sale']) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            sendErrorResponse('This NFT is not available for sale.', 400);
            exit;
        }
        
        if (empty($nft['owner_id'])) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            sendErrorResponse('Invalid NFT owner information.', 400);
            exit;
        }

        // 2. Get buyer's balance with FOR UPDATE to lock the row
        try {
            // First try luck_wallet_users
            $query = "SELECT user_id, luck_balance as balance FROM luck_wallet_users WHERE user_id = ? FOR UPDATE";
            error_log("Checking luck_wallet_users for buyer ID: $buyerId");
            
            $stmt = $pdo->prepare($query);
            if (!$stmt->execute([$buyerId])) {
                throw new PDOException("Failed to execute buyer balance query: " . implode(", ", $stmt->errorInfo()));
            }
            $buyer = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log("Buyer balance query result: " . json_encode($buyer));
            $balanceTable = 'luck_wallet_users';
            
            // If not found in luck_wallet_users, try users table
            if (!$buyer) {
                error_log("Buyer not found in luck_wallet_users, checking users table");
                $query = "SELECT id as user_id, balance FROM users WHERE id = ? FOR UPDATE";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$buyerId]);
                $buyer = $stmt->fetch(PDO::FETCH_ASSOC);
                $balanceTable = 'users';
            }
            
            if ($buyer) {
                error_log("Found buyer in $balanceTable: " . json_encode($buyer));
            } else {
                error_log("Buyer not found in any table with ID: $buyerId");
            }
            
        } catch (PDOException $e) {
            error_log("Error fetching buyer balance: " . $e->getMessage());
            throw new Exception('Error verifying your account balance');
        }

        if (!$buyer) {
            throw new Exception('Your account was not found. Please contact support.');
        }

        // 3. Verify buyer has enough balance
        if ($buyer['balance'] < $price) {
            error_log("Insufficient balance. Buyer has: {$buyer['balance']}, needs: $price");
            throw new Exception('Insufficient balance to complete this purchase');
        }
        
        // Log buyer's current balance before deduction
        error_log("Buyer current balance: {$buyer['balance']}, Price: $price");

        // 4. Get seller's ID and verify ownership
        $sellerId = $nft['owner_id'] ?? null;
        if (!$sellerId) {
            error_log("Invalid NFT data - missing owner_id: " . json_encode($nft));
            throw new Exception('Invalid NFT data. Please try again or contact support.');
        }
        
        error_log("NFT owner ID: $sellerId, Buyer ID: $buyerId");
        
        if ($sellerId == $buyerId) {
            error_log("Buyer $buyerId attempted to buy their own NFT $nftId");
            throw new Exception('You already own this NFT');
        }

        // 5. Deduct price from buyer's balance
        try {
            if ($balanceTable === 'luck_wallet_users') {
                $updateBuyerStmt = $pdo->prepare("UPDATE luck_wallet_users SET luck_balance = luck_balance - ? WHERE user_id = ?");
            } else {
                $updateBuyerStmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
            }
            
            error_log("Updating $balanceTable balance for user $buyerId, deducting $price");
            if (!$updateBuyerStmt->execute([$price, $buyerId])) {
                throw new PDOException("Failed to update buyer balance: " . implode(", ", $updateBuyerStmt->errorInfo()));
            }
            
            // Verify the update was successful
            $checkStmt = $pdo->prepare("SELECT luck_balance FROM luck_wallet_users WHERE user_id = ?");
            $checkStmt->execute([$buyerId]);
            $newBalance = $checkStmt->fetch(PDO::FETCH_COLUMN);
            error_log("Buyer's new balance after deduction: $newBalance");
            
            if ($updateBuyerStmt->rowCount() === 0) {
                throw new Exception('Failed to update buyer balance');
            }
            
            error_log("Successfully updated $balanceTable balance for user $buyerId");
            
        } catch (PDOException $e) {
            error_log("Error updating buyer balance: " . $e->getMessage());
            throw new Exception('Error processing your payment. Please try again.');
        }

        // 6. Add price to seller's balance
        try {
            error_log("Updating seller's ($sellerId) balance, adding $price");
            
            // Check if seller exists in luck_wallet_users
            $checkSellerStmt = $pdo->prepare("SELECT 1 FROM luck_wallet_users WHERE user_id = ? FOR UPDATE");
            $checkSellerStmt->execute([$sellerId]);
            $sellerInWallet = $checkSellerStmt->fetch();
            
            if ($sellerInWallet) {
                $updateSellerStmt = $pdo->prepare("UPDATE luck_wallet_users SET luck_balance = luck_balance + ? WHERE user_id = ?");
            } else {
                $updateSellerStmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            }
            
            // Log before update
            $checkBeforeStmt = $pdo->prepare("SELECT luck_balance as balance FROM " . 
                ($sellerInWallet ? 'luck_wallet_users' : 'users') . " WHERE user_id = ?");
            $checkBeforeStmt->execute([$sellerId]);
            $beforeBalance = $checkBeforeStmt->fetch(PDO::FETCH_ASSOC);
            error_log("Seller balance before: " . json_encode($beforeBalance));
            
            if (!$updateSellerStmt->execute([$price, $sellerId])) {
                throw new PDOException("Failed to update seller balance: " . implode(", ", $updateSellerStmt->errorInfo()));
            }
            
            // Verify the update was successful
            $checkAfterStmt = $pdo->prepare("SELECT luck_balance as balance FROM " . 
                ($sellerInWallet ? 'luck_wallet_users' : 'users') . " WHERE user_id = ?");
            $checkAfterStmt->execute([$sellerId]);
            $afterBalance = $checkAfterStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($afterBalance === false) {
                throw new Exception('Failed to verify seller balance update');
            }
            
            error_log("Seller balance after: " . json_encode($afterBalance));
            
            error_log("Successfully updated seller's balance");
            
        } catch (PDOException $e) {
            error_log("Error updating seller balance: " . $e->getMessage());
            throw new Exception('Error processing seller payment. Please contact support.');
        }

        // 7. Transfer NFT ownership and mark as not for sale
        try {
            error_log("Transferring NFT $nftId from seller $sellerId to buyer $buyerId");
            
            // Get current NFT state for logging
            $nftCheckStmt = $pdo->prepare("SELECT current_owner_user_id as owner_id, listed_for_sale as for_sale FROM luck_wallet_nfts WHERE nft_id = ? FOR UPDATE");
            $nftCheckStmt->execute([$nftId]);
            $currentNftState = $nftCheckStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$currentNftState) {
                throw new Exception('NFT not found during ownership transfer');
            }
            
            error_log("Current NFT state: " . json_encode($currentNftState));
            
            // Update NFT ownership
            $updateNftStmt = $pdo->prepare("UPDATE luck_wallet_nfts SET current_owner_user_id = ?, listed_for_sale = 0, listing_date = NOW() WHERE nft_id = ? AND current_owner_user_id = ?");
            $updateNftResult = $updateNftStmt->execute([$buyerId, $nftId, $sellerId]);
            $rowsAffected = $updateNftStmt->rowCount();
            
            error_log("NFT update result: " . ($updateNftResult ? 'success' : 'failed') . ", rows affected: $rowsAffected");
            
            if (!$updateNftResult || $rowsAffected === 0) {
                // Check current owner
                $ownerCheckStmt = $pdo->prepare("SELECT current_owner_user_id FROM luck_wallet_nfts WHERE nft_id = ?");
                $ownerCheckStmt->execute([$nftId]);
                $currentOwner = $ownerCheckStmt->fetch(PDO::FETCH_COLUMN);
                
                if ($currentOwner == $buyerId) {
                    error_log("NFT already owned by buyer, but continuing...");
                } else if ($currentOwner != $sellerId) {
                    throw new Exception('NFT ownership has changed. Please refresh and try again.');
                } else {
                    throw new Exception('Failed to transfer NFT ownership. Please try again.');
                }
            }
            
            error_log("Successfully transferred NFT ownership");
            
        } catch (PDOException $e) {
            error_log("Error transferring NFT ownership: " . $e->getMessage());
            throw new Exception('Error transferring NFT ownership. Please contact support.');
        }

        // 8. Record the transaction
        try {
            // Get the next auto-increment ID for the transaction
            $stmt = $pdo->query("SHOW TABLE STATUS LIKE 'luck_wallet_transactions'");
            $status = $stmt->fetch(PDO::FETCH_ASSOC);
            $transactionId = $status['Auto_increment'];
            
            $transactionType = 'nft_purchase';
            $notes = "Purchase of NFT ID: $nftId";
            
            // Insert into luck_wallet_transactions with correct column names
            $stmt = $pdo->prepare("INSERT INTO luck_wallet_transactions 
                (sender_user_id, receiver_user_id, amount, transaction_type, notes) 
                VALUES (?, ?, ?, ?, ?)");
            
            // For NFT purchase, seller is the sender (money goes from buyer to seller)
            // But in our case, we're recording the LUCK token transfer from buyer to seller
            $transactionRecorded = $stmt->execute([
                $buyerId,   // sender_user_id (buyer pays)
                $sellerId,  // receiver_user_id (seller receives)
                $price,     // amount
                $transactionType,
                $notes
            ]);
            
            // Get the auto-generated transaction ID
            $transactionId = $pdo->lastInsertId();
            
            if (!$transactionRecorded) {
                throw new Exception('Failed to record transaction');
            }

            // Commit transaction if everything is successful
            $pdo->commit();

            // Log successful purchase (in a real app, you'd use a proper logging system)
            error_log("Purchase successful - NFT: $nftId, Buyer: $buyerId, Seller: $sellerId, Amount: $price");

            // Prepare success response
            $response = [
                'success' => true,
                'message' => 'Purchase completed successfully!',
                'transactionId' => $transactionId,
                'nftId' => $nftId,
                'newOwnerId' => $buyerId
            ];
            
            echo json_encode($response);
            exit;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Transaction recording failed: " . $e->getMessage());
            sendErrorResponse('Failed to complete the transaction. Please try again or contact support.', 500);
        }
        
    } catch (PDOException $e) {
        // Database specific errors
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorInfo = $e->errorInfo ?? [];
        $errorDetails = [
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'sqlstate' => $errorInfo[0] ?? '',
            'driver_code' => $errorInfo[1] ?? '',
            'driver_message' => $errorInfo[2] ?? ''
        ];
        error_log('Database error during purchase: ' . json_encode($errorDetails));
        
        // For debugging - in production, you might want to be more generic
        $errorMessage = 'A database error occurred. ';
        if (strpos($e->getMessage(), 'SQLSTATE') !== false) {
            $errorMessage = 'Database query error. ';
        }
        $errorMessage .= 'Please try again or contact support if the problem persists.';
        
        sendErrorResponse($errorMessage, 500);
        
    } catch (Exception $e) {
        // Business logic errors
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Purchase error: " . $e->getMessage());
        sendErrorResponse($e->getMessage(), 400);
    }
    
} catch (Exception $e) {
    // Catch any unexpected errors
    error_log("Unexpected error in purchase_nft.php: " . $e->getMessage());
    sendErrorResponse('An unexpected error occurred. Please try again later.', 500);
}

// If we reach here, something went wrong
if (!isset($response)) {
    sendErrorResponse('An unexpected error occurred. Please try again later.', 500);
} else {
    // Ensure we're sending valid JSON
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?>
