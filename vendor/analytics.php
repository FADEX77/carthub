<?php
require_once '../config/database.php';
requireLogin('vendor');

$vendor_id = $_SESSION['user_id'];

// Date range filter
$date_range = $_GET['range'] ?? '30';
$start_date = date('Y-m-d', strtotime("-{$date_range} days"));
$end_date = date('Y-m-d');

if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
    $start_date = $_GET['start_date'];
    $end_date = $_GET['end_date'];
}

// Sales Analytics
$sales_query = "
    SELECT 
        DATE(o.created_at) as date,
        COUNT(DISTINCT o.id) as orders,
        SUM(oi.quantity) as items_sold,
        SUM(oi.price * oi.quantity) as revenue
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.vendor_id = ? AND DATE(o.created_at) BETWEEN ? AND ?
    GROUP BY DATE(o.created_at)
    ORDER BY date DESC
";
$sales_stmt = $pdo->prepare($sales_query);
$sales_stmt->execute([$vendor_id, $start_date, $end_date]);
$daily_sales = $sales_stmt->fetchAll();

// Product Performance
$product_performance_query = "
    SELECT 
        p.id, p.name, p.price, p.image_path,
        COUNT(oi.id) as order_count,
        SUM(oi.quantity) as total_sold,
        SUM(oi.price * oi.quantity) as revenue,
        AVG(oi.price * oi.quantity) as avg_order_value
    FROM products p
    LEFT JOIN order_items oi ON p.id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.id
    WHERE p.vendor_id = ? AND (o.created_at IS NULL OR DATE(o.created_at) BETWEEN ? AND ?)
    GROUP BY p.id
    ORDER BY revenue DESC
    LIMIT 10
";
$product_stmt = $pdo->prepare($product_performance_query);
$product_stmt->execute([$vendor_id, $start_date, $end_date]);
$top_products = $product_stmt->fetchAll();

// Customer Analytics
$customer_query = "
    SELECT 
        u.id, u.full_name, u.email,
        COUNT(DISTINCT o.id) as order_count,
        SUM(oi.price * oi.quantity) as total_spent,
        MAX(o.created_at) as last_order
    FROM users u
    JOIN orders o ON u.id = o.buyer_id
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.vendor_id = ? AND DATE(o.created_at) BETWEEN ? AND ?
    GROUP BY u.id
    ORDER BY total_spent DESC
    LIMIT 10
";
$customer_stmt = $pdo->prepare($customer_query);
$customer_stmt->execute([$vendor_id, $start_date, $end_date]);
$top_customers = $customer_stmt->fetchAll();

// Summary Statistics
$summary_query = "
    SELECT 
        COUNT(DISTINCT o.id) as total_orders,
        SUM(oi.quantity) as total_items,
        SUM(oi.price * oi.quantity) as total_revenue,
        AVG(oi.price * oi.quantity) as avg_order_value,
        COUNT(DISTINCT o.buyer_id) as unique_customers
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.vendor_id = ? AND DATE(o.created_at) BETWEEN ? AND ?
";
$summary_stmt = $pdo->prepare($summary_query);
$summary_stmt->execute([$vendor_id, $start_date, $end_date]);
$summary = $summary_stmt->fetch();

// Category Performance
$category_query = "
    SELECT 
        p.category,
        COUNT(oi.id) as orders,
        SUM(oi.quantity) as items_sold,
        SUM(oi.price * oi.quantity) as revenue
    FROM products p
    JOIN order_items oi ON p.id = oi.product_id
    JOIN orders o ON oi.order_id = o.id
    WHERE p.vendor_id = ? AND DATE(o.created_at) BETWEEN ? AND ?
    GROUP BY p.category
    ORDER BY revenue DESC
