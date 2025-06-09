<?php
require_once '../config/database.php';
requireLogin('vendor');

$vendor_id = $_SESSION['user_id'];
$message = '';

// Handle order status updates
if ($_POST && isset($_POST['action'])) {
    $action = $_POST['action'];
    $order_id = intval($_POST['order_id'] ?? 0);
    
    if ($action === 'update_status' && $order_id) {
        $new_status = sanitize_input($_POST['new_status']);
        $valid_statuses = ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'];
        
        if (in_array($new_status, $valid_statuses)) {
            // Verify this order belongs to vendor's products
            $verify_stmt = $pdo->prepare("
                SELECT COUNT(*) FROM orders o 
                JOIN order_items oi ON o.id = oi.order_id 
                JOIN products p ON oi.product_id = p.id 
                WHERE o.id = ? AND p.vendor_id = ?
            ");
            $verify_stmt->execute([$order_id, $vendor_id]);
            
            if ($verify_stmt->fetchColumn() > 0) {
                $update_stmt = $pdo->prepare("UPDATE orders SET order_status = ? WHERE id = ?");
                if ($update_stmt->execute([$new_status, $order_id])) {
                    $message = "Order status updated successfully!";
                }
            }
        }
    }
}

// Pagination and filtering
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';

// Build query
$where_conditions = ["p.vendor_id = ?"];
$params = [$vendor_id];

if ($status_filter) {
    $where_conditions[] = "o.order_status = ?";
    $params[] = $status_filter;
}

if ($search) {
    $where_conditions[] = "(o.id LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count
$count_sql = "
    SELECT COUNT(DISTINCT o.id) 
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    JOIN users u ON o.buyer_id = u.id
    WHERE $where_clause
";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_orders = $count_stmt->fetchColumn();
$total_pages = ceil($total_orders / $per_page);

// Get orders with details
$orders_sql = "
    SELECT DISTINCT
        o.id, o.total_amount, o.order_status, o.created_at, o.shipping_address,
        u.full_name as buyer_name, u.email as buyer_email, u.phone as buyer_phone,
        GROUP_CONCAT(CONCAT(p.name, ' (', oi.quantity, 'x)') SEPARATOR ', ') as products,
        SUM(oi.price * oi.quantity) as vendor_total
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    JOIN users u ON o.buyer_id = u.id
    WHERE $where_clause
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT $per_page OFFSET $offset
";
$orders_stmt = $pdo->prepare($orders_sql);
$orders_stmt->execute($params);
$orders = $orders_stmt->fetchAll();

// Get order statistics
$stats_query = "
    SELECT 
        COUNT(DISTINCT o.id) as total_orders,
        SUM(CASE WHEN o.order_status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
        SUM(CASE WHEN o.order_status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_orders,
        SUM(CASE WHEN o.order_status = 'shipped' THEN 1 ELSE 0 END) as shipped_orders,
        SUM(CASE WHEN o.order_status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
        SUM(oi.price * oi.quantity) as total_revenue
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.vendor_id = ?
";
$stats_stmt = $pdo->prepare($stats_query);
$stats_stmt->execute([$vendor_id]);
$order_stats = $stats_stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - CartHub Vendor</title>
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
                        <li><a href="orders.php" class="active">Orders</a></li>
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
                <h1 class="dashboard-title">🛒 Order Management</h1>
                <p class="dashboard-subtitle">Track and manage all your orders</p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>

            <!-- Order Statistics -->
            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($order_stats['total_orders']); ?></div>
                    <div class="stat-label">Total Orders</div>
                    <div style="font-size: 0.75rem; color: var(--success-color); margin-top: 0.25rem;">
                        $<?php echo number_format($order_stats['total_revenue'], 2); ?> revenue
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($order_stats['pending_orders']); ?></div>
                    <div class="stat-label">Pending Orders</div>
                    <div style="font-size: 0.75rem; color: var(--warning-color); margin-top: 0.25rem;">
                        Need attention
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($order_stats['confirmed_orders']); ?></div>
                    <div class="stat-label">Confirmed</div>
                    <div style="font-size: 0.75rem; color: var(--info-color); margin-top: 0.25rem;">
                        Ready to ship
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($order_stats['shipped_orders']); ?></div>
                    <div class="stat-label">Shipped</div>
                    <div style="font-size: 0.75rem; color: var(--primary-color); margin-top: 0.25rem;">
                        In transit
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($order_stats['delivered_orders']); ?></div>
                    <div class="stat-label">Delivered</div>
                    <div style="font-size: 0.75rem; color: var(--success-color); margin-top: 0.25rem;">
                        Completed
                    </div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="search-filters">
                <form method="GET">
                    <div style="display: grid; grid-template-columns: 2fr 1fr auto auto; gap: 1rem; align-items: end;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="search">Search Orders</label>
                            <input type="text" name="search" id="search" class="search-input" placeholder="Search by order ID, customer name, or email..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="status">Order Status</label>
                            <select name="status" id="status" class="search-input">
                                <option value="">All Statuses</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="shipped" <?php echo $status_filter === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                <option value="delivered" <?php echo $status_filter === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="orders.php" class="btn btn-secondary">Reset</a>
                    </div>
                </form>
            </div>

            <!-- Orders Table -->
            <div class="table-container">
                <div class="table-header">
                    <h3 class="table-title">📋 Order List</h3>
                    <div style="display: flex; gap: 1rem;">
                        <button class="btn btn-secondary" onclick="exportOrders()">📊 Export</button>
                        <button class="btn btn-primary" onclick="bulkActions()">⚡ Bulk Actions</button>
                    </div>
                </div>
                
                <?php if (!empty($orders)): ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: var(--light-color);">
                                <th style="padding: 1rem; text-align: left; border-bottom: 1px solid var(--border-color);">
                                    <input type="checkbox" id="select-all" onchange="toggleAllOrders(this)">
                                </th>
                                <th style="padding: 1rem; text-align: left; border-bottom: 1px solid var(--border-color);">Order Details</th>
                                <th style="padding: 1rem; text-align: left; border-bottom: 1px solid var(--border-color);">Customer</th>
                                <th style="padding: 1rem; text-align: left; border-bottom: 1px solid var(--border-color);">Products</th>
                                <th style="padding: 1rem; text-align: center; border-bottom: 1px solid var(--border-color);">Amount</th>
                                <th style="padding: 1rem; text-align: center; border-bottom: 1px solid var(--border-color);">Status</th>
                                <th style="padding: 1rem; text-align: center; border-bottom: 1px solid var(--border-color);">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                            <tr style="border-bottom: 1px solid var(--border-color);" class="order-row">
                                <td style="padding: 1rem;">
                                    <input type="checkbox" class="order-checkbox" value="<?php echo $order['id']; ?>">
                                </td>
                                <td style="padding: 1rem;">
                                    <div>
                                        <div style="font-weight: 600; margin-bottom: 0.25rem;">Order #<?php echo $order['id']; ?></div>
                                        <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                            <?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?>
                                        </div>
                                    </div>
                                </td>
                                <td style="padding: 1rem;">
                                    <div>
                                        <div style="font-weight: 600; margin-bottom: 0.25rem;"><?php echo htmlspecialchars($order['buyer_name']); ?></div>
                                        <div style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 0.25rem;"><?php echo htmlspecialchars($order['buyer_email']); ?></div>
                                        <?php if ($order['buyer_phone']): ?>
                                        <div style="font-size: 0.875rem; color: var(--text-secondary);"><?php echo htmlspecialchars($order['buyer_phone']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td style="padding: 1rem;">
                                    <div style="font-size: 0.875rem; max-width: 200px;">
                                        <?php echo htmlspecialchars($order['products']); ?>
                                    </div>
                                </td>
                                <td style="padding: 1rem; text-align: center;">
                                    <div style="font-weight: 600; color: var(--success-color);">$<?php echo number_format($order['vendor_total'], 2); ?></div>
                                </td>
                                <td style="padding: 1rem; text-align: center;">
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
                                <td style="padding: 1rem; text-align: center;">
                                    <div style="display: flex; gap: 0.5rem; justify-content: center; flex-wrap: wrap;">
                                        <button class="btn" style="padding: 0.25rem 0.75rem; font-size: 0.75rem; background: var(--info-color); color: white;" onclick="viewOrderDetails(<?php echo $order['id']; ?>)">
                                            👁️ View
                                        </button>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                            <select name="new_status" onchange="this.form.submit()" style="padding: 0.25rem; font-size: 0.75rem; border: 1px solid var(--border-color); border-radius: 4px;">
                                                <option value="">Update Status</option>
                                                <option value="pending" <?php echo $order['order_status'] === 'pending' ? 'disabled' : ''; ?>>Pending</option>
                                                <option value="confirmed" <?php echo $order['order_status'] === 'confirmed' ? 'disabled' : ''; ?>>Confirmed</option>
                                                <option value="shipped" <?php echo $order['order_status'] === 'shipped' ? 'disabled' : ''; ?>>Shipped</option>
                                                <option value="delivered" <?php echo $order['order_status'] === 'delivered' ? 'disabled' : ''; ?>>Delivered</option>
                                                <option value="cancelled" <?php echo $order['order_status'] === 'cancelled' ? 'disabled' : ''; ?>>Cancelled</option>
                                            </select>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">Next</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php else: ?>
                <div style="padding: 4rem; text-align: center;">
                    <div style="font-size: 4rem; margin-bottom: 2rem;">📦</div>
                    <h3 style="color: var(--text-secondary); margin-bottom: 1rem;">No orders found</h3>
                    <p style="color: var(--text-secondary); margin-bottom: 2rem;">
                        <?php if ($search || $status_filter): ?>
                            Try adjusting your search criteria or filters.
                        <?php else: ?>
                            Orders for your products will appear here.
                        <?php endif; ?>
                    </p>
                    <a href="products.php" class="btn btn-primary">Manage Products</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Order Details Modal -->
    <div id="orderModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; border-radius: var(--border-radius); padding: 2rem; max-width: 600px; width: 90%; max-height: 80%; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <h3>Order Details</h3>
                <button onclick="closeOrderModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
            </div>
            <div id="orderDetailsContent">
                <!-- Order details will be loaded here -->
            </div>
        </div>
    </div>

    <footer>
        <div class="container">
            <div class="footer-bottom">
                <p>&copy; 2025 CartHub Vendor Portal. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        function toggleAllOrders(checkbox) {
            const orderCheckboxes = document.querySelectorAll('.order-checkbox');
            orderCheckboxes.forEach(cb => cb.checked = checkbox.checked);
        }

        function viewOrderDetails(orderId) {
            // In a real implementation, this would fetch order details via AJAX
            document.getElementById('orderDetailsContent').innerHTML = `
                <div style="text-align: center; padding: 2rem;">
                    <div style="font-size: 2rem; margin-bottom: 1rem;">📋</div>
                    <p>Loading order details for Order #${orderId}...</p>
                    <p style="color: var(--text-secondary); font-size: 0.875rem;">This would show detailed order information, shipping address, payment details, and order history.</p>
                </div>
            `;
            document.getElementById('orderModal').style.display = 'block';
        }

        function closeOrderModal() {
            document.getElementById('orderModal').style.display = 'none';
        }

        function exportOrders() {
            alert('Export functionality would be implemented here to download order data as CSV/Excel.');
        }

        function bulkActions() {
            const selectedOrders = document.querySelectorAll('.order-checkbox:checked');
            if (selectedOrders.length === 0) {
                alert('Please select orders to perform bulk actions.');
                return;
            }
            
            const orderIds = Array.from(selectedOrders).map(cb => cb.value);
            alert(`Bulk actions for orders: ${orderIds.join(', ')}\n\nThis would allow updating multiple order statuses at once.`);
        }

        // Close modal when clicking outside
        document.getElementById('orderModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeOrderModal();
            }
        });
    </script>
</body>
</html>