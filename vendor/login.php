<?php
require_once '../config/database.php';

// If already logged in as vendor, redirect to dashboard
if (isLoggedIn() && getUserType() === 'vendor') {
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
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND user_type = 'vendor' AND status = 'active'");
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
            $error = 'Invalid vendor credentials';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Login - CartHub</title>
    <link rel="stylesheet" href="../css/modern_style.css">
    <link rel="icon" href="../images/carthub-logo.png" type="image/png">
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
                        <li><a href="../index.php">Home</a></li>
                        <li><a href="../signup.php">Sign Up</a></li>
                        <li><a href="../buyer/login.php">Buyer Login</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <div class="form-container">
            <div style="text-align: center; margin-bottom: 2rem;">
                <div style="width: 80px; height: 80px; background: linear-gradient(135deg, var(--secondary-color), var(--primary-color)); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-size: 2rem; color: white;">
                    🏪
                </div>
                <h2 style="color: var(--primary-color); margin-bottom: 0.5rem;">Vendor Portal</h2>
                <p style="color: var(--text-secondary);">Manage your store and grow your business</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="username">Vendor Username</label>
                    <input type="text" name="username" id="username" required placeholder="Enter your username">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" name="password" id="password" required placeholder="Enter your password">
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">Login to Dashboard</button>
            </form>

            <div style="text-align: center; margin-top: 2rem;">
                <p>Don't have a vendor account? <a href="../signup.php" style="color: var(--primary-color); font-weight: 600;">Sign up here</a></p>
            </div>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2025 CartHub Vendor Portal. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>