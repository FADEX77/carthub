<?php
require_once '../config/database.php';

// If already logged in as admin, redirect to dashboard
if (isLoggedIn() && getUserType() === 'admin') {
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
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND user_type = 'admin' AND status = 'active'");
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
            $error = 'Invalid admin credentials';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - CartHub</title>
    <link rel="stylesheet" href="../css/modern_style.css">
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <a href="../index.php" class="logo">
                    <img src="https://hebbkx1anhila5yf.public.blob.vercel-storage.com/carthub-high-resolution-logo-WapSYRWf3qJtbFbQ5lZK3eLO6PRZ5f.png" alt="CartHub Logo">
                    CartHub Admin
                </a>
                <nav>
                    <ul class="nav-links">
                        <li><a href="../index.php">Home</a></li>
                        <li><a href="../buyer/login.php">Buyer Login</a></li>
                        <li><a href="../vendor/login.php">Vendor Login</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <div class="form-container">
            <div style="text-align: center; margin-bottom: 2rem;">
                <div style="width: 80px; height: 80px; background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-size: 2rem; color: white;">
                    🔐
                </div>
                <h2 style="color: var(--primary-color); margin-bottom: 0.5rem;">Admin Portal</h2>
                <p style="color: var(--text-secondary);">Secure access to CartHub administration</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="username">Admin Username</label>
                    <input type="text" name="username" id="username" required placeholder="Enter admin username">
                </div>

                <div class="form-group">
                    <label for="password">Admin Password</label>
                    <input type="password" name="password" id="password" required placeholder="Enter admin password">
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">Access Admin Dashboard</button>
            </form>

            <div style="text-align: center; margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--border-color);">
                <p style="color: var(--text-secondary); margin-bottom: 1rem;">Default Admin Credentials:</p>
                <div style="background: var(--light-color); padding: 1rem; border-radius: var(--border-radius); font-family: monospace;">
                    <strong>Username:</strong> admin<br>
                    <strong>Password:</strong> admin123
                </div>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2024 CartHub Admin Portal. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>