<?php
require_once '../config/database.php';
requireLogin('admin');

// Get statistics
$stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM users WHERE user_type = 'buyer') as total_buyers,
        (SELECT COUNT(*) FROM users WHERE user_type = 'vendor') as total_vendors,
        (SELECT COUNT(*) FROM products) as total_products,
        (SELECT COUNT(*) FROM orders) as total_orders,
        (SELECT SUM(total_amount) FROM orders WHERE order_status != 'cancelled') as total_revenue,
        (SELECT COUNT(*) FROM orders WHERE order_status = 'pending') as pending_orders
";
$stats = $pdo->query($stats_query)->fetch();

// Get recent activities
$recent_users = $pdo->query("
    SELECT username, full_name, user_type, created_at 
    FROM users 
    WHERE user_type != 'admin' 
    ORDER BY created_at DESC 
    LIMIT 10
")->fetchAll();

$recent_products = $pdo->query("
    SELECT p.name, p.price, p.created_at, u.full_name as vendor_name
    FROM products p 
    JOIN users u ON p.vendor_id = u.id 
    ORDER BY p.created_at DESC 
    LIMIT 10
")->fetchAll();

$recent_orders = $pdo->query("
    SELECT o.id, o.total_amount, o.order_status, o.created_at, u.full_name as buyer_name
    FROM orders o 
    JOIN users u ON o.buyer_id = u.id 
    ORDER BY o.created_at DESC 
    LIMIT 10
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - CartHub</title>
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
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><a href="users.php">Manage Users</a></li>
                        <li><a href="products.php">Manage Products</a></li>
                        <li><a href="orders.php">Manage Orders</a></li>
                        <li><a href="../logout.php" class="btn btn-danger">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main class="dashboard">
        <div class="container">
            <div class="dashboard-header">
                <h1 class="dashboard-title">Admin Dashboard</h1>
                <p class="dashboard-subtitle">Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>! Monitor and manage your CartHub marketplace.</p>
            </div>

            <!-- Statistics Cards -->
            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($stats['total_buyers']); ?></div>
                    <div class="stat-label">Total Buyers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($stats['total_vendors']); ?></div>
                    <div class="stat-label">Total Vendors</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($stats['total_products']); ?></div>
                    <div class="stat-label">Total Products</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($stats['total_orders']); ?></div>
                    <div class="stat-label">Total Orders</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">$<?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($stats['pending_orders']); ?></div>
                    <div class="stat-label">Pending Orders</div>
                </div>
            </div>

            <!-- Recent Activities -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 2rem;">
                <!-- Recent Users -->
                <div class="table-container">
                    <div class="table-header">
                        <h3 class="table-title">Recent User Registrations</h3>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_users as $user): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($user['full_name']); ?></strong><br>
                                    <small style="color: var(--text-secondary);">@<?php echo htmlspecialchars($user['username']); ?></small>
                                </td>
                                <td>
                                    <span class="product-category" style="background: <?php echo $user['user_type'] === 'vendor' ? 'rgba(245, 158, 11, 0.1)' : 'rgba(59, 130, 246, 0.1)'; ?>; color: <?php echo $user['user_type'] === 'vendor' ? '#d97706' : '#2563eb'; ?>;">
                                        <?php echo ucfirst($user['user_type']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Recent Products -->
                <div class="table-container">
                    <div class="table-header">
                        <h3 class="table-title">Recent Products Added</h3>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Price</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_products as $product): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($product['name']); ?></strong><br>
                                    <small style="color: var(--text-secondary);">by <?php echo htmlspecialchars($product['vendor_name']); ?></small>
                                </td>
                                <td style="color: var(--primary-color); font-weight: 600;">$<?php echo number_format($product['price'], 2); ?></td>
                                <td><?php echo date('M j, Y', strtotime($product['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Orders -->
            <div class="table-container" style="margin-top: 2rem;">
                <div class="table-header">
                    <h3 class="table-title">Recent Orders</h3>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_orders as $order): ?>
                        <tr>
                            <td><strong>#<?php echo $order['id']; ?></strong></td>
                            <td><?php echo htmlspecialchars($order['buyer_name']); ?></td>
                            <td style="color: var(--primary-color); font-weight: 600;">$<?php echo number_format($order['total_amount'], 2); ?></td>
                            <td>
                                <span class="product-category" style="
                                    background: <?php 
                                        switch($order['order_status']) {
                                            case 'pending': echo 'rgba(245, 158, 11, 0.1)'; break;
                                            case 'confirmed': echo 'rgba(59, 130, 246, 0.1)'; break;
                                            case 'shipped': echo 'rgba(139, 92, 246, 0.1)'; break;
                                            case 'delivered': echo 'rgba(16, 185, 129, 0.1)'; break;
                                            case 'cancelled': echo 'rgba(239, 68, 68, 0.1)'; break;
                                        }
                                    ?>;
                                    color: <?php 
                                        switch($order['order_status']) {
                                            case 'pending': echo '#d97706'; break;
                                            case 'confirmed': echo '#2563eb'; break;
                                            case 'shipped': echo '#7c3aed'; break;
                                            case 'delivered': echo '#059669'; break;
                                            case 'cancelled': echo '#dc2626'; break;
                                        }
                                    ?>;">
                                    <?php echo ucfirst($order['order_status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                            <td>
                                <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn" style="padding: 0.5rem 1rem; font-size: 0.875rem;">View</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Quick Actions -->
            <div style="margin-top: 3rem; text-align: center;">
                <h3 style="margin-bottom: 2rem; color: var(--text-primary);">Quick Actions</h3>
                <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                    <a href="users.php" class="btn btn-primary">Manage Users</a>
                    <a href="products.php" class="btn btn-secondary">Manage Products</a>
                    <a href="orders.php" class="btn btn-success">View All Orders</a>
                    <a href="reports.php" class="btn" style="background: var(--warning-color); color: white;">Generate Reports</a>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <div class="footer-bottom">
                <p>&copy; 2024 CartHub Admin Panel. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="../js/script.js"></script>
</body>
</html>