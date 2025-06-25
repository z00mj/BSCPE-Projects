<?php
// Start the session
session_start();

// Unset all session variables
$_SESSION = array();

// If it's desired to kill the session, also delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally, destroy the session
session_destroy();

// Clear any remaining session data
session_unset();

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Thu, 19 Nov 1981 08:52:00 GMT");

// Add no-cache headers for all browsers
header("Pragma: no-cache");
header("Expires: 0");

// Redirect to login page with a random parameter to prevent caching
$random = md5(uniqid(rand(), true));
header("Location: /Luck Wallet/auth/login.php?loggedout=1&rnd=" . $random);
exit();
?>
