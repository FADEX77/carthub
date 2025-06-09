<?php
require_once '../config/database.php';
requireLogin('vendor');

$vendor_id = $_SESSION['user_id'];

// Get vendor statistics
$stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM products WHERE vendor_id = ?) as total_products,
        (SELECT COUNT(*) FROM products WHERE vendor_id = ? AND status = 'active') as active_products,
        (SELECT SUM(stock_quantity) FROM products WHERE vendor_id = ?) as total_inventory,
        (SELECT COUNT(*) FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE p.vendor_id = ?) as total_sales,
        (SELECT SUM(oi.price * oi.quantity) FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE p.vendor_id = ?) as total_revenue,
        (SELECT COUNT(*) FROM order_items oi JOIN products p ON oi.product_id = p.id JOIN orders o ON oi.order_id = o.id WHERE p.vendor_id = ? AND o.order_status = 'pending') as pending_orders,
        (SELECT AVG(oi.price * oi.quantity) FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE p.vendor_id = ?) as avg_order_value
";
$stats_stmt = $pdo->prepare($stats_query);
$stats_stmt->execute([$vendor_id, $vendor_id, $vendor_id, $vendor_id, $vendor_id, $vendor_id, $vendor_id]);
$stats = $stats_stmt->fetch();

// Get top selling products
$top_products_stmt = $pdo->prepare("
    SELECT p.name, p.price, p.image_path, SUM(oi.quantity) as total_sold, SUM(oi.price * oi.quantity) as revenue
    FROM products p
    JOIN order_items oi ON p.id = oi.product_id
    WHERE p.vendor_id = ?
    GROUP BY p.id
    ORDER BY total_sold DESC
    LIMIT 5
");
$top_products_stmt->execute([$vendor_id]);
$top_products = $top_products_stmt->fetchAll();

// Get recent orders
$recent_orders_stmt = $pdo->prepare("
    SELECT o.id, o.total_amount, o.order_status, o.created_at, u.full_name as buyer_name, 
           oi.quantity, oi.price, p.name as product_name, p.image_path
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    JOIN users u ON o.buyer_id = u.id
    WHERE p.vendor_id = ?
    ORDER BY o.created_at DESC
    LIMIT 8
");
$recent_orders_stmt->execute([$vendor_id]);
$recent_orders = $recent_orders_stmt->fetchAll();

// Get low stock products
$low_stock_stmt = $pdo->prepare("
    SELECT name, stock_quantity, price, image_path
    FROM products 
    WHERE vendor_id = ? AND stock_quantity <= 10 AND status = 'active'
    ORDER BY stock_quantity ASC
    LIMIT 5
");
$low_stock_stmt->execute([$vendor_id]);
$low_stock = $low_stock_stmt->fetchAll();

// Get monthly sales data for chart
$monthly_sales_stmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(o.created_at, '%Y-%m') as month,
        SUM(oi.price * oi.quantity) as revenue,
        COUNT(DISTINCT o.id) as orders
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.vendor_id = ? AND o.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(o.created_at, '%Y-%m')
    ORDER BY month DESC
");
$monthly_sales_stmt->execute([$vendor_id]);
$monthly_sales = $monthly_sales_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Dashboard - CartHub</title>
    <link rel="stylesheet" href="../css/modern_style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="icon" href="../images/carthub-logo.png" type="image/png">
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
                        <li><a href="dashboard.php" class="active">Dashboard</a></li>
                        <li><a href="products.php">My Products</a></li>
                        <li><a href="add_product.php">Add Product</a></li>
                        <li><a href="orders.php">Orders</a></li>
                        <li><a href="analytics.php">Analytics</a></li>
                        <li><a href="settings.php">Settings</a></li>
                        <li><a href="../logout.php" class="btn btn-danger">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main class="dashboard">
        <div class="container">
            <div class="dashboard-header">
                <h1 class="dashboard-title">Vendor Dashboard</h1>
                <p class="dashboard-subtitle">Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>! Here's your store overview.</p>
            </div>

            <!-- Statistics Cards -->
            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($stats['total_products']); ?></div>
                    <div class="stat-label">Total Products</div>
                    <div style="font-size: 0.75rem; color: var(--success-color); margin-top: 0.25rem;">
                        <?php echo $stats['active_products']; ?> active
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">$<?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></div>
                    <div class="stat-label">Total Revenue</div>
                    <div style="font-size: 0.75rem; color: var(--primary-color); margin-top: 0.25rem;">
                        Avg: $<?php echo number_format($stats['avg_order_value'] ?? 0, 2); ?>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($stats['total_sales']); ?></div>
                    <div class="stat-label">Items Sold</div>
                    <div style="font-size: 0.75rem; color: var(--info-color); margin-top: 0.25rem;">
                        All time sales
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($stats['total_inventory']); ?></div>
                    <div class="stat-label">Total Inventory</div>
                    <div style="font-size: 0.75rem; color: var(--warning-color); margin-top: 0.25rem;">
                        Items in stock
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($stats['pending_orders']); ?></div>
                    <div class="stat-label">Pending Orders</div>
                    <div style="font-size: 0.75rem; color: var(--danger-color); margin-top: 0.25rem;">
                        Need attention
                    </div>
                </div>
            </div>

            <!-- Charts and Analytics -->
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin: 2rem 0;">
                <!-- Sales Chart -->
                <div class="table-container">
                    <div class="table-header">
                        <h3 class="table-title">Sales Overview (Last 6 Months)</h3>
                    </div>
                    <div style="padding: 2rem;">
                        <canvas id="salesChart" width="400" height="200"></canvas>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="table-container">
                    <div class="table-header">
                        <h3 class="table-title">Quick Actions</h3>
                    </div>
                    <div style="padding: 1.5rem;">
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <a href="add_product.php" class="btn btn-primary" style="text-align: center;">
                                📦 Add New Product
                            </a>
                            <a href="products.php" class="btn btn-secondary" style="text-align: center;">
                                📋 Manage Products
                            </a>
                            <a href="orders.php" class="btn btn-success" style="text-align: center;">
                                🚚 View Orders
                            </a>
                            <a href="analytics.php" class="btn" style="background: var(--warning-color); color: white; text-align: center;">
                                📊 View Analytics
                            </a>
                            <a href="settings.php" class="btn" style="background: var(--info-color); color: white; text-align: center;">
                                ⚙️ Store Settings
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Products and Recent Orders -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin: 2rem 0;">
                <!-- Top Selling Products -->
                <div class="table-container">
                    <div class="table-header">
                        <h3 class="table-title">🏆 Top Selling Products</h3>
                    </div>
                    <?php if (!empty($top_products)): ?>
                    <div style="padding: 1rem;">
                        <?php foreach ($top_products as $product): ?>
                        <div style="display: flex; align-items: center; padding: 1rem; border-bottom: 1px solid var(--border-color); transition: var(--transition);" onmouseover="this.style.background='var(--light-color)'" onmouseout="this.style.background='white'">
                            <img src="<?php echo $product['image_path'] ? '../uploads/products/' . $product['image_path'] : '/placeholder.svg?height=60&width=60'; ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                 style="width: 60px; height: 60px; object-fit: cover; border-radius: var(--border-radius); margin-right: 1rem;">
                            <div style="flex: 1;">
                                <div style="font-weight: 600; margin-bottom: 0.25rem;"><?php echo htmlspecialchars($product['name']); ?></div>
                                <div style="color: var(--primary-color); font-weight: 600;">$<?php echo number_format($product['price'], 2); ?></div>
                                <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                    <?php echo $product['total_sold']; ?> sold • $<?php echo number_format($product['revenue'], 2); ?> revenue
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div style="padding: 3rem; text-align: center;">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">📈</div>
                        <h4 style="color: var(--text-secondary); margin-bottom: 1rem;">No sales data yet</h4>
                        <p style="color: var(--text-secondary);">Your top selling products will appear here once you start making sales.</p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Orders -->
                <div class="table-container">
                    <div class="table-header">
                        <h3 class="table-title">🛒 Recent Orders</h3>
                    </div>
                    <?php if (!empty($recent_orders)): ?>
                    <div style="max-height: 400px; overflow-y: auto;">
                        <?php foreach ($recent_orders as $order): ?>
                        <div style="display: flex; align-items: center; padding: 1rem; border-bottom: 1px solid var(--border-color); transition: var(--transition);" onmouseover="this.style.background='var(--light-color)'" onmouseout="this.style.background='white'">
                            <img src="<?php echo $order['image_path'] ? '../uploads/products/' . $order['image_path'] : '/placeholder.svg?height=50&width=50'; ?>" 
                                 alt="<?php echo htmlspecialchars($order['product_name']); ?>" 
                                 style="width: 50px; height: 50px; object-fit: cover; border-radius: var(--border-radius); margin-right: 1rem;">
                            <div style="flex: 1;">
                                <div style="font-weight: 600; margin-bottom: 0.25rem;">Order #<?php echo $order['id']; ?></div>
                                <div style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 0.25rem;">
                                    <?php echo htmlspecialchars($order['buyer_name']); ?>
                                </div>
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span style="color: var(--primary-color); font-weight: 600;">$<?php echo number_format($order['price'] * $order['quantity'], 2); ?></span>
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
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div style="padding: 3rem; text-align: center;">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">📦</div>
                        <h4 style="color: var(--text-secondary); margin-bottom: 1rem;">No orders yet</h4>
                        <p style="color: var(--text-secondary);">Orders for your products will appear here.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Low Stock Alert -->
            <?php if (!empty($low_stock)): ?>
            <div class="table-container" style="margin: 2rem 0;">
                <div class="table-header" style="background: rgba(245, 158, 11, 0.1); border-left: 4px solid var(--warning-color);">
                    <h3 class="table-title" style="color: var(--warning-color);">⚠️ Low Stock Alert</h3>
                </div>
                <div style="padding: 1rem;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem;">
                        <?php foreach ($low_stock as $product): ?>
                        <div style="display: flex; align-items: center; padding: 1rem; background: var(--light-color); border-radius: var(--border-radius); border: 1px solid var(--warning-color);">
                            <img src="<?php echo $product['image_path'] ? '../uploads/products/' . $product['image_path'] : '/placeholder.svg?height=50&width=50'; ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                 style="width: 50px; height: 50px; object-fit: cover; border-radius: var(--border-radius); margin-right: 1rem;">
                            <div>
                                <div style="font-weight: 600; margin-bottom: 0.25rem;"><?php echo htmlspecialchars($product['name']); ?></div>
                                <div style="color: var(--warning-color); font-weight: 600;">Only <?php echo $product['stock_quantity']; ?> left</div>
                                <div style="font-size: 0.875rem; color: var(--text-secondary);">$<?php echo number_format($product['price'], 2); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="text-align: center; margin-top: 1rem;">
                        <a href="products.php" class="btn btn-warning">Manage Stock Levels</a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <div class="container">
            <div class="footer-bottom">
                <p>&copy; 2025 CartHub Vendor Portal. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // Sales Chart
        const ctx = document.getElementById('salesChart').getContext('2d');
        const salesData = <?php echo json_encode(array_reverse($monthly_sales)); ?>;
        
        const chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: salesData.map(item => {
                    const date = new Date(item.month + '-01');
                    return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
                }),
                datasets: [{
                    label: 'Revenue ($)',
                    data: salesData.map(item => parseFloat(item.revenue || 0)),
                    borderColor: 'rgb(99, 102, 241)',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Orders',
                    data: salesData.map(item => parseInt(item.orders || 0)),
                    borderColor: 'rgb(245, 158, 11)',
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                    tension: 0.4,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Revenue ($)'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Orders'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });
    </script>
</body>
</html>