";
$category_stmt = $pdo->prepare($category_query);
$category_stmt->execute([$vendor_id, $start_date, $end_date]);
$category_performance = $category_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - CartHub Vendor</title>
    <link rel="stylesheet" href="../css/modern_style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                        <li><a href="analytics.php" class="active">Analytics</a></li>
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
                <h1 class="dashboard-title">📊 Sales Analytics</h1>
                <p class="dashboard-subtitle">Detailed insights into your store performance</p>
            </div>

            <!-- Date Range Filter -->
            <div class="search-filters">
                <form method="GET">
                    <div style="display: grid; grid-template-columns: auto auto auto auto auto; gap: 1rem; align-items: end;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>Quick Range</label>
                            <select name="range" onchange="this.form.submit()">
                                <option value="7" <?php echo $date_range === '7' ? 'selected' : ''; ?>>Last 7 days</option>
                                <option value="30" <?php echo $date_range === '30' ? 'selected' : ''; ?>>Last 30 days</option>
                                <option value="90" <?php echo $date_range === '90' ? 'selected' : ''; ?>>Last 90 days</option>
                                <option value="365" <?php echo $date_range === '365' ? 'selected' : ''; ?>>Last year</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>Start Date</label>
                            <input type="date" name="start_date" value="<?php echo $start_date; ?>">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>End Date</label>
                            <input type="date" name="end_date" value="<?php echo $end_date; ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">Apply Filter</button>
                        <a href="analytics.php" class="btn btn-secondary">Reset</a>
                    </div>
                </form>
            </div>

            <!-- Summary Cards -->
            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-number">$<?php echo number_format($summary['total_revenue'] ?? 0, 2); ?></div>
                    <div class="stat-label">Total Revenue</div>
                    <div style="font-size: 0.75rem; color: var(--success-color); margin-top: 0.25rem;">
                        <?php echo $summary['total_orders'] ?? 0; ?> orders
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($summary['total_items'] ?? 0); ?></div>
                    <div class="stat-label">Items Sold</div>
                    <div style="font-size: 0.75rem; color: var(--primary-color); margin-top: 0.25rem;">
                        Avg: $<?php echo number_format($summary['avg_order_value'] ?? 0, 2); ?>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($summary['unique_customers'] ?? 0); ?></div>
                    <div class="stat-label">Unique Customers</div>
                    <div style="font-size: 0.75rem; color: var(--info-color); margin-top: 0.25rem;">
                        Customer base
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($summary['total_orders'] ?? 0); ?></div>
                    <div class="stat-label">Total Orders</div>
                    <div style="font-size: 0.75rem; color: var(--warning-color); margin-top: 0.25rem;">
                        Order volume
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin: 2rem 0;">
                <!-- Sales Trend Chart -->
                <div class="table-container">
                    <div class="table-header">
                        <h3 class="table-title">📈 Sales Trend</h3>
                    </div>
                    <div style="padding: 2rem;">
                        <canvas id="salesTrendChart" width="400" height="200"></canvas>
                    </div>
                </div>

                <!-- Category Performance -->
                <div class="table-container">
                    <div class="table-header">
                        <h3 class="table-title">🏷️ Category Performance</h3>
                    </div>
                    <div style="padding: 1.5rem;">
                        <canvas id="categoryChart" width="300" height="300"></canvas>
                    </div>
                </div>
            </div>

            <!-- Product Performance -->
            <div class="table-container" style="margin: 2rem 0;">
                <div class="table-header">
                    <h3 class="table-title">🏆 Top Performing Products</h3>
                </div>
                <?php if (!empty($top_products)): ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: var(--light-color);">
                                <th style="padding: 1rem; text-align: left; border-bottom: 1px solid var(--border-color);">Product</th>
                                <th style="padding: 1rem; text-align: center; border-bottom: 1px solid var(--border-color);">Orders</th>
                                <th style="padding: 1rem; text-align: center; border-bottom: 1px solid var(--border-color);">Sold</th>
                                <th style="padding: 1rem; text-align: center; border-bottom: 1px solid var(--border-color);">Revenue</th>
                                <th style="padding: 1rem; text-align: center; border-bottom: 1px solid var(--border-color);">Avg Order</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_products as $product): ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 1rem;">
                                    <div style="display: flex; align-items: center; gap: 1rem;">
                                        <img src="<?php echo $product['image_path'] ? '../uploads/products/' . $product['image_path'] : '/placeholder.svg?height=50&width=50'; ?>" 
                                             alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                             style="width: 50px; height: 50px; object-fit: cover; border-radius: var(--border-radius);">
                                        <div>
                                            <div style="font-weight: 600;"><?php echo htmlspecialchars($product['name']); ?></div>
                                            <div style="color: var(--text-secondary); font-size: 0.875rem;">$<?php echo number_format($product['price'], 2); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td style="padding: 1rem; text-align: center; font-weight: 600;"><?php echo $product['order_count'] ?? 0; ?></td>
                                <td style="padding: 1rem; text-align: center; font-weight: 600;"><?php echo $product['total_sold'] ?? 0; ?></td>
                                <td style="padding: 1rem; text-align: center; font-weight: 600; color: var(--success-color);">$<?php echo number_format($product['revenue'] ?? 0, 2); ?></td>
                                <td style="padding: 1rem; text-align: center; font-weight: 600;">$<?php echo number_format($product['avg_order_value'] ?? 0, 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div style="padding: 3rem; text-align: center;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">📊</div>
                    <h4 style="color: var(--text-secondary);">No sales data available</h4>
                    <p style="color: var(--text-secondary);">Product performance will appear here once you start making sales.</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Top Customers -->
            <div class="table-container" style="margin: 2rem 0;">
                <div class="table-header">
                    <h3 class="table-title">👥 Top Customers</h3>
                </div>
                <?php if (!empty($top_customers)): ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: var(--light-color);">
                                <th style="padding: 1rem; text-align: left; border-bottom: 1px solid var(--border-color);">Customer</th>
                                <th style="padding: 1rem; text-align: center; border-bottom: 1px solid var(--border-color);">Orders</th>
                                <th style="padding: 1rem; text-align: center; border-bottom: 1px solid var(--border-color);">Total Spent</th>
                                <th style="padding: 1rem; text-align: center; border-bottom: 1px solid var(--border-color);">Last Order</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_customers as $customer): ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 1rem;">
                                    <div>
                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($customer['full_name']); ?></div>
                                        <div style="color: var(--text-secondary); font-size: 0.875rem;"><?php echo htmlspecialchars($customer['email']); ?></div>
                                    </div>
                                </td>
                                <td style="padding: 1rem; text-align: center; font-weight: 600;"><?php echo $customer['order_count']; ?></td>
                                <td style="padding: 1rem; text-align: center; font-weight: 600; color: var(--success-color);">$<?php echo number_format($customer['total_spent'], 2); ?></td>
                                <td style="padding: 1rem; text-align: center;"><?php echo date('M j, Y', strtotime($customer['last_order'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div style="padding: 3rem; text-align: center;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">👥</div>
                    <h4 style="color: var(--text-secondary);">No customer data available</h4>
                    <p style="color: var(--text-secondary);">Customer analytics will appear here once you start making sales.</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Export Options -->
            <div style="background: var(--light-color); padding: 2rem; border-radius: var(--border-radius); margin-top: 2rem; text-align: center;">
                <h4 style="margin-bottom: 1rem;">📊 Export Analytics</h4>
                <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">Download your analytics data for external analysis</p>
                <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                    <button class="btn btn-primary" onclick="exportData('csv')">📄 Export as CSV</button>
                    <button class="btn btn-secondary" onclick="exportData('pdf')">📋 Export as PDF</button>
                    <button class="btn" style="background: var(--success-color); color: white;" onclick="exportData('excel')">📊 Export as Excel</button>
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

    <script>
        // Sales Trend Chart
        const salesCtx = document.getElementById('salesTrendChart').getContext('2d');
        const salesData = <?php echo json_encode(array_reverse($daily_sales)); ?>;
        
        new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: salesData.map(item => new Date(item.date).toLocaleDateString()),
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

        // Category Performance Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryData = <?php echo json_encode($category_performance); ?>;
        
        new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: categoryData.map(item => item.category),
                datasets: [{
                    data: categoryData.map(item => parseFloat(item.revenue || 0)),
                    backgroundColor: [
                        '#6366f1', '#f59e0b', '#10b981', '#ef4444', '#8b5cf6',
                        '#06b6d4', '#f97316', '#84cc16', '#ec4899', '#6b7280'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });

        // Export functionality
        function exportData(format) {
            alert(`Export as ${format.toUpperCase()} functionality would be implemented here.`);
            // In a real implementation, this would trigger a server-side export
        }
    </script>
</body>
</html>