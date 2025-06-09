<?php
require_once '../config/database.php';
requireLogin('buyer');

$buyer_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Get cart count for header
$cart_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM cart WHERE buyer_id = ?");
$cart_count_stmt->execute([$buyer_id]);
$cart_count = $cart_count_stmt->fetchColumn();

// Get user data
$user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->execute([$buyer_id]);
$user = $user_stmt->fetch();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $full_name = sanitize_input($_POST['full_name']);
        $email = sanitize_input($_POST['email']);
        $phone = sanitize_input($_POST['phone'] ?? '');
        $address = sanitize_input($_POST['address'] ?? '');
        $city = sanitize_input($_POST['city'] ?? '');
        $state = sanitize_input($_POST['state'] ?? '');
        $zip_code = sanitize_input($_POST['zip_code'] ?? '');
        $country = sanitize_input($_POST['country'] ?? '');
        
        // Check if email is already taken by another user
        $email_check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $email_check->execute([$email, $buyer_id]);
        
        if ($email_check->fetch()) {
            $error = 'Email address is already taken by another user.';
        } else {
            $update_stmt = $pdo->prepare("
                UPDATE users SET 
                    full_name = ?, email = ?, phone = ?, address = ?, 
                    city = ?, state = ?, zip_code = ?, country = ?
                WHERE id = ?
            ");
            
            if ($update_stmt->execute([$full_name, $email, $phone, $address, $city, $state, $zip_code, $country, $buyer_id])) {
                $success = 'Profile updated successfully!';
                $_SESSION['full_name'] = $full_name;
                $_SESSION['email'] = $email;
                
                // Refresh user data
                $user_stmt->execute([$buyer_id]);
                $user = $user_stmt->fetch();
            } else {
                $error = 'Failed to update profile. Please try again.';
            }
        }
    }
    
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'Please fill in all password fields.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New passwords do not match.';
        } elseif (strlen($new_password) < 6) {
            $error = 'New password must be at least 6 characters long.';
        } elseif (!password_verify($current_password, $user['password'])) {
            $error = 'Current password is incorrect.';
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $password_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            
            if ($password_stmt->execute([$hashed_password, $buyer_id])) {
                $success = 'Password changed successfully!';
            } else {
                $error = 'Failed to change password. Please try again.';
            }
        }
    }
    
    if (isset($_POST['update_preferences'])) {
        $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
        $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
        $newsletter = isset($_POST['newsletter']) ? 1 : 0;
        $order_updates = isset($_POST['order_updates']) ? 1 : 0;
        $promotional_emails = isset($_POST['promotional_emails']) ? 1 : 0;
        
        // Check if preferences table exists, if not create it
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS user_preferences (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                email_notifications TINYINT(1) DEFAULT 1,
                sms_notifications TINYINT(1) DEFAULT 0,
                newsletter TINYINT(1) DEFAULT 1,
                order_updates TINYINT(1) DEFAULT 1,
                promotional_emails TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
        
        // Insert or update preferences
        $pref_stmt = $pdo->prepare("
            INSERT INTO user_preferences (user_id, email_notifications, sms_notifications, newsletter, order_updates, promotional_emails)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            email_notifications = VALUES(email_notifications),
            sms_notifications = VALUES(sms_notifications),
            newsletter = VALUES(newsletter),
            order_updates = VALUES(order_updates),
            promotional_emails = VALUES(promotional_emails),
            updated_at = CURRENT_TIMESTAMP
        ");
        
        if ($pref_stmt->execute([$buyer_id, $email_notifications, $sms_notifications, $newsletter, $order_updates, $promotional_emails])) {
            $success = 'Preferences updated successfully!';
        } else {
            $error = 'Failed to update preferences. Please try again.';
        }
    }
}

// Get user preferences
$pref_stmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
$pref_stmt->execute([$buyer_id]);
$preferences = $pref_stmt->fetch() ?: [
    'email_notifications' => 1,
    'sms_notifications' => 0,
    'newsletter' => 1,
    'order_updates' => 1,
    'promotional_emails' => 0
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings - CartHub</title>
    <link rel="stylesheet" href="../css/modern_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="icon" href="images/carthub-logo.png" type="image/png">
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <a href="../index.php" class="logo">
                    <img src="../images/carthub-logo.png" alt="CartHub Logo">
                    CartHub
                </a>
                <nav>
                    <ul class="nav-links">
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><a href="browse.php">Browse Products</a></li>
                        <li><a href="cart.php">Cart (<?php echo $cart_count; ?>)</a></li>
                        <li><a href="orders.php">My Orders</a></li>
                        <li><a href="wishlist.php">Wishlist</a></li>
                        <li><a href="settings.php" class="active">Settings</a></li>
                        <li><a href="../logout.php" class="btn btn-danger">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main class="dashboard">
        <div class="container">
            <div class="dashboard-header">
                <h1 class="dashboard-title">Account Settings</h1>
                <p class="dashboard-subtitle">Manage your account information and preferences</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <div class="settings-container">
                <!-- Settings Navigation -->
                <div class="settings-nav">
                    <div class="nav-item active" data-tab="profile">
                        <i class="fas fa-user"></i>
                        <span>Profile Information</span>
                    </div>
                    <div class="nav-item" data-tab="security">
                        <i class="fas fa-lock"></i>
                        <span>Security</span>
                    </div>
                    <div class="nav-item" data-tab="preferences">
                        <i class="fas fa-cog"></i>
                        <span>Preferences</span>
                    </div>
                    <div class="nav-item" data-tab="privacy">
                        <i class="fas fa-shield-alt"></i>
                        <span>Privacy</span>
                    </div>
                </div>

                <!-- Settings Content -->
                <div class="settings-content">
                    <!-- Profile Information Tab -->
                    <div class="tab-content active" id="profile">
                        <div class="form-container">
                            <h3>Profile Information</h3>
                            <form method="POST">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="full_name">Full Name *</label>
                                        <input type="text" name="full_name" id="full_name" required 
                                               value="<?php echo htmlspecialchars($user['full_name']); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="email">Email Address *</label>
                                        <input type="email" name="email" id="email" required 
                                               value="<?php echo htmlspecialchars($user['email']); ?>">
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="phone">Phone Number</label>
                                        <input type="tel" name="phone" id="phone" 
                                               value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="country">Country</label>
                                        <select name="country" id="country">
                                            <option value="">Select Country</option>
                                            <option value="US" <?php echo ($user['country'] ?? '') === 'US' ? 'selected' : ''; ?>>United States</option>
                                            <option value="CA" <?php echo ($user['country'] ?? '') === 'CA' ? 'selected' : ''; ?>>Canada</option>
                                            <option value="UK" <?php echo ($user['country'] ?? '') === 'UK' ? 'selected' : ''; ?>>United Kingdom</option>
                                            <option value="AU" <?php echo ($user['country'] ?? '') === 'AU' ? 'selected' : ''; ?>>Australia</option>
                                            <option value="DE" <?php echo ($user['country'] ?? '') === 'DE' ? 'selected' : ''; ?>>Germany</option>
                                            <option value="FR" <?php echo ($user['country'] ?? '') === 'FR' ? 'selected' : ''; ?>>France</option>
                                            <option value="JP" <?php echo ($user['country'] ?? '') === 'JP' ? 'selected' : ''; ?>>Japan</option>
                                            <option value="Other" <?php echo ($user['country'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="address">Address</label>
                                    <input type="text" name="address" id="address" 
                                           value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="city">City</label>
                                        <input type="text" name="city" id="city" 
                                               value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="state">State/Province</label>
                                        <input type="text" name="state" id="state" 
                                               value="<?php echo htmlspecialchars($user['state'] ?? ''); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="zip_code">ZIP/Postal Code</label>
                                        <input type="text" name="zip_code" id="zip_code" 
                                               value="<?php echo htmlspecialchars($user['zip_code'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <button type="submit" name="update_profile" class="btn btn-primary">
                                    Update Profile
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Security Tab -->
                    <div class="tab-content" id="security">
                        <div class="form-container">
                            <h3>Change Password</h3>
                            <form method="POST">
                                <div class="form-group">
                                    <label for="current_password">Current Password *</label>
                                    <input type="password" name="current_password" id="current_password" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="new_password">New Password *</label>
                                    <input type="password" name="new_password" id="new_password" required minlength="6">
                                    <small>Password must be at least 6 characters long</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="confirm_password">Confirm New Password *</label>
                                    <input type="password" name="confirm_password" id="confirm_password" required>
                                </div>
                                
                                <button type="submit" name="change_password" class="btn btn-primary">
                                    Change Password
                                </button>
                            </form>
                        </div>
                        
                        <div class="form-container">
                            <h3>Account Security</h3>
                            <div class="security-info">
                                <div class="security-item">
                                    <div class="security-icon">
                                        <i class="fas fa-calendar-alt"></i>
                                    </div>
                                    <div class="security-details">
                                        <h4>Account Created</h4>
                                        <p><?php echo date('F j, Y', strtotime($user['created_at'])); ?></p>
                                    </div>
                                </div>
                                
                                <div class="security-item">
                                    <div class="security-icon">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div class="security-details">
                                        <h4>Last Login</h4>
                                        <p><?php echo date('F j, Y g:i A', strtotime($user['last_login'] ?? $user['created_at'])); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Preferences Tab -->
                    <div class="tab-content" id="preferences">
                        <div class="form-container">
                            <h3>Notification Preferences</h3>
                            <form method="POST">
                                <div class="preference-group">
                                    <div class="preference-item">
                                        <label class="switch">
                                            <input type="checkbox" name="email_notifications" 
                                                   <?php echo $preferences['email_notifications'] ? 'checked' : ''; ?>>
                                            <span class="slider"></span>
                                        </label>
                                        <div class="preference-info">
                                            <h4>Email Notifications</h4>
                                            <p>Receive important updates via email</p>
                                        </div>
                                    </div>
                                    
                                    <div class="preference-item">
                                        <label class="switch">
                                            <input type="checkbox" name="sms_notifications" 
                                                   <?php echo $preferences['sms_notifications'] ? 'checked' : ''; ?>>
                                            <span class="slider"></span>
                                        </label>
                                        <div class="preference-info">
                                            <h4>SMS Notifications</h4>
                                            <p>Receive order updates via text message</p>
                                        </div>
                                    </div>
                                    
                                    <div class="preference-item">
                                        <label class="switch">
                                            <input type="checkbox" name="order_updates" 
                                                   <?php echo $preferences['order_updates'] ? 'checked' : ''; ?>>
                                            <span class="slider"></span>
                                        </label>
                                        <div class="preference-info">
                                            <h4>Order Updates</h4>
                                            <p>Get notified about order status changes</p>
                                        </div>
                                    </div>
                                    
                                    <div class="preference-item">
                                        <label class="switch">
                                            <input type="checkbox" name="newsletter" 
                                                   <?php echo $preferences['newsletter'] ? 'checked' : ''; ?>>
                                            <span class="slider"></span>
                                        </label>
                                        <div class="preference-info">
                                            <h4>Newsletter</h4>
                                            <p>Receive our weekly newsletter with new products</p>
                                        </div>
                                    </div>
                                    
                                    <div class="preference-item">
                                        <label class="switch">
                                            <input type="checkbox" name="promotional_emails" 
                                                   <?php echo $preferences['promotional_emails'] ? 'checked' : ''; ?>>
                                            <span class="slider"></span>
                                        </label>
                                        <div class="preference-info">
                                            <h4>Promotional Emails</h4>
                                            <p>Receive special offers and discounts</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" name="update_preferences" class="btn btn-primary">
                                    Save Preferences
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Privacy Tab -->
                    <div class="tab-content" id="privacy">
                        <div class="form-container">
                            <h3>Privacy Settings</h3>
                            <div class="privacy-section">
                                <h4>Data & Privacy</h4>
                                <p>We take your privacy seriously. Here's how we handle your data:</p>
                                
                                <div class="privacy-item">
                                    <i class="fas fa-shield-alt"></i>
                                    <div>
                                        <h5>Data Protection</h5>
                                        <p>Your personal information is encrypted and securely stored.</p>
                                    </div>
                                </div>
                                
                                <div class="privacy-item">
                                    <i class="fas fa-eye-slash"></i>
                                    <div>
                                        <h5>Privacy Control</h5>
                                        <p>You have full control over what information you share.</p>
                                    </div>
                                </div>
                                
                                <div class="privacy-item">
                                    <i class="fas fa-download"></i>
                                    <div>
                                        <h5>Data Export</h5>
                                        <p>Request a copy of your data at any time.</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="privacy-actions">
                                <button class="btn btn-secondary" onclick="exportData()">
                                    Export My Data
                                </button>
                                <button class="btn btn-danger" onclick="deleteAccount()">
                                    Delete Account
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <div class="footer-bottom">
                <p>&copy; 2024 CartHub. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // Tab switching functionality
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', function() {
                const tabId = this.dataset.tab;
                
                // Remove active class from all nav items and tab contents
                document.querySelectorAll('.nav-item').forEach(nav => nav.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
                
                // Add active class to clicked nav item and corresponding tab content
                this.classList.add('active');
                document.getElementById(tabId).classList.add('active');
            });
        });
        
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (newPassword !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
        
        function exportData() {
            if (confirm('This will generate a file containing all your account data. Continue?')) {
                // Create a simple data export
                const userData = {
                    profile: {
                        name: '<?php echo addslashes($user['full_name']); ?>',
                        email: '<?php echo addslashes($user['email']); ?>',
                        phone: '<?php echo addslashes($user['phone'] ?? ''); ?>',
                        address: '<?php echo addslashes($user['address'] ?? ''); ?>',
                        city: '<?php echo addslashes($user['city'] ?? ''); ?>',
                        state: '<?php echo addslashes($user['state'] ?? ''); ?>',
                        zipCode: '<?php echo addslashes($user['zip_code'] ?? ''); ?>',
                        country: '<?php echo addslashes($user['country'] ?? ''); ?>',
                        accountCreated: '<?php echo $user['created_at']; ?>'
                    },
                    preferences: {
                        emailNotifications: <?php echo $preferences['email_notifications'] ? 'true' : 'false'; ?>,
                        smsNotifications: <?php echo $preferences['sms_notifications'] ? 'true' : 'false'; ?>,
                        newsletter: <?php echo $preferences['newsletter'] ? 'true' : 'false'; ?>,
                        orderUpdates: <?php echo $preferences['order_updates'] ? 'true' : 'false'; ?>,
                        promotionalEmails: <?php echo $preferences['promotional_emails'] ? 'true' : 'false'; ?>
                    }
                };
                
                const dataStr = JSON.stringify(userData, null, 2);
                const dataBlob = new Blob([dataStr], {type: 'application/json'});
                const url = URL.createObjectURL(dataBlob);
                const link = document.createElement('a');
                link.href = url;
                link.download = 'my_carthub_data.json';
                link.click();
                URL.revokeObjectURL(url);
            }
        }
        
        function deleteAccount() {
            if (confirm('Are you sure you want to delete your account? This action cannot be undone.')) {
                if (confirm('This will permanently delete all your data, orders, and wishlist items. Are you absolutely sure?')) {
                    window.location.href = '../delete_account.php';
                }
            }
        }
    </script>

    <style>
        .settings-container {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .settings-nav {
            background: white;
            border-radius: var(--border-radius);
            padding: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            height: fit-content;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 0.5rem;
        }
        
        .nav-item:hover {
            background: var(--light-color);
        }
        
        .nav-item.active {
            background: var(--primary-color);
            color: white;
        }
        
        .nav-item i {
            font-size: 1.1rem;
            width: 20px;
        }
        
        .settings-content {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .tab-content {
            display: none;
            padding: 2rem;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .form-container {
            margin-bottom: 2rem;
        }
        
        .form-container:last-child {
            margin-bottom: 0;
        }
        
        .form-container h3 {
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-primary);
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 1rem;
        }
        
        .form-group small {
            display: block;
            margin-top: 0.25rem;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        
        .security-info {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .security-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: var(--light-color);
            border-radius: var(--border-radius);
        }
        
        .security-icon {
            width: 40px;
            height: 40px;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        
        .security-details h4 {
            margin: 0 0 0.25rem;
            font-size: 1rem;
        }
        
        .security-details p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        
        .preference-group {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .preference-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
        }
        
        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }
        
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 24px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: var(--primary-color);
        }
        
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        
        .preference-info h4 {
            margin: 0 0 0.25rem;
            font-size: 1rem;
        }
        
        .preference-info p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        
        .privacy-section {
            margin-bottom: 2rem;
        }
        
        .privacy-section h4 {
            margin-bottom: 1rem;
        }
        
        .privacy-section p {
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
        }
        
        .privacy-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 1rem;
            padding: 1rem;
            background: var(--light-color);
            border-radius: var(--border-radius);
        }
        
        .privacy-item i {
            color: var(--primary-color);
            font-size: 1.5rem;
            margin-top: 0.25rem;
        }
        
        .privacy-item h5 {
            margin: 0 0 0.5rem;
            font-size: 1rem;
        }
        
        .privacy-item p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        
        .privacy-actions {
            display: flex;
            gap: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }
        
        @media (max-width: 992px) {
            .settings-container {
                grid-template-columns: 1fr;
            }
            
            .settings-nav {
                display: flex;
                overflow-x: auto;
                gap: 0.5rem;
                padding: 1rem;
            }
            
            .nav-item {
                white-space: nowrap;
                margin-bottom: 0;
                min-width: fit-content;
            }
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .privacy-actions {
                flex-direction: column;
            }
            
            .nav-item span {
                display: none;
            }
            
            .nav-item {
                justify-content: center;
                min-width: 60px;
            }
        }
    </style>
</body>
</html>