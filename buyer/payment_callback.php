<?php
require_once '../config/database.php';
requireLogin('buyer');

$reference = $_GET['reference'] ?? '';
$order_id = intval($_GET['order_id'] ?? 0);
$buyer_id = $_SESSION['user_id'];

// Paystack secret key - Replace with your actual secret key
$paystack_secret_key = 'sk_test_7955d083231af531d17aa9e154bc1bccb543b0e3';

// Verify payment with Paystack
function verifyPaystackPayment($reference, $secret_key) {
    $curl = curl_init();
    
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://api.paystack.co/transaction/verify/" . rawurlencode($reference),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => array(
            "Authorization: Bearer " . $secret_key,
            "Cache-Control: no-cache",
        ),
    ));
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    
    if ($err) {
        return false;
    }
    
    return json_decode($response, true);
}

// Verify order belongs to user
$order_stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND buyer_id = ?");
$order_stmt->execute([$order_id, $buyer_id]);
$order = $order_stmt->fetch();

if (!$order) {
    header('Location: orders.php');
    exit;
}

$payment_successful = false;
$error_message = '';

if ($reference) {
    // Verify payment with Paystack
    $verification = verifyPaystackPayment($reference, $paystack_secret_key);
    
    if ($verification && $verification['status'] && $verification['data']['status'] === 'success') {
        // Payment successful
        $payment_successful = true;
        
        // Update order status
        $update_stmt = $pdo->prepare("
            UPDATE orders 
            SET payment_status = 'paid', order_status = 'confirmed', updated_at = NOW() 
            WHERE id = ?
        ");
        $update_stmt->execute([$order_id]);
        
        // Clear cart
        $clear_cart_stmt = $pdo->prepare("DELETE FROM cart WHERE buyer_id = ?");
        $clear_cart_stmt->execute([$buyer_id]);
        
        // Clear payment data from session
        unset($_SESSION['payment_data']);
        
    } else {
        $error_message = 'Payment verification failed. Please contact support.';
        
        // Update order status to failed
        $update_stmt = $pdo->prepare("
            UPDATE orders 
            SET payment_status = 'failed', updated_at = NOW() 
            WHERE id = ?
        ");
        $update_stmt->execute([$order_id]);
    }
} else {
    $error_message = 'Invalid payment reference.';
}

// Get order items for display
$items_stmt = $pdo->prepare("
    SELECT oi.*, p.image_path 
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$items_stmt->execute([$order_id]);
$order_items = $items_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $payment_successful ? 'Payment Successful' : 'Payment Failed'; ?> - CartHub</title>
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
                        <li><a href="orders.php">My Orders</a></li>
                        <li><a href="../logout.php" class="btn btn-danger">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main class="payment-result">
        <div class="container">
            <div class="result-container">
                <?php if ($payment_successful): ?>
                    <!-- Success Page -->
                    <div class="success-content">
                        <div class="success-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h1>Payment Successful!</h1>
                        <p>Thank you for your order. Your payment has been processed successfully.</p>
                        
                        <div class="order-details">
                            <h3>Order Details</h3>
                            <div class="detail-row">
                                <span>Order Number:</span>
                                <span>#<?php echo $order['id']; ?></span>
                            </div>
                            <div class="detail-row">
                                <span>Payment Reference:</span>
                                <span><?php echo htmlspecialchars($reference); ?></span>
                            </div>
                            <div class="detail-row">
                                <span>Total Amount:</span>
                                <span>$<?php echo number_format($order['total_amount'], 2); ?></span>
                            </div>
                            <div class="detail-row">
                                <span>Payment Method:</span>
                                <span>Paystack</span>
                            </div>
                            <div class="detail-row">
                                <span>Order Status:</span>
                                <span class="status-confirmed">Confirmed</span>
                            </div>
                        </div>
                        
                        <?php if (!empty($order_items)): ?>
                            <div class="order-items">
                                <h3>Items Ordered</h3>
                                <?php foreach ($order_items as $item): ?>
                                    <div class="order-item">
                                        <img src="<?php echo $item['image_path'] ? '../uploads/products/' . $item['image_path'] : '/placeholder.svg?height=60&width=60'; ?>" 
                                             alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                        <div class="item-info">
                                            <h4><?php echo htmlspecialchars($item['product_name']); ?></h4>
                                            <p>Quantity: <?php echo $item['quantity']; ?></p>
                                            <p>Price: $<?php echo number_format($item['unit_price'], 2); ?></p>
                                        </div>
                                        <div class="item-total">
                                            $<?php echo number_format($item['total_price'], 2); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="next-steps">
                            <h3>What's Next?</h3>
                            <div class="steps-grid">
                                <div class="step-card">
                                    <i class="fas fa-envelope"></i>
                                    <h4>Confirmation Email</h4>
                                    <p>You'll receive an order confirmation email shortly</p>
                                </div>
                                <div class="step-card">
                                    <i class="fas fa-box"></i>
                                    <h4>Processing</h4>
                                    <p>Your order will be processed within 1-2 business days</p>
                                </div>
                                <div class="step-card">
                                    <i class="fas fa-truck"></i>
                                    <h4>Shipping</h4>
                                    <p>You'll receive tracking information once shipped</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="action-buttons">
                            <a href="orders.php" class="btn btn-primary">View My Orders</a>
                            <a href="browse.php" class="btn btn-secondary">Continue Shopping</a>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Error Page -->
                    <div class="error-content">
                        <div class="error-icon">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <h1>Payment Failed</h1>
                        <p><?php echo htmlspecialchars($error_message); ?></p>
                        
                        <div class="order-details">
                            <h3>Order Information</h3>
                            <div class="detail-row">
                                <span>Order Number:</span>
                                <span>#<?php echo $order['id']; ?></span>
                            </div>
                            <div class="detail-row">
                                <span>Amount:</span>
                                <span>$<?php echo number_format($order['total_amount'], 2); ?></span>
                            </div>
                            <div class="detail-row">
                                <span>Status:</span>
                                <span class="status-failed">Payment Failed</span>
                            </div>
                        </div>
                        
                        <div class="action-buttons">
                            <a href="payment.php?order_id=<?php echo $order_id; ?>" class="btn btn-primary">Try Again</a>
                            <a href="orders.php" class="btn btn-secondary">View Orders</a>
                            <a href="dashboard.php" class="btn btn-secondary">Go to Dashboard</a>
                        </div>
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

    <style>
        .payment-result {
            background: #f8fafc;
            min-height: 100vh;
            padding: 2rem 0;
        }
        
        .result-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: var(--border-radius);
            padding: 3rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
        }
        
        .success-icon {
            font-size: 5rem;
            color: var(--success-color);
            margin-bottom: 1.5rem;
        }
        
        .error-icon {
            font-size: 5rem;
            color: var(--danger-color);
            margin-bottom: 1.5rem;
        }
        
        .result-container h1 {
            margin-bottom: 1rem;
            color: var(--text-primary);
        }
        
        .result-container > p {
            margin-bottom: 2rem;
            color: var(--text-secondary);
            font-size: 1.1rem;
        }
        
        .order-details {
            background: var(--light-color);
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-bottom: 2rem;
            text-align: left;
        }
        
        .order-details h3 {
            margin-bottom: 1.5rem;
            color: var(--text-primary);
            text-align: center;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .detail-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .detail-row span:first-child {
            color: var(--text-secondary);
        }
        
        .detail-row span:last-child {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .status-confirmed {
            color: var(--success-color) !important;
        }
        
        .status-failed {
            color: var(--danger-color) !important;
        }
        
        .order-items {
            margin-bottom: 2rem;
            text-align: left;
        }
        
        .order-items h3 {
            margin-bottom: 1.5rem;
            color: var(--text-primary);
            text-align: center;
        }
        
        .order-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: var(--light-color);
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
        }
        
        .order-item img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: var(--border-radius);
        }
        
        .item-info {
            flex: 1;
        }
        
        .item-info h4 {
            margin: 0 0 0.5rem;
            color: var(--text-primary);
        }
        
        .item-info p {
            margin: 0 0 0.25rem;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .item-total {
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .next-steps {
            margin-bottom: 2rem;
        }
        
        .next-steps h3 {
            margin-bottom: 1.5rem;
            color: var(--text-primary);
        }
        
        .steps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }
        
        .step-card {
            background: var(--light-color);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            text-align: center;
        }
        
        .step-card i {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        
        .step-card h4 {
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }
        
        .step-card p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        @media (max-width: 768px) {
            .result-container {
                margin: 1rem;
                padding: 2rem;
            }
            
            .steps-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .action-buttons .btn {
                width: 100%;
                max-width: 300px;
            }
        }
    </style>
</body>
</html>
