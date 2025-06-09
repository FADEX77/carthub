<?php
require_once '../config/database.php';
requireLogin('vendor');

$vendor_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Get current vendor data
$vendor_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$vendor_stmt->execute([$vendor_id]);
$vendor = $vendor_stmt->fetch();

// Handle form submissions
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $full_name = sanitize_input($_POST['full_name']);
        $email = sanitize_input($_POST['email']);
        $phone = sanitize_input($_POST['phone']);
        $address = sanitize_input($_POST['address']);
        
        // Check if email is already taken by another user
        $email_check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $email_check->execute([$email, $vendor_id]);
        
        if ($email_check->fetch()) {
            $error = 'Email address is already taken by another user.';
        } else {
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, address = ? WHERE id = ?");
            if ($stmt->execute([$full_name, $email, $phone, $address, $vendor_id])) {
                $_SESSION['full_name'] = $full_name;
                $message = 'Profile updated successfully!';
                // Refresh vendor data
                $vendor_stmt->execute([$vendor_id]);
                $vendor = $vendor_stmt->fetch();
            } else {
                $error = 'Error updating profile.';
            }
        }
    } elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (!password_verify($current_password, $vendor['password'])) {
            $error = 'Current password is incorrect.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New passwords do not match.';
        } elseif (strlen($new_password) < 6) {
            $error = 'New password must be at least 6 characters long.';
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($stmt->execute([$hashed_password, $vendor_id])) {
                $message = 'Password changed successfully!';
            } else {
                $error = 'Error changing password.';
            }
        }
    } elseif ($action === 'upload_avatar') {
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/avatars/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($file_extension, $allowed_extensions)) {
                $new_filename = 'avatar_' . $vendor_id . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_path)) {
                    // Delete old avatar if exists
                    if ($vendor['avatar'] && file_exists($upload_dir . $vendor['avatar'])) {
                        unlink($upload_dir . $vendor['avatar']);
                    }
                    
                    $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                    if ($stmt->execute([$new_filename, $vendor_id])) {
                        $message = 'Avatar updated successfully!';
                        // Refresh vendor data
                        $vendor_stmt->execute([$vendor_id]);
                        $vendor = $vendor_stmt->fetch();
                    }
                } else {
                    $error = 'Failed to upload avatar.';
                }
            } else {
                $error = 'Invalid image format. Please use JPG, PNG, or GIF.';
            }
        }
    }
}

