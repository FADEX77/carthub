<?php
require_once 'config/database.php';

// Create properly hashed passwords
$admin_password = password_hash('admin123', PASSWORD_DEFAULT);
$vendor_password = password_hash('password', PASSWORD_DEFAULT);

try {
    // Update admin password
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
    $stmt->execute([$admin_password]);
    
    // Update all vendor passwords
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_type = 'vendor'");
    $stmt->execute([$vendor_password]);
    
    echo "✅ Passwords updated successfully!\n\n";
    echo "Admin Login:\n";
    echo "Username: admin\n";
    echo "Password: admin123\n\n";
    
    echo "Vendor Logins (Password: vendor123):\n";
    
    // Get all vendors
    $vendors = $pdo->query("SELECT username, full_name FROM users WHERE user_type = 'vendor' ORDER BY username")->fetchAll();
    
    foreach ($vendors as $vendor) {
        echo "Username: {$vendor['username']} ({$vendor['full_name']})\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>