<?php
require_once '../config/database.php';
requireLogin('buyer');

$buyer_id = $_SESSION['user_id'];

// Get cart count for header
$cart_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM cart WHERE buyer_id = ?");
$cart_count_stmt->execute([$buyer_id]);
$cart_count = $cart_count_stmt->fetchColumn();

// Handle filter
$status_filter = $_GET['status'] ?? 'all';
$time_filter = $_GET['time'] ?? 'all';

// Build query
$query = "
    SELECT o.*, COUNT(oi.id) as item_count
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.buyer_id = ?
";

$params = [$buyer_id];

if ($status_filter !== 'all') {
    $query .= " AND o.order_status = ?";
    $params[] = $status_filter;
}

if ($time_filter !== 'all') {
    switch ($time_filter) {
        case 'last_30_days':
            $query .= " AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
        case 'last_6_months':
            $query .= " AND o.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
            break;
        case 'this_year':
            $query .= " AND YEAR(o.created_at) = YEAR(CURDATE())";
            break;
    }
}

$query .= " GROUP BY o.id ORDER BY o.created_at DESC";

$orders_stmt = $pdo->prepare($query);
$orders_stmt->execute($params);
$orders = $orders_stmt->fetchAll();

// Get order statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN order_status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
        SUM(CASE WHEN order_status = 'delivered' THEN 1 ELSE 0 END) as completed_orders,
        SUM(total_amount) as total_spent
    FROM orders
    WHERE buyer_id = ?
