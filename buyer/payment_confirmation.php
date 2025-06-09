<?php
require_once '../config/database.php';
requireLogin('buyer');

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$reference = isset($_GET['reference']) ? $_GET['reference'] : '';
$buyer_id = $_SESSION['user_id'];

// Verify order belongs to user
$order_stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND buyer_id = ?");
$order_stmt->execute([$order_id, $buyer_id]);
$order = $order_stmt->fetch();

if (!$order) {
    $_SESSION['error'] = 'Invalid order or order not found.';
    header('Location: orders.php');
    exit;
}

// Get order items
$items_stmt = $pdo->prepare("
    SELECT i.*, p.image_path, p.name as product_name, 
       p.price as unit_price, p.description,
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

// Paystack configuration
$paystack_secret_key = 'sk_test_7955d083231af531d17aa9e154bc1bccb543b0e3'; // Replace with your Paystack secret key

// Verify payment status
$payment_verified = false;
$payment_status = 'pending';

if ($reference) {
    // Verify payment with Paystack API
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api.paystack.co/transaction/verify/" . rawurlencode($reference),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Accept: application/json",
            "Authorization: Bearer " . $paystack_secret_key
        ]
    ]);
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    
    if (!$err) {
        $transaction = json_decode($response, true);
        if ($transaction && $transaction['status'] === true && $transaction['data']['status'] === 'success') {
            $payment_verified = true;
            $payment_status = 'paid';
            
            // Update order status in database if not already updated
            if ($order['payment_status'] !== 'paid') {
                $update_stmt = $pdo->prepare("
                    UPDATE orders 
                    SET payment_status = ?, order_status = 'confirmed', updated_at = NOW() 
                    WHERE id = ?
                ");
                $update_stmt->execute(['paid', $order_id]);
                
                // Clear cart only if payment was just confirmed
                $clear_cart_stmt = $pdo->prepare("DELETE FROM cart WHERE buyer_id = ?");
                $clear_cart_stmt->execute([$buyer_id]);
            }
        } elseif ($transaction && $transaction['data']['status'] === 'failed') {
            $payment_status = 'failed';
            
            // Update order status
            $update_stmt = $pdo->prepare("
                UPDATE orders 
                SET payment_status = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $update_stmt->execute(['failed', $order_id]);
        }
    }
} elseif ($order['payment_status'] === 'paid') {
    // Payment was already verified previously
    $payment_verified = true;
    $payment_status = 'paid';
}

// Get estimated delivery date (5-7 business days from now)
$delivery_date = date('F j, Y', strtotime('+7 days'));

// Generate a fake tracking number
$tracking_number = strtoupper('TRK' . str_pad(mt_rand(1, 9999999), 7, '0', STR_PAD_LEFT));
// Update tracking number if not set
if (empty($order['tracking_number']) && $payment_verified) {
    $pdo->prepare("UPDATE orders SET tracking_number = ? WHERE id = ?")->execute([$tracking_number, $order_id]);
} else {
    $tracking_number = $order['tracking_number'];
}

// Get shipping address
$shipping_address = json_decode($order['shipping_address'], true) ?: [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - CartHub</title>
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

    <main class="confirmation">
        <div class="container">
            <div class="confirmation-container">
                <div class="confirmation-header">
                    <div class="checkout-steps">
                        <div class="step completed">
                            <span class="step-number"><i class="fas fa-check"></i></span>
                            <span class="step-label">Shipping</span>
                        </div>
                        <div class="step completed">
                            <span class="step-number"><i class="fas fa-check"></i></span>
                            <span class="step-label">Payment</span>
                        </div>
                        <div class="step completed">
                            <span class="step-number"><i class="fas fa-check"></i></span>
                            <span class="step-label">Confirmation</span>
                        </div>
                    </div>
                </div>

                <?php if ($payment_verified): ?>
                    <div class="success-content">
                        <div class="success-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h1>Order Confirmed!</h1>
                        <p class="success-message">Thank you for your purchase. Your payment has been successfully processed.</p>
                        
                        <!-- Order Details -->
                        <div class="order-details">
                            <div class="order-header">
                                <div class="order-title">
                                    <h2>Order #<?php echo $order_id; ?></h2>
                                    <span class="order-status status-<?php echo $order['order_status']; ?>">
                                        <?php echo ucfirst($order['order_status']); ?>
                                    </span>
                                </div>
                                <div class="order-date">
                                    <i class="far fa-calendar-alt"></i>
                                    <?php echo date('F j, Y', strtotime($order['created_at'])); ?>
                                </div>
                            </div>
                            
                            <div class="details-grid">
                                <div class="details-box">
                                    <h3><i class="fas fa-receipt"></i> Payment Details</h3>
                                    <ul>
                                        <li><span>Payment Method:</span> Paystack</li>
                                        <li><span>Payment Status:</span> <span class="status-paid">Paid</span></li>
                                        <li><span>Transaction ID:</span> <?php echo $reference; ?></li>
                                        <li><span>Date:</span> <?php echo date('F j, Y H:i', strtotime($order['updated_at'])); ?></li>
                                    </ul>
                                </div>
                                
                                <div class="details-box">
                                    <h3><i class="fas fa-shipping-fast"></i> Shipping Details</h3>
                                    <ul>
                                        <li><span>Recipient:</span> <?php echo htmlspecialchars($shipping_address['full_name'] ?? $buyer['full_name']); ?></li>
                                        <li><span>Address:</span> <?php echo htmlspecialchars($shipping_address['address'] ?? ''); ?></li>
                                        <li>
                                            <?php 
                                            echo htmlspecialchars($shipping_address['city'] ?? '') . ', ';
                                            echo htmlspecialchars($shipping_address['state'] ?? '') . ' ';
                                            echo htmlspecialchars($shipping_address['zip_code'] ?? '');
                                            ?>
                                        </li>
                                        <li><span>Country:</span> <?php echo htmlspecialchars($shipping_address['country'] ?? ''); ?></li>
                                        <li><span>Phone:</span> <?php echo htmlspecialchars($shipping_address['phone'] ?? ''); ?></li>
                                    </ul>
                                </div>
                                
                                <div class="details-box">
                                    <h3><i class="fas fa-box"></i> Order Summary</h3>
                                    <ul>
                                        <li><span>Total Items:</span> <?php echo count($items); ?></li>
                                        <li><span>Subtotal:</span> $<?php echo number_format($order['total_amount'] - $order['shipping_amount'] - $order['tax_amount'], 2); ?></li>
                                        <li><span>Shipping:</span> $<?php echo number_format($order['shipping_amount'], 2); ?></li>
                                        <li><span>Tax:</span> $<?php echo number_format($order['tax_amount'], 2); ?></li>
                                        <li class="total"><span>Total:</span> $<?php echo number_format($order['total_amount'], 2); ?></li>
                                    </ul>
                                </div>
                                
                                <div class="details-box">
                                    <h3><i class="fas fa-truck"></i> Delivery Information</h3>
                                    <ul>
                                        <li><span>Status:</span> Processing</li>
                                        <li><span>Tracking Number:</span> <?php echo $tracking_number; ?></li>
                                        <li><span>Estimated Delivery:</span> <?php echo $delivery_date; ?></li>
                                        <li><span>Shipping Method:</span> Standard Shipping</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Order Items -->
                        <div class="confirmation-items">
                            <h3>Items in Your Order</h3>
                            
                            <div class="order-items">
                                <?php foreach ($items as $item): ?>
                                    <div class="order-item">
                                        <div class="item-image">
                                            <img src="<?php echo $item['image_path'] ? '../uploads/products/' . $item['image_path'] : '../images/placeholder.png'; ?>" 
                                                 alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                        </div>
                                        <div class="item-info">
                                            <h4><?php echo htmlspecialchars($item['product_name']); ?></h4>
                                            <p>Unit Price: $<?php echo number_format($item['unit_price'], 2); ?></p>
                                            <p>Quantity: <?php echo $item['quantity']; ?></p>
                                        </div>
                                        <div class="item-price">
                                            $<?php echo number_format($item['total_price'], 2); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Next Steps -->
                        <div class="next-steps">
                            <h3>What's Next?</h3>
                            <div class="steps-grid">
                                <div class="step-card">
                                    <div class="step-icon">
                                        <i class="fas fa-envelope"></i>
                                    </div>
                                    <div class="step-content">
                                        <h4>Order Confirmation Email</h4>
                                        <p>We've sent you a confirmation email with your order details.</p>
                                    </div>
                                </div>
                                <div class="step-card">
                                    <div class="step-icon">
                                        <i class="fas fa-box-open"></i>
                                    </div>
                                    <div class="step-content">
                                        <h4>Order Processing</h4>
                                        <p>We're preparing your items for shipment.</p>
                                    </div>
                                </div>
                                <div class="step-card">
                                    <div class="step-icon">
                                        <i class="fas fa-truck"></i>
                                    </div>
                                    <div class="step-content">
                                        <h4>Order Shipping</h4>
                                        <p>You'll receive tracking information once your order ships.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="confirmation-actions">
                            <a href="orders.php" class="btn btn-primary">
                                View Your Orders
                            </a>
                            <button class="btn btn-secondary" id="print-receipt">
                                <i class="fas fa-print"></i>
                                Print Receipt
                            </button>
                            <a href="browse.php" class="btn btn-secondary">
                                Continue Shopping
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="error-content">
                        <div class="error-icon">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <h1>Payment Failed</h1>
                        <p class="error-message">We couldn't process your payment. Please try again or contact customer support for assistance.</p>
                        
                        <div class="failed-details">
                            <div class="details-box">
                                <h3>Order Information</h3>
                                <ul>
                                    <li><span>Order ID:</span> #<?php echo $order_id; ?></li>
                                    <li><span>Date:</span> <?php echo date('F j, Y', strtotime($order['created_at'])); ?></li>
                                    <li><span>Amount:</span> $<?php echo number_format($order['total_amount'], 2); ?></li>
                                    <li><span>Status:</span> <span class="status-failed">Payment Failed</span></li>
                                </ul>
                            </div>
                            
                            <div class="failed-reason">
                                <h4>Possible reasons for payment failure:</h4>
                                <ul>
                                    <li>Insufficient funds in your account</li>
                                    <li>Card expired or invalid</li>
                                    <li>Bank declined the transaction</li>
                                    <li>Network or connectivity issues</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="confirmation-actions">
                            <a href="payment.php?order_id=<?php echo $order_id; ?>" class="btn btn-primary">
                                Try Payment Again
                            </a>
                            <a href="orders.php" class="btn btn-secondary">
                                View Your Orders
                            </a>
                            <a href="browse.php" class="btn btn-secondary">
                                Continue Shopping
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
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
        document.getElementById('print-receipt')?.addEventListener('click', function() {
            window.print();
        });
    </script>

    <style>
        .confirmation {
            background: #f8fafc;
            min-height: 100vh;
            padding: 2rem 0;
        }
        
        .confirmation-container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .confirmation-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .checkout-steps {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-bottom: 3rem;
        }
        
        .step {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
        }
        
        .step.completed {
            color: var(--success-color);
        }
        
        .step-number {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: var(--light-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        
        .step.completed .step-number {
            background: var(--success-color);
            color: white;
        }
        
        .success-content,
        .error-content {
            background: white;
            border-radius: var(--border-radius);
            padding: 3rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
        }
        
        .success-icon,
        .error-icon {
            font-size: 5rem;
            margin-bottom: 1.5rem;
        }
        
        .success-icon {
            color: var(--success-color);
        }
        
        .error-icon {
            color: var(--danger-color);
        }
        
        .success-content h1,
        .error-content h1 {
            margin-bottom: 1rem;
            color: var(--text-primary);
        }
        
        .success-message,
        .error-message {
            font-size: 1.1rem;
            color: var(--text-secondary);
            margin-bottom: 3rem;
        }
        
        .order-details {
            margin-bottom: 3rem;
            text-align: left;
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .order-title {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .order-title h2 {
            font-size: 1.5rem;
            margin: 0;
        }
        
        .order-status {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .status-paid,
        .status-confirmed {
            background: #ecfdf5;
            color: #065f46;
        }
        
        .status-pending {
            background: #fffbeb;
            color: #92400e;
        }
        
        .status-failed {
            background: #fee2e2;
            color: #b91c1c;
        }
        
        .order-date {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        
        .details-box {
            background: var(--light-color);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            text-align: left;
        }
        
        .details-box h3 {
            margin-bottom: 1rem;
            font-size: 1.1rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .details-box ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .details-box li {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            font-size: 0.95rem;
            color: var(--text-secondary);
        }
        
        .details-box li span:first-child {
            font-weight: 500;
            color: var(--text-primary);
        }
        
        .details-box li.total {
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--primary-color);
            border-top: 1px solid var(--border-color);
            padding-top: 0.75rem;
            margin-top: 0.75rem;
        }
        
        .confirmation-items {
            margin-bottom: 3rem;
            text-align: left;
        }
        
        .confirmation-items h3 {
            margin-bottom: 1.5rem;
            font-size: 1.25rem;
            color: var(--text-primary);
            text-align: center;
        }
        
        .order-items {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .order-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: var(--light-color);
            border-radius: var(--border-radius);
            padding: 1.25rem;
        }
        
        .item-image {
            width: 60px;
            height: 60px;
            flex-shrink: 0;
        }
        
        .item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: var(--border-radius);
        }
        
        .item-info {
            flex: 1;
        }
        
        .item-info h4 {
            margin: 0 0 0.25rem;
            font-size: 1rem;
        }
        
        .item-info p {
            margin: 0 0 0.25rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        .item-price {
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .next-steps {
            margin-bottom: 3rem;
        }
        
        .next-steps h3 {
            margin-bottom: 1.5rem;
            font-size: 1.25rem;
            color: var(--text-primary);
            text-align: center;
        }
        
        .steps-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
        }
        
        .step-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
            background: var(--light-color);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            text-align: center;
        }
        
        .step-icon {
            width: 60px;
            height: 60px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--primary-color);
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .step-content h4 {
            margin: 0 0 0.5rem;
            color: var(--text-primary);
        }
        
        .step-content p {
            margin: 0;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        .confirmation-actions {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }
        
        .failed-details {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .failed-reason {
            background: var(--light-color);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            text-align: left;
        }
        
        .failed-reason h4 {
            margin: 0 0 1rem;
            font-size: 1rem;
        }
        
        .failed-reason ul {
            padding-left: 1.5rem;
            margin: 0;
            color: var(--text-secondary);
        }
        
        .failed-reason li {
            margin-bottom: 0.5rem;
        }
        
        @media (max-width: 992px) {
            .success-content,
            .error-content {
                padding: 2rem;
            }
        }
        
        @media (max-width: 768px) {
            .checkout-steps {
                flex-direction: column;
                align-items: center;
                gap: 1rem;
            }
            
            .details-grid {
                grid-template-columns: 1fr;
            }
            
            .steps-grid {
                grid-template-columns: 1fr;
            }
            
            .confirmation-actions {
                flex-direction: column;
            }
            
            .confirmation-actions .btn {
                width: 100%;
            }
        }
        
        @media print {
            header, footer, .checkout-steps, .next-steps, .confirmation-actions, 
            .step-icon, .order-status {
                display: none !important;
            }
            
            .success-content {
                box-shadow: none;
                padding: 0;
            }
            
            .details-grid {
                grid-template-columns: 1fr 1fr;
                gap: 2rem;
            }
            
            .details-box {
                break-inside: avoid;
            }
            
            body {
                font-size: 12pt;
            }
            
            .success-icon {
                font-size: 3rem;
            }
        }
    </style>
</body>
</html>
