<?php
/**
 * Global Utility Helper Functions
 * File: config/functions.php
 */

require_once __DIR__ . '/db.php';

/**
 * Dynamic Base URL Configuration
 * Auto-detects protocol, domain, and subfolder path (e.g., http://localhost/school/)
 */
if (!defined('BASE_URL')) {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Extract base folder relative to server root
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $dirParts = explode('/', trim($scriptName, '/'));
    
    // If running in a subfolder (e.g. /school/config/functions.php or /school/index.php)
    $projectFolder = (!empty($dirParts) && $dirParts[0] !== 'index.php' && $dirParts[0] !== '') ? '/' . $dirParts[0] . '/' : '/';
    
    define('BASE_URL', $protocol . '://' . $host . $projectFolder);
}

/**
 * Sanitizes user input string for safety
 */
function sanitizeInput(string $data): string {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Fetches the currently active academic term and session details
 */
function getCurrentTerm(PDO $pdo): ?array {
    $sql = "SELECT t.id AS term_id, t.term_name, t.is_promotional, s.id AS session_id, s.name AS session_name 
            FROM sch_terms t 
            INNER JOIN sch_sessions s ON t.session_id = s.id 
            WHERE t.is_current = 1 AND s.is_active = 1 
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetch();
    return $result ?: null;
}

/**
 * Calculates total assessment score from CA components and Exam
 */
function calculateTotalScore(float $ca1 = 0.0, float $ca2 = 0.0, float $ca3 = 0.0, float $exam = 0.0): float {
    return $ca1 + $ca2 + $ca3 + $exam;
}

/**
 * Formats monetary amounts in Nigerian Naira (NGN)
 */
function formatCurrency(float $amount): string {
    return '₦' . number_format($amount, 2);
}

/**
 * Sets a session flash message for UI feedback
 */
function setFlashMessage(string $type, string $message): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash_message'] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

/**
 * Generates a unique 6-character alphanumeric student code
 */
function generateStudentCode(): string {
    return strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 6));
}
?>