";
$stats_stmt = $pdo->prepare($stats_query);
$stats_stmt->execute([$buyer_id]);
$stats = $stats_stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - CartHub</title>
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
                        <li><a href="orders.php" class="active">My Orders</a></li>
                        <li><a href="wishlist.php">Wishlist</a></li>
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
                <h1 class="dashboard-title">My Orders</h1>
                <p class="dashboard-subtitle">Track and manage your order history</p>
            </div>

            <!-- Order Statistics -->
            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($stats['total_orders']); ?></div>
                    <div class="stat-label">Total Orders</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($stats['pending_orders']); ?></div>
                    <div class="stat-label">Pending Orders</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($stats['completed_orders']); ?></div>
                    <div class="stat-label">Completed Orders</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">$<?php echo number_format($stats['total_spent'] ?? 0, 2); ?></div>
                    <div class="stat-label">Total Spent</div>
                </div>
            </div>

            <!-- Filter Options -->
            <div class="filter-container">
                <form method="GET" action="orders.php" class="filter-form">
                    <div class="filter-options">
                        <div class="filter-group">
                            <label for="status">Order Status</label>
                            <select name="status" id="status" onchange="this.form.submit()">
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Orders</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="shipped" <?php echo $status_filter === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                <option value="delivered" <?php echo $status_filter === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="time">Time Period</label>
                            <select name="time" id="time" onchange="this.form.submit()">
                                <option value="all" <?php echo $time_filter === 'all' ? 'selected' : ''; ?>>All Time</option>
                                <option value="last_30_days" <?php echo $time_filter === 'last_30_days' ? 'selected' : ''; ?>>Last 30 Days</option>
                                <option value="last_6_months" <?php echo $time_filter === 'last_6_months' ? 'selected' : ''; ?>>Last 6 Months</option>
                                <option value="this_year" <?php echo $time_filter === 'this_year' ? 'selected' : ''; ?>>This Year</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-secondary">Apply Filters</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Orders List -->
            <div class="orders-container">
                <?php if (!empty($orders)): ?>
                    <?php foreach ($orders as $order): ?>
                        <div class="order-card">
                            <div class="order-header">
                                <div class="order-id">
                                    <h3>Order #<?php echo $order['id']; ?></h3>
                                    <span class="order-date"><?php echo date('F j, Y', strtotime($order['created_at'])); ?></span>
                                </div>
                                <div class="order-status">
                                    <span class="status-badge <?php echo $order['order_status']; ?>">
                                        <?php echo ucfirst($order['order_status']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="order-summary">
                                <div class="order-info">
                                    <div class="info-item">
                                        <span class="info-label">Items</span>
                                        <span class="info-value"><?php echo $order['item_count']; ?> items</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Total</span>
                                        <span class="info-value price">$<?php echo number_format($order['total_amount'], 2); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Payment</span>
                                        <span class="info-value"><?php echo ucfirst($order['payment_method'] ?? 'Credit Card'); ?></span>
                                    </div>
                                </div>
                                
                                <div class="order-actions">
                                    <a href="order_detail.php?id=<?php echo $order['id']; ?>" class="btn btn-primary">View Details</a>
                                    <?php if ($order['order_status'] === 'delivered'): ?>
                                        <button class="btn btn-secondary" onclick="leaveReview(<?php echo $order['id']; ?>)">Leave Review</button>
                                    <?php endif; ?>
                                    <?php if ($order['order_status'] === 'pending'): ?>
                                        <button class="btn btn-danger" onclick="cancelOrder(<?php echo $order['id']; ?>)">Cancel Order</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if ($order['order_status'] === 'shipped'): ?>
                                <div class="tracking-info">
                                    <div class="tracking-header">
                                        <i class="fas fa-truck"></i>
                                        <span>Tracking Information</span>
                                    </div>
                                    <div class="tracking-details">
                                        <p>Tracking Number: <strong><?php echo $order['tracking_number'] ?? 'TRK'.str_pad($order['id'], 8, '0', STR_PAD_LEFT); ?></strong></p>
                                        <p>Estimated Delivery: <strong><?php echo date('F j, Y', strtotime($order['created_at'] . ' +5 days')); ?></strong></p>
                                        <a href="#" class="track-link">Track Package <i class="fas fa-external-link-alt"></i></a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-orders">
                        <div class="no-orders-icon">
                            <i class="fas fa-shopping-bag"></i>
                        </div>
                        <h2>No orders found</h2>
                        <p>You haven't placed any orders yet or no orders match your filter criteria.</p>
                        <a href="browse.php" class="btn btn-primary">Start Shopping</a>
                    </div>
                <?php endif; ?>
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
        function leaveReview(orderId) {
            window.location.href = `review.php?order_id=${orderId}`;
        }
        
        function cancelOrder(orderId) {
            if (confirm('Are you sure you want to cancel this order?')) {
                fetch('../api/order_actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=cancel&order_id=${orderId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Order cancelled successfully!');
                        window.location.reload();
                    } else {
                        alert(data.message || 'Failed to cancel order');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                });
            }
        }
    </script>

    <style>
        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .filter-container {
            background: #f9fafb;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        
        .filter-form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .filter-options {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            min-width: 200px;
        }
        
        .filter-group label {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-secondary);
        }
        
        .filter-group select {
            padding: 0.75rem;
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
            background-color: white;
        }
        
        .orders-container {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .order-card {
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .order-id h3 {
            margin: 0 0 0.25rem;
            font-size: 1.1rem;
        }
        
        .order-date {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .status-badge.pending {
            background: rgba(245, 158, 11, 0.1);
            color: #d97706;
        }
        
        .status-badge.confirmed {
            background: rgba(59, 130, 246, 0.1);
            color: #2563eb;
        }
        
        .status-badge.shipped {
            background: rgba(139, 92, 246, 0.1);
            color: #7c3aed;
        }
        
        .status-badge.delivered {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
        }
        
        .status-badge.cancelled {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
        }
        
        .order-summary {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .order-info {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 0.25rem;
        }
        
        .info-value {
            font-weight: 500;
        }
        
        .info-value.price {
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .order-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .order-actions .btn {
            font-size: 0.875rem;
            padding: 0.5rem 1rem;
        }
        
        .tracking-info {
            background: #f8fafc;
            padding: 1.5rem;
            border-top: 1px solid var(--border-color);
        }
        
        .tracking-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .tracking-details p {
            margin: 0.5rem 0;
            font-size: 0.9rem;
        }
        
        .track-link {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }
        
        .track-link:hover {
            text-decoration: underline;
        }
        
        .no-orders {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .no-orders-icon {
            font-size: 5rem;
            color: var(--text-secondary);
            opacity: 0.3;
            margin-bottom: 1.5rem;
        }
        
        .no-orders h2 {
            margin-bottom: 1rem;
        }
        
        .no-orders p {
            color: var(--text-secondary);
            margin-bottom: 2rem;
        }
        
        @media (max-width: 768px) {
            .filter-options {
                flex-direction: column;
            }
            
            .filter-group {
                width: 100%;
            }
            
            .order-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .order-summary {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .order-info {
                width: 100%;
                justify-content: space-between;
            }
            
            .order-actions {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</body>
</html>