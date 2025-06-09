<?php
require_once '../config/database.php';

// If already logged in as buyer, redirect to dashboard
if (isLoggedIn() && getUserType() === 'buyer') {
    header('Location: dashboard.php');
    exit();
}

$error = '';

if ($_POST) {
    $username = sanitize_input($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND user_type = 'buyer' AND status = 'active'");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_type'] = $user['user_type'];
            $_SESSION['full_name'] = $user['full_name'];
            header('Location: dashboard.php');
            exit();
        } else {
            $error = 'Invalid username or password';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buyer Login - CartHub</title>
    <link rel="stylesheet" href="../css/modern_style.css">
    <link rel="icon" href="../images/carthub-logo.png" type="image/png">
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
                        <li><a href="../index.php">Home</a></li>
                        <li><a href="../signup.php">Sign Up</a></li>
                        <li><a href="../vendor/login.php">Vendor Login</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <div class="form-container">
            <div style="text-align: center; margin-bottom: 2rem;">
                <div style="width: 80px; height: 80px; background: linear-gradient(135deg, var(--primary-color), var(--success-color)); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-size: 2rem; color: white;">
                    🛒
                </div>
                <h2 style="color: var(--primary-color); margin-bottom: 0.5rem;">Welcome Back!</h2>
                <p style="color: var(--text-secondary);">Login to your CartHub account and start shopping</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="username">Username or Email</label>
                    <input type="text" name="username" id="username" required placeholder="Enter your username or email" value="<?php echo $_POST['username'] ?? ''; ?>">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" name="password" id="password" required placeholder="Enter your password">
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                    <label style="display: flex; align-items: center; font-size: 0.875rem; color: var(--text-secondary);">
                        <input type="checkbox" name="remember" style="margin-right: 0.5rem;">
                        Remember me
                    </label>
                    <a href="forgot_password.php" style="color: var(--primary-color); text-decoration: none; font-size: 0.875rem;">Forgot Password?</a>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; font-size: 1.125rem; padding: 1rem;">Login to Shop</button>
            </form>

            <div style="text-align: center; margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--border-color);">
                <p style="color: var(--text-secondary); margin-bottom: 1rem;">Don't have an account?</p>
                <a href="../signup.php" class="btn btn-secondary" style="width: 100%; margin-bottom: 1rem;">Create Buyer Account</a>
                <p style="color: var(--text-secondary); font-size: 0.875rem;">
                    Are you a vendor? <a href="../vendor/login.php" style="color: var(--primary-color); font-weight: 600;">Login here</a>
                </p>
            </div>

            <!-- Features for Buyers -->
            <div style="margin-top: 2rem; padding: 1.5rem; background: var(--light-color); border-radius: var(--border-radius);">
                <h4 style="color: var(--text-primary); margin-bottom: 1rem; text-align: center;">Why Shop with CartHub?</h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; text-align: center;">
                    <div>
                        <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">🔒</div>
                        <div style="font-size: 0.875rem; color: var(--text-secondary);">Secure Shopping</div>
                    </div>
                    <div>
                        <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">🚚</div>
                        <div style="font-size: 0.875rem; color: var(--text-secondary);">Fast Delivery</div>
                    </div>
                    <div>
                        <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">💎</div>
                        <div style="font-size: 0.875rem; color: var(--text-secondary);">Quality Products</div>
                    </div>
                    <div>
                        <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">🎯</div>
                        <div style="font-size: 0.875rem; color: var(--text-secondary);">Best Prices</div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2025 CartHub. Your trusted shopping destination.</p>
        </div>
    </footer>

    <script src="../js/script.js"></script>
</body>
</html>