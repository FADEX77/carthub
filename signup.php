<?php
require_once 'config/database.php';

$error = '';
$success = '';

if ($_POST) {
    $username = sanitize_input($_POST['username']);
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $user_type = sanitize_input($_POST['user_type']);
    $full_name = sanitize_input($_POST['full_name']);
    $phone = sanitize_input($_POST['phone']);
    $address = sanitize_input($_POST['address']);

    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($user_type) || empty($full_name)) {
        $error = 'All required fields must be filled';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } else {
        // Check if username or email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        
        if ($stmt->fetch()) {
            $error = 'Username or email already exists';
        } else {
            // Insert new user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password, user_type, full_name, phone, address) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            if ($stmt->execute([$username, $email, $hashed_password, $user_type, $full_name, $phone, $address])) {
                $success = 'Account created successfully! You can now login.';
            } else {
                $error = 'Error creating account. Please try again.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join CartHub - Sign Up</title>
    <link rel="stylesheet" href="css/modern_style.css">
    <link rel="icon" href="images/carthub-logo.png" type="image/png">
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <a href="index.php" class="logo">
                    <img src="images/carthub-logo.png" alt="CartHub Logo">
                    CartHub
                </a>
                <nav>
                    <ul class="nav-links">
                        <li><a href="index.php">Home</a></li>
                        <li><a href="buyer/login.php">Buyer Login</a></li>
                        <li><a href="vendor/login.php">Vendor Login</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <div class="form-container" style="max-width: 600px;">
            <div style="text-align: center; margin-bottom: 2rem;">
                <div style="width: 80px; height: 80px; background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-size: 2rem; color: white;">
                    ✨
                </div>
                <h2 style="color: var(--primary-color); margin-bottom: 0.5rem;">Join CartHub Today</h2>
                <p style="color: var(--text-secondary);">Create your account and start your amazing shopping journey</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                    <div style="margin-top: 1rem; text-align: center;">
                        <a href="buyer/login.php" class="btn btn-primary">Login as Buyer</a>
                        <a href="vendor/login.php" class="btn btn-secondary">Login as Vendor</a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!$success): ?>
            <form method="POST" id="signup-form">
                <!-- Account Type Selection -->
                <div class="form-group">
                    <label for="user_type">I want to join as: *</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 0.5rem;">
                        <label style="display: flex; align-items: center; padding: 1rem; border: 2px solid var(--border-color); border-radius: var(--border-radius); cursor: pointer; transition: var(--transition);" class="account-type-option" data-type="buyer">
                            <input type="radio" name="user_type" value="buyer" required style="margin-right: 0.75rem;" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] == 'buyer') ? 'checked' : ''; ?>>
                            <div>
                                <div style="font-size: 1.5rem; margin-bottom: 0.25rem;">🛒</div>
                                <div style="font-weight: 600; color: var(--text-primary);">Buyer</div>
                                <div style="font-size: 0.875rem; color: var(--text-secondary);">Shop amazing products</div>
                            </div>
                        </label>
                        <label style="display: flex; align-items: center; padding: 1rem; border: 2px solid var(--border-color); border-radius: var(--border-radius); cursor: pointer; transition: var(--transition);" class="account-type-option" data-type="vendor">
                            <input type="radio" name="user_type" value="vendor" required style="margin-right: 0.75rem;" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] == 'vendor') ? 'checked' : ''; ?>>
                            <div>
                                <div style="font-size: 1.5rem; margin-bottom: 0.25rem;">🏪</div>
                                <div style="font-weight: 600; color: var(--text-primary);">Vendor</div>
                                <div style="font-size: 0.875rem; color: var(--text-secondary);">Sell your products</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Personal Information -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="full_name">Full Name *</label>
                        <input type="text" name="full_name" id="full_name" value="<?php echo $_POST['full_name'] ?? ''; ?>" required placeholder="Enter your full name">
                    </div>

                    <div class="form-group">
                        <label for="username">Username *</label>
                        <input type="text" name="username" id="username" value="<?php echo $_POST['username'] ?? ''; ?>" required placeholder="Choose a username">
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">Email Address *</label>
                    <input type="email" name="email" id="email" value="<?php echo $_POST['email'] ?? ''; ?>" required placeholder="Enter your email address">
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" name="phone" id="phone" value="<?php echo $_POST['phone'] ?? ''; ?>" placeholder="Enter your phone number">
                </div>

                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea name="address" id="address" placeholder="Enter your address (optional)"><?php echo $_POST['address'] ?? ''; ?></textarea>
                </div>

                <!-- Password Section -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="password">Password *</label>
                        <input type="password" name="password" id="password" required placeholder="Create a password">
                        <small style="color: var(--text-secondary);">Minimum 6 characters</small>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm Password *</label>
                        <input type="password" name="confirm_password" id="confirm_password" required placeholder="Confirm your password">
                    </div>
                </div>

                <!-- Terms and Conditions -->
                <div style="margin: 1.5rem 0;">
                    <label style="display: flex; align-items: flex-start; font-size: 0.875rem; color: var(--text-secondary);">
                        <input type="checkbox" required style="margin-right: 0.5rem; margin-top: 0.125rem;">
                        I agree to CartHub's <a href="#" style="color: var(--primary-color);">Terms of Service</a> and <a href="#" style="color: var(--primary-color);">Privacy Policy</a>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; font-size: 1.125rem; padding: 1rem;">Create My Account</button>
            </form>

            <!-- Login Links -->
            <div style="text-align: center; margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--border-color);">
                <p style="color: var(--text-secondary); margin-bottom: 1rem;">Already have an account?</p>
                <div style="display: flex; gap: 1rem; justify-content: center;">
                    <a href="buyer/login.php" class="btn btn-secondary">Login as Buyer</a>
                    <a href="vendor/login.php" class="btn btn-secondary">Login as Vendor</a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Benefits Section -->
            <div style="margin-top: 2rem; padding: 2rem; background: var(--light-color); border-radius: var(--border-radius);">
                <h4 style="color: var(--text-primary); margin-bottom: 1.5rem; text-align: center;">Why Choose CartHub?</h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
                    <div style="text-align: center;">
                        <div style="font-size: 2rem; margin-bottom: 0.5rem;">🛒</div>
                        <div style="font-weight: 600; margin-bottom: 0.5rem;">For Buyers</div>
                        <div style="font-size: 0.875rem; color: var(--text-secondary);">Access thousands of quality products from trusted vendors</div>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 2rem; margin-bottom: 0.5rem;">🏪</div>
                        <div style="font-weight: 600; margin-bottom: 0.5rem;">For Vendors</div>
                        <div style="font-size: 0.875rem; color: var(--text-secondary);">Reach customers worldwide and grow your business</div>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 2rem; margin-bottom: 0.5rem;">🔒</div>
                        <div style="font-weight: 600; margin-bottom: 0.5rem;">Secure Platform</div>
                        <div style="font-size: 0.875rem; color: var(--text-secondary);">Advanced security for safe transactions</div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2025 CartHub. Join thousands of satisfied users.</p>
        </div>
    </footer>

    <script>
        // Enhanced form interactions
        document.addEventListener('DOMContentLoaded', function() {
            const accountTypeOptions = document.querySelectorAll('.account-type-option');
            const radioInputs = document.querySelectorAll('input[name="user_type"]');
            
            // Handle account type selection styling
            radioInputs.forEach(radio => {
                radio.addEventListener('change', function() {
                    accountTypeOptions.forEach(option => {
                        option.style.borderColor = 'var(--border-color)';
                        option.style.background = 'white';
                    });
                    
                    if (this.checked) {
                        const parentOption = this.closest('.account-type-option');
                        parentOption.style.borderColor = 'var(--primary-color)';
                        parentOption.style.background = 'rgba(99, 102, 241, 0.05)';
                    }
                });
            });
            
            // Password strength indicator
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;
                
                if (password.length >= 6) strength++;
                if (password.match(/[a-z]/)) strength++;
                if (password.match(/[A-Z]/)) strength++;
                if (password.match(/[0-9]/)) strength++;
                if (password.match(/[^a-zA-Z0-9]/)) strength++;
                
                // You can add visual strength indicator here
            });
            
            // Confirm password validation
            confirmPasswordInput.addEventListener('input', function() {
                const password = passwordInput.value;
                const confirmPassword = this.value;
                
                if (confirmPassword && password !== confirmPassword) {
                    this.style.borderColor = 'var(--danger-color)';
                } else {
                    this.style.borderColor = 'var(--border-color)';
                }
            });
        });
    </script>
</body>
</html>