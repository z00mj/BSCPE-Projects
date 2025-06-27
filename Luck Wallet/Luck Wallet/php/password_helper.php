<?php
/**
 * Password helper functions
 */

/**
 * Verify a password against a hash (supports both MD5 and password_hash formats)
 * 
 * @param string $password The password to verify
 * @param string $hash The stored hash to verify against
 * @return bool Returns true if the password matches, false otherwise
 */
function verifyPassword($password, $hash) {
    // Check if the hash is MD5 (32 characters hexadecimal)
    if (preg_match('/^[a-f0-9]{32}$/i', $hash)) {
        return md5($password) === $hash;
    }
    
    // Otherwise, assume it's a password_hash hash
    return password_verify($password, $hash);
}

/**
 * Hash a password using the current recommended algorithm
 * 
 * @param string $password The password to hash
 * @return string The hashed password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Check if a password needs to be rehashed
 * 
 * @param string $hash The hash to check
 * @return bool True if password needs rehashing, false otherwise
 */
function passwordNeedsRehash($hash) {
    // MD5 hashes always need rehashing
    if (preg_match('/^[a-f0-9]{32}$/i', $hash)) {
        return true;
    }
    
    // Check if password_hash needs rehashing
    return password_needs_rehash($hash, PASSWORD_DEFAULT);
}

/**
 * Upgrade an MD5 password to password_hash
 * 
 * @param string $password The plain text password
 * @return string The new hashed password
 */
function upgradePassword($password) {
    return hashPassword($password);
}
