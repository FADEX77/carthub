<?php
require_once '../config/database.php';
requireLogin('buyer');

$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$buyer_id = $_SESSION['user_id'];

// Verify order belongs to user and get order details
$order_stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND buyer_id = ?");
$order_stmt->execute([$order_id, $buyer_id]);
$order = $order_stmt->fetch();

if (!$order) {
    $_SESSION['error'] = 'Order not found or access denied.';
    header('Location: orders.php');
    exit;
}

// Get order items with product details
$items_stmt = $pdo->prepare("
    SELECT i.*, p.name as product_name, p.image_path, p.description,
           (i.quantity * COALESCE(i.price, p.price, 0)) as total_price
    FROM order_items i 
    LEFT JOIN products p ON i.product_id = p.id 
    WHERE i.order_id = ?
");
$items_stmt->execute([$order_id]);
$items = $items_stmt->fetchAll();

// Get buyer info
$buyer_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$buyer_stmt->execute([$buyer_id]);
$buyer = $buyer_stmt->fetch();

// Decode addresses
$shipping_address = json_decode($order['shipping_address'], true) ?: [];
$billing_address = json_decode($order['billing_address'], true) ?: [];

// Generate tracking number if not exists
$tracking_number = $order['tracking_number'] ?? 'TRK' . str_pad($order_id, 8, '0', STR_PAD_LEFT);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #<?php echo $order_id; ?> - CartHub</title>
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
                        <li><a href="cart.php">Cart</a></li>
                        <li><a href="orders.php">My Orders</a></li>
                        <li><a href="../logout.php" class="btn btn-danger">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main class="order-detail">
        <div class="container">
            <!-- Breadcrumb -->
            <div class="breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="orders.php">My Orders</a>
                <span>/</span>
                <span>Order #<?php echo $order_id; ?></span>
            </div>

            <!-- Order Header -->
            <div class="order-header">
                <div class="order-title">
                    <h1>Order #<?php echo $order_id; ?></h1>
                    <span class="order-status status-<?php echo $order['order_status']; ?>">
                        <?php echo ucfirst($order['order_status']); ?>
                    </span>
                </div>
                <div class="order-date">
                    <i class="far fa-calendar-alt"></i>
                    Placed on <?php echo date('F j, Y \a\t g:i A', strtotime($order['created_at'])); ?>
                </div>
            </div>

            <!-- Order Timeline -->
            <div class="order-timeline">
                <div class="timeline-item <?php echo in_array($order['order_status'], ['pending', 'confirmed', 'shipped', 'delivered']) ? 'completed' : ''; ?>">
                    <div class="timeline-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="timeline-content">
                        <h4>Order Placed</h4>
                        <p><?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></p>
                    </div>
                </div>

                <div class="timeline-item <?php echo in_array($order['order_status'], ['confirmed', 'shipped', 'delivered']) ? 'completed' : ''; ?>">
                    <div class="timeline-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="timeline-content">
                        <h4>Order Confirmed</h4>
                        <p><?php echo $order['payment_status'] === 'paid' ? 'Payment confirmed' : 'Awaiting payment'; ?></p>
                    </div>
                </div>

                <div class="timeline-item <?php echo in_array($order['order_status'], ['shipped', 'delivered']) ? 'completed' : ''; ?>">
                    <div class="timeline-icon">
                        <i class="fas fa-truck"></i>
                    </div>
                    <div class="timeline-content">
                        <h4>Order Shipped</h4>
                        <p><?php echo $order['order_status'] === 'shipped' || $order['order_status'] === 'delivered' ? 'Package in transit' : 'Preparing for shipment'; ?></p>
                    </div>
                </div>

                <div class="timeline-item <?php echo $order['order_status'] === 'delivered' ? 'completed' : ''; ?>">
                    <div class="timeline-icon">
                        <i class="fas fa-home"></i>
                    </div>
                    <div class="timeline-content">
                        <h4>Delivered</h4>
                        <p><?php echo $order['order_status'] === 'delivered' ? 'Package delivered' : 'Estimated: ' . date('M j, Y', strtotime($order['created_at'] . ' +7 days')); ?></p>
                    </div>
                </div>
            </div>

            <div class="order-content">
                <!-- Order Items -->
                <div class="order-section">
                    <h2>Order Items</h2>
                    <div class="order-items">
                        <?php foreach ($items as $item): ?>
                            <div class="order-item">
                                <div class="item-image">
                                    <img src="<?php echo $item['image_path'] ? '../uploads/products/' . $item['image_path'] : '../images/placeholder.png'; ?>" 
                                         alt="<?php echo htmlspecialchars($item['product_name'] ?? 'Product'); ?>">
                                </div>
                                <div class="item-details">
                                    <h4><?php echo htmlspecialchars($item['product_name'] ?? 'Product'); ?></h4>
                                    <p class="item-description"><?php echo htmlspecialchars(substr($item['description'] ?? '', 0, 100)); ?><?php echo strlen($item['description'] ?? '') > 100 ? '...' : ''; ?></p>
                                    <div class="item-meta">
                                        <span>Quantity: <?php echo $item['quantity']; ?></span>
                                        <span>Unit Price: $<?php echo number_format($item['price'] ?? 0, 2); ?></span>
                                    </div>
                                </div>
                                <div class="item-price">
                                    $<?php echo number_format($item['total_price'] ?? 0, 2); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Order Summary -->
                <div class="order-section">
                    <h2>Order Summary</h2>
                    <div class="summary-details">
                        <div class="summary-row">
                            <span>Subtotal</span>
                            <span>$<?php echo number_format($order['total_amount'] - $order['shipping_amount'] - $order['tax_amount'], 2); ?></span>
                        </div>
                        <div class="summary-row">
                            <span>Shipping</span>
                            <span>$<?php echo number_format($order['shipping_amount'], 2); ?></span>
                        </div>
                        <div class="summary-row">
                            <span>Tax</span>
                            <span>$<?php echo number_format($order['tax_amount'], 2); ?></span>
                        </div>
                        <div class="summary-divider"></div>
                        <div class="summary-row total">
                            <span>Total</span>
                            <span>$<?php echo number_format($order['total_amount'], 2); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Shipping & Billing -->
                <div class="address-section">
                    <div class="address-box">
                        <h3><i class="fas fa-shipping-fast"></i> Shipping Address</h3>
                        <div class="address-content">
                            <p><strong><?php echo htmlspecialchars($shipping_address['full_name'] ?? $buyer['full_name']); ?></strong></p>
                            <p><?php echo htmlspecialchars($shipping_address['address'] ?? ''); ?></p>
                            <p><?php echo htmlspecialchars($shipping_address['city'] ?? '') . ', ' . 
                                        htmlspecialchars($shipping_address['state'] ?? '') . ' ' . 
                                        htmlspecialchars($shipping_address['zip_code'] ?? ''); ?></p>
                            <p><?php echo htmlspecialchars($shipping_address['country'] ?? ''); ?></p>
                            <p><?php echo htmlspecialchars($shipping_address['phone'] ?? ''); ?></p>
                        </div>
                    </div>

                    <div class="address-box">
                        <h3><i class="fas fa-credit-card"></i> Billing Address</h3>
                        <div class="address-content">
                            <p><strong><?php echo htmlspecialchars($billing_address['full_name'] ?? $buyer['full_name']); ?></strong></p>
                            <p><?php echo htmlspecialchars($billing_address['address'] ?? ''); ?></p>
                            <p><?php echo htmlspecialchars($billing_address['city'] ?? '') . ', ' . 
                                        htmlspecialchars($billing_address['state'] ?? '') . ' ' . 
                                        htmlspecialchars($billing_address['zip_code'] ?? ''); ?></p>
                            <p><?php echo htmlspecialchars($billing_address['country'] ?? ''); ?></p>
                            <p><?php echo htmlspecialchars($billing_address['phone'] ?? ''); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Payment Information -->
                <div class="order-section">
                    <h2>Payment Information</h2>
                    <div class="payment-info">
                        <div class="payment-method">
                            <div class="payment-icon">
                                <i class="fas fa-credit-card"></i>
                            </div>
                            <div class="payment-details">
                                <h4><?php echo ucfirst($order['payment_method'] ?? 'Paystack'); ?></h4>
                                <p>Status: <span class="payment-status status-<?php echo $order['payment_status']; ?>"><?php echo ucfirst($order['payment_status']); ?></span></p>
                                <p>Amount: $<?php echo number_format($order['total_amount'], 2); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tracking Information -->
                <?php if (in_array($order['order_status'], ['shipped', 'delivered'])): ?>
                <div class="order-section">
                    <h2>Tracking Information</h2>
                    <div class="tracking-info">
                        <div class="tracking-number">
                            <strong>Tracking Number: <?php echo $tracking_number; ?></strong>
                        </div>
                        <div class="tracking-details">
                            <p>Carrier: Standard Shipping</p>
                            <p>Estimated Delivery: <?php echo date('F j, Y', strtotime($order['created_at'] . ' +7 days')); ?></p>
                            <a href="#" class="track-package-btn">
                                <i class="fas fa-external-link-alt"></i>
                                Track Package
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Action Buttons -->
            <div class="order-actions">
                <?php if ($order['order_status'] === 'pending'): ?>
                    <button class="btn btn-danger" onclick="cancelOrder(<?php echo $order_id; ?>)">
                        <i class="fas fa-times"></i>
                        Cancel Order
                    </button>
                <?php endif; ?>
                
                <?php if ($order['order_status'] === 'delivered'): ?>
                    <a href="review.php?order_id=<?php echo $order_id; ?>" class="btn btn-primary">
                        <i class="fas fa-star"></i>
                        Leave Review
                    </a>
                <?php endif; ?>
                
                <button class="btn btn-secondary" onclick="window.print()">
                    <i class="fas fa-print"></i>
                    Print Order
                </button>
                
                <a href="orders.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Back to Orders
                </a>
                
                <a href="browse.php" class="btn btn-primary">
                    <i class="fas fa-shopping-cart"></i>
                    Shop Again
                </a>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> CartHub. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
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
        .order-detail {
            background: #f8fafc;
            min-height: 100vh;
            padding: 2rem 0;
        }
        
        .breadcrumb {
            margin-bottom: 2rem;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        
        .breadcrumb a {
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .breadcrumb span {
            margin: 0 0.5rem;
        }
        
        .order-header {
            background: white;
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .order-title {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .order-title h1 {
            margin: 0;
            font-size: 1.75rem;
        }
        
        .order-status {
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-size: 0.875rem;
            font-weight: 600;
        }
        
        .status-pending {
            background: rgba(245, 158, 11, 0.1);
            color: #d97706;
        }
        
        .status-confirmed {
            background: rgba(59, 130, 246, 0.1);
            color: #2563eb;
        }
        
        .status-shipped {
            background: rgba(139, 92, 246, 0.1);
            color: #7c3aed;
        }
        
        .status-delivered {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
        }
        
        .status-cancelled {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
        }
        
        .status-paid {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
        }
        
        .order-date {
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .order-timeline {
            background: white;
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            position: relative;
        }
        
        .order-timeline::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 10%;
            right: 10%;
            height: 2px;
            background: var(--border-color);
            z-index: 1;
        }
        
        .timeline-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            position: relative;
            z-index: 2;
            flex: 1;
        }
        
        .timeline-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--light-color);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            font-size: 1.25rem;
            color: var(--text-secondary);
            border: 3px solid var(--border-color);
        }
        
        .timeline-item.completed .timeline-icon {
            background: var(--success-color);
            color: white;
            border-color: var(--success-color);
        }
        
        .timeline-content h4 {
            margin: 0 0 0.5rem;
            font-size: 0.9rem;
            color: var(--text-primary);
        }
        
        .timeline-content p {
            margin: 0;
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        .order-content {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }
        
        .order-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .order-section h2 {
            margin: 0 0 1.5rem;
            font-size: 1.25rem;
            color: var(--text-primary);
        }
        
        .order-items {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .order-item {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            padding: 1.5rem;
            background: var(--light-color);
            border-radius: var(--border-radius);
        }
        
        .item-image {
            width: 80px;
            height: 80px;
            flex-shrink: 0;
        }
        
        .item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: var(--border-radius);
        }
        
        .item-details {
            flex: 1;
        }
        
        .item-details h4 {
            margin: 0 0 0.5rem;
            font-size: 1.1rem;
        }
        
        .item-description {
            margin: 0 0 0.75rem;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .item-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        .item-price {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .summary-details {
            max-width: 400px;
            margin-left: auto;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            font-size: 0.95rem;
        }
        
        .summary-row.total {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .summary-divider {
            height: 1px;
            background: var(--border-color);
            margin: 1.5rem 0;
        }
        
        .address-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }
        
        .address-box {
            background: white;
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .address-box h3 {
            margin: 0 0 1.5rem;
            font-size: 1.1rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .address-content p {
            margin: 0 0 0.5rem;
            color: var(--text-secondary);
            line-height: 1.5;
        }
        
        .payment-info {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        
        .payment-icon {
            width: 60px;
            height: 60px;
            background: var(--light-color);
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--primary-color);
        }
        
        .payment-details h4 {
            margin: 0 0 0.5rem;
            font-size: 1.1rem;
        }
        
        .payment-details p {
            margin: 0 0 0.25rem;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .tracking-info {
            background: var(--light-color);
            border-radius: var(--border-radius);
            padding: 1.5rem;
        }
        
        .tracking-number {
            font-size: 1.1rem;
            margin-bottom: 1rem;
            color: var(--text-primary);
        }
        
        .tracking-details p {
            margin: 0 0 0.5rem;
            color: var(--text-secondary);
        }
        
        .track-package-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            margin-top: 1rem;
        }
        
        .track-package-btn:hover {
            text-decoration: underline;
        }
        
        .order-actions {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }
        
        .order-actions .btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        @media (max-width: 992px) {
            .order-timeline {
                flex-direction: column;
                gap: 2rem;
            }
            
            .order-timeline::before {
                display: none;
            }
            
            .timeline-item {
                flex-direction: row;
                text-align: left;
            }
            
            .timeline-icon {
                margin-bottom: 0;
                margin-right: 1rem;
            }
            
            .address-section {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .order-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .order-item {
                flex-direction: column;
                text-align: center;
            }
            
            .item-details {
                text-align: center;
            }
            
            .order-actions {
                flex-direction: column;
            }
            
            .order-actions .btn {
                width: 100%;
                justify-content: center;
            }
        }
        
        @media print {
            header, footer, .order-actions, .breadcrumb {
                display: none !important;
            }
            
            .order-detail {
                background: white;
                padding: 0;
            }
            
            .order-section, .address-box {
                box-shadow: none;
                break-inside: avoid;
            }
        }
    </style>
</body>
</html>
