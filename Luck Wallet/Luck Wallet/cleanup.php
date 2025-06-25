<?php
// List of files to be removed - carefully reviewed as safe to remove
$filesToRemove = [
    // Debug and log files
    'debug.log',
    'debug_nfts.php',
    'error_log.txt',
    'php_errors.log',
    'php/php_errors.log',
    'php/error_log',
    
    // Test files
    'test_auth.php',
    'test_daily_bonus.php',
    'test_db.php',
    'php/test_connection.php',
    'php/test_db.php',
    'php/debug_helper.php',
    'php/check_db_structure.php',
    'php/check_transactions.php',
    
    // Check scripts (one-time use)
    'check_luckytime.php',
    'check_nft_tables.php',
    'check_nfts.php',
    'check_schema.php',
    'check_tables.php',
    'fix_nft_ownership.php',
    
    // Duplicate/backup files
    'copy copy.html',
    'copy.html',
];

$removed = [];
$errors = [];

// Function to safely remove files
function removeFile($file) {
    global $baseDir;
    $path = $baseDir . '/' . $file;
    
    if (file_exists($path)) {
        if (is_dir($path)) {
            return rmdir($path);
        } else {
            return unlink($path);
        }
    }
    return false;
}

// Set base directory
$baseDir = __DIR__;

// Process file removal
echo "Starting cleanup...\n\n";

foreach ($filesToRemove as $file) {
    $filePath = $baseDir . '/' . $file;
    
    if (file_exists($filePath)) {
        if (is_writable($filePath)) {
            if (removeFile($file)) {
                $removed[] = $file;
                echo "[REMOVED] $file\n";
            } else {
                $errors[] = "Failed to remove: $file";
                echo "[ERROR] Failed to remove: $file\n";
            }
        } else {
            $errors[] = "Permission denied: $file";
            echo "[ERROR] Permission denied: $file\n";
        }
    } else {
        echo "[NOT FOUND] $file\n";
    }
}

// Summary
echo "\nCleanup complete!\n";
echo "Removed " . count($removed) . " files.\n";

if (!empty($errors)) {
    echo "\nThere were some errors:\n";
    foreach ($errors as $error) {
        echo "- $error\n";
    }
}

// Create .gitignore if it doesn't exist
$gitignore = $baseDir . '/.gitignore';
if (!file_exists($gitignore)) {
    $gitignoreContent = "# Logs and databases
*.log
*.sql
*.sqlite

# Environment files
.env
.env.*
!.env.example

# Dependency directories
/vendor/
/node_modules/

# IDE specific files
.idea/
.vscode/
*.swp
*.swo

# System Files
.DS_Store
Thumbs.db
error_log
php_errors.log

# Local development files
*.local.php
*.local.*

# Backup files
*.bak
*.backup
*.swp
*~

# Debug files
debug.log
debug_*.php
check_*.php
fix_*.php
test_*.php
php/test_*.php
php/debug_*.php
php/check_*.php

# OS generated files
.DS_Store
.DS_Store?
._*
.Spotlight-V100
.Trashes
ehthumbs.db
Thumbs.db

# User uploaded content (adjust as needed)
uploads/*
!uploads/.gitkeep
";
    
    if (file_put_contents($gitignore, $gitignoreContent)) {
        echo "\nCreated .gitignore file to prevent tracking of unnecessary files.\n";
    } else {
        echo "\nFailed to create .gitignore file.\n";
    }
}

echo "\nNote: Please verify your application still works as expected after cleanup.\n";
?>
