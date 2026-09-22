<?php
/**
 * Session Termination Script
 * File: logout.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/functions.php';

// Clear all active session variables
$_SESSION = array();

// Destroy session cookie if present
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Unset session arrays and destroy session data
session_unset();
session_destroy();

// Restart session strictly to set logged_out message banner
session_start();
setFlashMessage('info', 'You have been successfully logged out.');

header('Location: login.php?msg=logged_out');
exit();
?>