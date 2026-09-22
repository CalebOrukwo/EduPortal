<?php
/**
 * Authentication and Access Control Middleware
 * File: config/auth.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Checks if a user is logged in
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']) && isset($_SESSION['role']);
}

/**
 * Restricts access to authenticated users only
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        header("Location: ../login.php?error=unauthorized");
        exit();
    }
}

/**
 * Ensures user has an Admin role
 */
function checkAdmin(): void {
    requireLogin();
    if ($_SESSION['role'] !== 'admin') {
        header("Location: ../login.php?error=access_denied");
        exit();
    }
}

/**
 * Ensures user has a Staff role
 */
function checkStaff(): void {
    requireLogin();
    if ($_SESSION['role'] !== 'staff') {
        header("Location: ../login.php?error=access_denied");
        exit();
    }
}

/**
 * Ensures user has a Student role
 */
function checkStudent(): void {
    requireLogin();
    if ($_SESSION['role'] !== 'student') {
        header("Location: ../login.php?error=access_denied");
        exit();
    }
}

/**
 * Ensures user has any of the allowed roles
 */
function checkAnyRole(array $allowedRoles): void {
    requireLogin();
    if (!in_array($_SESSION['role'], $allowedRoles, true)) {
        header("Location: ../login.php?error=access_denied");
        exit();
    }
}
?>