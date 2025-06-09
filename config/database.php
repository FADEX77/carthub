<?php
// Database configuration
$host = 'localhost';
$dbname = 'carthub';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Helper functions
function sanitize_input($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_type']);
}

function getUserType() {
    return $_SESSION['user_type'] ?? null;
}

function requireLogin($required_type = null) {
    if (!isLoggedIn()) {
        header('Location: ../index.php');
        exit();
    }
    
    if ($required_type && getUserType() !== $required_type) {
        // Redirect to appropriate dashboard based on user type
        $userType = getUserType();
        switch ($userType) {
            case 'admin':
                header('Location: ../admin/dashboard.php');
                break;
            case 'vendor':
                header('Location: ../vendor/dashboard.php');
                break;
            case 'buyer':
                header('Location: ../buyer/dashboard.php');
                break;
            default:
                header('Location: ../index.php');
        }
        exit();
    }
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function showAlert($message, $type = 'info') {
    return "<div class='alert alert-$type'>$message</div>";
}

// Check if user is logged in and redirect to appropriate dashboard
function checkLoginRedirect() {
    if (isLoggedIn()) {
        $userType = getUserType();
        switch ($userType) {
            case 'admin':
                return 'admin/dashboard.php';
            case 'vendor':
                return 'vendor/dashboard.php';
            case 'buyer':
                return 'buyer/dashboard.php';
        }
    }
    return false;
}
?>