// Add avatar column if it doesn't exist
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN avatar VARCHAR(255) DEFAULT NULL");
} catch (PDOException $e) {
    // Column already exists
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - CartHub Vendor</title>
    <link rel="stylesheet" href="../css/modern_style.css">
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <a href="../index.php" class="logo">
                    <img src="../images/carthub-logo.png" alt="CartHub Logo">
                    CartHub Vendor
                </a>
                <nav>
                    <ul class="nav-links">
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><a href="products.php">My Products</a></li>
                        <li><a href="add_product.php">Add Product</a></li>
                        <li><a href="orders.php">Orders</a></li>
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
                <p class="dashboard-subtitle">Manage your account information and store preferences</p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 2rem;">
                <!-- Settings Navigation -->
                <div class="table-container">
                    <div class="table-header">
                        <h3 class="table-title">Settings Menu</h3>
                    </div>
                    <div style="padding: 1rem;">
                        <nav style="display: flex; flex-direction: column; gap: 0.5rem;">
                            <a href="#profile" class="settings-nav-link active" onclick="showSection('profile', this)">
                                👤 Profile Information
                            </a>
                            <a href="#avatar" class="settings-nav-link" onclick="showSection('avatar', this)">
                                📷 Profile Picture
                            </a>
                            <a href="#password" class="settings-nav-link" onclick="showSection('password', this)">
                                🔒 Change Password
                            </a>
                            <a href="#payment" class="settings-nav-link" onclick="showSection('payment', this)">
                                💳 Payment Settings
                            </a>
                            <a href="#notifications" class="settings-nav-link" onclick="showSection('notifications', this)">
                                🔔 Notifications
                            </a>
                            <a href="#store" class="settings-nav-link" onclick="showSection('store', this)">
                                🏪 Store Settings
                            </a>
                        </nav>
                    </div>
                </div>

                <!-- Settings Content -->
                <div>
                    <!-- Profile Information -->
                    <div id="profile-section" class="settings-section">
                        <div class="table-container">
                            <div class="table-header">
                                <h3 class="table-title">👤 Profile Information</h3>
                            </div>
                            <div style="padding: 2rem;">
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_profile">
                                    
                                    <div class="form-group">
                                        <label for="full_name">Full Name</label>
                                        <input type="text" name="full_name" id="full_name" value="<?php echo htmlspecialchars($vendor['full_name']); ?>" required>
                                    </div>

                                    <div class="form-group">
                                        <label for="username">Username</label>
                                        <input type="text" value="<?php echo htmlspecialchars($vendor['username']); ?>" disabled style="background: var(--light-color); color: var(--text-secondary);">
                                        <small style="color: var(--text-secondary);">Username cannot be changed</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="email">Email Address</label>
                                        <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($vendor['email']); ?>" required>
                                    </div>

                                    <div class="form-group">
                                        <label for="phone">Phone Number</label>
                                        <input type="tel" name="phone" id="phone" value="<?php echo htmlspecialchars($vendor['phone']); ?>">
                                    </div>

                                    <div class="form-group">
                                        <label for="address">Address</label>
                                        <textarea name="address" id="address" rows="3"><?php echo htmlspecialchars($vendor['address']); ?></textarea>
                                    </div>

                                    <button type="submit" class="btn btn-primary">Update Profile</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Avatar Upload -->
                    <div id="avatar-section" class="settings-section" style="display: none;">
                        <div class="table-container">
                            <div class="table-header">
                                <h3 class="table-title">📷 Profile Picture</h3>
                            </div>
                            <div style="padding: 2rem; text-align: center;">
                                <div style="margin-bottom: 2rem;">
                                    <img src="<?php echo $vendor['avatar'] ? '../uploads/avatars/' . $vendor['avatar'] : '/placeholder.svg?height=150&width=150'; ?>" 
                                         alt="Profile Picture" 
                                         style="width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 4px solid var(--primary-color);">
                                </div>
                                
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="upload_avatar">
                                    
                                    <div style="margin-bottom: 1.5rem;">
                                        <input type="file" name="avatar" id="avatar" accept="image/*" style="display: none;" onchange="previewAvatar(this)">
                                        <button type="button" onclick="document.getElementById('avatar').click()" class="btn btn-secondary">
                                            Choose New Picture
                                        </button>
                                    </div>
                                    
                                    <div id="avatar-preview" style="margin-bottom: 1.5rem;"></div>
                                    
                                    <button type="submit" class="btn btn-primary" id="upload-avatar-btn" style="display: none;">
                                        Upload Picture
                                    </button>
                                </form>
                                
                                <p style="color: var(--text-secondary); font-size: 0.875rem; margin-top: 1rem;">
                                    Supported formats: JPG, PNG, GIF (Max: 2MB)
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Change Password -->
                    <div id="password-section" class="settings-section" style="display: none;">
                        <div class="table-container">
                            <div class="table-header">
                                <h3 class="table-title">🔒 Change Password</h3>
                            </div>
                            <div style="padding: 2rem;">
                                <form method="POST">
                                    <input type="hidden" name="action" value="change_password">
                                    
                                    <div class="form-group">
                                        <label for="current_password">Current Password</label>
                                        <input type="password" name="current_password" id="current_password" required>
                                    </div>

                                    <div class="form-group">
                                        <label for="new_password">New Password</label>
                                        <input type="password" name="new_password" id="new_password" required>
                                        <small style="color: var(--text-secondary);">Minimum 6 characters</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="confirm_password">Confirm New Password</label>
                                        <input type="password" name="confirm_password" id="confirm_password" required>
                                    </div>

                                    <button type="submit" class="btn btn-primary">Change Password</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Settings -->
                    <div id="payment-section" class="settings-section" style="display: none;">
                        <div class="table-container">
                            <div class="table-header">
                                <h3 class="table-title">💳 Payment Settings</h3>
                            </div>
                            <div style="padding: 2rem;">
                                <div style="background: var(--light-color); padding: 2rem; border-radius: var(--border-radius); margin-bottom: 2rem;">
                                    <h4 style="margin-bottom: 1rem;">Payment Methods</h4>
                                    <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">Add your preferred payment methods to receive payments from sales.</p>
                                    
                                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                                        <div style="padding: 1.5rem; background: white; border-radius: var(--border-radius); border: 2px dashed var(--border-color); text-align: center;">
                                            <div style="font-size: 2rem; margin-bottom: 0.5rem;">🏦</div>
                                            <div style="font-weight: 600; margin-bottom: 0.5rem;">Bank Account</div>
                                            <button class="btn btn-secondary" style="font-size: 0.875rem;">Add Bank Account</button>
                                        </div>
                                        
                                        <div style="padding: 1.5rem; background: white; border-radius: var(--border-radius); border: 2px dashed var(--border-color); text-align: center;">
                                            <div style="font-size: 2rem; margin-bottom: 0.5rem;">💳</div>
                                            <div style="font-weight: 600; margin-bottom: 0.5rem;">PayPal</div>
                                            <button class="btn btn-secondary" style="font-size: 0.875rem;">Connect PayPal</button>
                                        </div>
                                        
                                        <div style="padding: 1.5rem; background: white; border-radius: var(--border-radius); border: 2px dashed var(--border-color); text-align: center;">
                                            <div style="font-size: 2rem; margin-bottom: 0.5rem;">📱</div>
                                            <div style="font-weight: 600; margin-bottom: 0.5rem;">Mobile Money</div>
                                            <button class="btn btn-secondary" style="font-size: 0.875rem;">Add Mobile Money</button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div style="background: rgba(59, 130, 246, 0.1); padding: 1.5rem; border-radius: var(--border-radius); border-left: 4px solid var(--info-color);">
                                    <h5 style="color: var(--info-color); margin-bottom: 0.5rem;">💡 Payment Information</h5>
                                    <p style="color: var(--text-secondary); font-size: 0.875rem;">
                                        Payments are processed weekly. You'll receive your earnings minus our 3% platform fee directly to your chosen payment method.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notifications -->
                    <div id="notifications-section" class="settings-section" style="display: none;">
                        <div class="table-container">
                            <div class="table-header">
                                <h3 class="table-title">🔔 Notification Preferences</h3>
                            </div>
                            <div style="padding: 2rem;">
                                <form>
                                    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: var(--light-color); border-radius: var(--border-radius);">
                                            <div>
                                                <div style="font-weight: 600;">New Orders</div>
                                                <div style="font-size: 0.875rem; color: var(--text-secondary);">Get notified when you receive new orders</div>
                                            </div>
                                            <label class="switch">
                                                <input type="checkbox" checked>
                                                <span class="slider"></span>
                                            </label>
                                        </div>
                                        
                                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: var(--light-color); border-radius: var(--border-radius);">
                                            <div>
                                                <div style="font-weight: 600;">Low Stock Alerts</div>
                                                <div style="font-size: 0.875rem; color: var(--text-secondary);">Alert when product stock is running low</div>
                                            </div>
                                            <label class="switch">
                                                <input type="checkbox" checked>
                                                <span class="slider"></span>
                                            </label>
                                        </div>
                                        
                                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: var(--light-color); border-radius: var(--border-radius);">
                                            <div>
                                                <div style="font-weight: 600;">Payment Updates</div>
                                                <div style="font-size: 0.875rem; color: var(--text-secondary);">Notifications about payments and payouts</div>
                                            </div>
                                            <label class="switch">
                                                <input type="checkbox" checked>
                                                <span class="slider"></span>
                                            </label>
                                        </div>
                                        
                                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: var(--light-color); border-radius: var(--border-radius);">
                                            <div>
                                                <div style="font-weight: 600;">Marketing Tips</div>
                                                <div style="font-size: 0.875rem; color: var(--text-secondary);">Receive tips to improve your sales</div>
                                            </div>
                                            <label class="switch">
                                                <input type="checkbox">
                                                <span class="slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary" style="margin-top: 2rem;">Save Preferences</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Store Settings -->
                    <div id="store-section" class="settings-section" style="display: none;">
                        <div class="table-container">
                            <div class="table-header">
                                <h3 class="table-title">🏪 Store Settings</h3>
                            </div>
                            <div style="padding: 2rem;">
                                <form>
                                    <div class="form-group">
                                        <label for="store_name">Store Name</label>
                                        <input type="text" id="store_name" value="<?php echo htmlspecialchars($vendor['full_name']); ?>">
                                    </div>

                                    <div class="form-group">
                                        <label for="store_description">Store Description</label>
                                        <textarea id="store_description" rows="4" placeholder="Tell customers about your store..."></textarea>
                                    </div>

                                    <div class="form-group">
                                        <label for="business_hours">Business Hours</label>
                                        <input type="text" id="business_hours" placeholder="e.g., Mon-Fri 9AM-6PM">
                                    </div>

                                    <div class="form-group">
                                        <label for="return_policy">Return Policy</label>
                                        <textarea id="return_policy" rows="3" placeholder="Describe your return policy..."></textarea>
                                    </div>

                                    <div class="form-group">
                                        <label for="shipping_info">Shipping Information</label>
                                        <textarea id="shipping_info" rows="3" placeholder="Shipping methods and timeframes..."></textarea>
                                    </div>

                                    <button type="submit" class="btn btn-primary">Update Store Settings</button>
                                </form>
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
                <p>&copy; 2025 CartHub Vendor Portal. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <style>
        .settings-nav-link {
            display: block;
            padding: 1rem;
            text-decoration: none;
            color: var(--text-primary);
            border-radius: var(--border-radius);
            transition: var(--transition);
            border: 1px solid transparent;
        }

        .settings-nav-link:hover {
            background: var(--light-color);
            border-color: var(--border-color);
        }

        .settings-nav-link.active {
            background: var(--primary-color);
            color: white;
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
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
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: var(--primary-color);
        }

        input:focus + .slider {
            box-shadow: 0 0 1px var(--primary-color);
        }

        input:checked + .slider:before {
            transform: translateX(26px);
        }
    </style>

    <script>
        function showSection(sectionName, element) {
            // Hide all sections
            const sections = document.querySelectorAll('.settings-section');
            sections.forEach(section => section.style.display = 'none');
            
            // Show selected section
            document.getElementById(sectionName + '-section').style.display = 'block';
            
            // Update navigation
            const navLinks = document.querySelectorAll('.settings-nav-link');
            navLinks.forEach(link => link.classList.remove('active'));
            element.classList.add('active');
        }

        function previewAvatar(input) {
            const file = input.files[0];
            const preview = document.getElementById('avatar-preview');
            const uploadBtn = document.getElementById('upload-avatar-btn');
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = `
                        <div style="margin-bottom: 1rem;">
                            <img src="${e.target.result}" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 2px solid var(--success-color);">
                        </div>
                        <p style="color: var(--success-color); font-weight: 600;">Ready to upload: ${file.name}</p>
                    `;
                    uploadBtn.style.display = 'inline-block';
                };
                reader.readAsDataURL(file);
            }
        }

        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.style.borderColor = 'var(--danger-color)';
            } else {
                this.style.borderColor = 'var(--border-color)';
            }
        });
    </script>
</body>
</html>