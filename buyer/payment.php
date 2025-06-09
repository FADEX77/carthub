<?php
require_once '../config/database.php';
requireLogin('buyer');

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
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
    SELECT i.*, p.image_path, p.name as product_name, p.price as unit_price,
           (i.quantity * COALESCE(i.price, p.price, 0)) as total_price
    FROM order_items i 
    LEFT JOIN products p ON i.product_id = p.id 
    WHERE i.order_id = ?
");
$items_stmt->execute([$order_id]);
$items = $items_stmt->fetchAll();

// Paystack configuration - Replace with your actual keys
$paystack_public_key = 'pk_test_4594636884df651cf5633e6edcbfa3a46245c746'; // Replace with your Paystack public key

// Generate reference
$reference = 'carthub_' . $order_id . '_' . time();

// Get payment data from session or regenerate if not available
if (!isset($_SESSION['payment_data']) || $_SESSION['payment_data']['order_id'] != $order_id) {
    // Get user data
    $user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $user_stmt->execute([$buyer_id]);
    $user = $user_stmt->fetch();

    // Decode shipping address
    $shipping_address = json_decode($order['shipping_address'], true) ?: [];

    // Convert amount to cents for USD (Paystack expects amount in smallest currency unit)
    $total_amount = floatval($order['total_amount'] ?? 0);
    $amount_in_cents = intval($total_amount * 100); // Convert to cents and ensure it's an integer

    $_SESSION['payment_data'] = [
        'order_id' => $order_id,
        'amount' => $amount_in_cents,
        'email' => $user['email'] ?? '',
        'customer_name' => $shipping_address['full_name'] ?? $user['full_name'] ?? '',
        'phone' => $shipping_address['phone'] ?? $user['phone'] ?? ''
    ];
}

$payment_data = $_SESSION['payment_data'];

// Additional validation to ensure amount is valid
if (!is_numeric($payment_data['amount']) || $payment_data['amount'] <= 0) {
    $_SESSION['error'] = 'Invalid payment amount. Please try again.';
    header('Location: orders.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - CartHub</title>
    <link rel="stylesheet" href="../css/modern_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://js.paystack.co/v1/inline.js"></script>
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

    <main class="payment">
        <div class="container">
            <div class="payment-container">
                <div class="payment-header">
                    <h1>Complete Your Payment</h1>
                    <div class="checkout-steps">
                        <div class="step completed">
                            <span class="step-number"><i class="fas fa-check"></i></span>
                            <span class="step-label">Shipping</span>
                        </div>
                        <div class="step active">
                            <span class="step-number">2</span>
                            <span class="step-label">Payment</span>
                        </div>
                        <div class="step">
                            <span class="step-number">3</span>
                            <span class="step-label">Confirmation</span>
                        </div>
                    </div>
                </div>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <!-- Debug Information (Remove in production) -->
                <div class="debug-info" style="background: #f0f9ff; border: 1px solid #0ea5e9; padding: 1rem; margin-bottom: 1rem; border-radius: 8px;">
                    <h4 style="margin: 0 0 0.5rem; color: #0369a1;">Debug Information:</h4>
                    <p style="margin: 0; font-family: monospace; font-size: 0.9rem;">
                        Order Total: $<?php echo number_format($order['total_amount'], 2); ?><br>
                        Amount in Cents: <?php echo $payment_data['amount']; ?><br>
                        Email: <?php echo htmlspecialchars($payment_data['email']); ?><br>
                        Customer: <?php echo htmlspecialchars($payment_data['customer_name']); ?>
                    </p>
                </div>

                <div class="payment-content">
                    <div class="payment-details">
                        <div class="order-info">
                            <h3>Order #<?php echo $order_id; ?></h3>
                            <div class="order-meta">
                                <div class="meta-item">
                                    <span class="meta-label">Date:</span>
                                    <span class="meta-value"><?php echo date('F j, Y', strtotime($order['created_at'])); ?></span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label">Total Amount:</span>
                                    <span class="meta-value">$<?php echo number_format($order['total_amount'], 2); ?></span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label">Status:</span>
                                    <span class="meta-value status-pending"><?php echo ucfirst($order['order_status']); ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="payment-method-section">
                            <h3>Payment Method</h3>
                            <div class="payment-method-card active">
                                <div class="payment-icon">
                                    <i class="fas fa-credit-card"></i>
                                </div>
                                <div class="payment-info">
                                    <h4>Pay with Paystack</h4>
                                    <p>Secure payment with your debit/credit card</p>
                                    <div class="accepted-cards">
                                        <i class="fab fa-cc-visa"></i>
                                        <i class="fab fa-cc-mastercard"></i>
                                        <i class="fab fa-cc-amex"></i>
                                        <i class="fas fa-university"></i>
                                    </div>
                                </div>
                            </div>

                            <button id="paystack-button" class="btn btn-primary btn-large">
                                <i class="fas fa-lock"></i>
                                Pay $<?php echo number_format($order['total_amount'], 2); ?> Securely
                            </button>

                            <div class="security-badges">
                                <div class="security-badge">
                                    <i class="fas fa-shield-alt"></i>
                                    <span>SSL Secured</span>
                                </div>
                                <div class="security-badge">
                                    <i class="fas fa-lock"></i>
                                    <span>256-bit Encryption</span>
                                </div>
                                <div class="security-badge">
                                    <i class="fas fa-check-circle"></i>
                                    <span>PCI Compliant</span>
                                </div>
                            </div>
                        </div>

                        <!-- Customer Information -->
                        <div class="customer-info">
                            <h3>Customer Information</h3>
                            <div class="info-grid">
                                <?php 
                                $shipping_address = json_decode($order['shipping_address'], true) ?: [];
                                $billing_address = json_decode($order['billing_address'], true) ?: [];
                                ?>
                                <div class="info-box">
                                    <h4>Shipping Address</h4>
                                    <p><?php echo htmlspecialchars($shipping_address['full_name'] ?? ''); ?></p>
                                    <p><?php echo htmlspecialchars($shipping_address['address'] ?? ''); ?></p>
                                    <p><?php echo htmlspecialchars($shipping_address['city'] ?? '') . ', ' . 
                                        htmlspecialchars($shipping_address['state'] ?? '') . ' ' . 
                                        htmlspecialchars($shipping_address['zip_code'] ?? ''); ?></p>
                                    <p><?php echo htmlspecialchars($shipping_address['country'] ?? ''); ?></p>
                                    <p><?php echo htmlspecialchars($shipping_address['phone'] ?? ''); ?></p>
                                </div>
                                
                                <div class="info-box">
                                    <h4>Billing Address</h4>
                                    <p><?php echo htmlspecialchars($billing_address['full_name'] ?? ''); ?></p>
                                    <p><?php echo htmlspecialchars($billing_address['address'] ?? ''); ?></p>
                                    <p><?php echo htmlspecialchars($billing_address['city'] ?? '') . ', ' . 
                                        htmlspecialchars($billing_address['state'] ?? '') . ' ' . 
                                        htmlspecialchars($billing_address['zip_code'] ?? ''); ?></p>
                                    <p><?php echo htmlspecialchars($billing_address['country'] ?? ''); ?></p>
                                    <p><?php echo htmlspecialchars($billing_address['phone'] ?? ''); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="order-summary">
                        <h3>Order Summary</h3>
                        
                        <!-- Order Items -->
                       <div class="order-items">
                        <?php foreach ($items as $item): ?>
                            <div class="order-item">
                                <img src="<?php echo $item['image_path'] ? '../uploads/products/' . $item['image_path'] : '../images/placeholder.png'; ?>" 
                                    alt="<?php echo htmlspecialchars($item['product_name'] ?? $item['name'] ?? 'Product'); ?>">
                                <div class="item-details">
                                    <h4><?php echo htmlspecialchars($item['product_name'] ?? $item['name'] ?? 'Product'); ?></h4>
                                    <p>Quantity: <?php echo $item['quantity']; ?></p>
                                    <p>Unit Price: $<?php echo number_format($item['unit_price'] ?? $item['price'] ?? 0, 2); ?></p>
                                </div>
                                <div class="item-price">
                                    $<?php echo number_format($item['total_price'] ?? ($item['price'] * $item['quantity']) ?? 0, 2); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                        
                        <!-- Order Totals -->
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
                </div>

                <div class="payment-footer">
                    <a href="checkout.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Back to Checkout
                    </a>
                </div>
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
        // Enhanced JavaScript with better error handling for USD payments
        document.addEventListener('DOMContentLoaded', function() {
            const paystackButton = document.getElementById('paystack-button');
            
            // Check if Paystack is loaded
            if (typeof PaystackPop === 'undefined') {
                console.error('Paystack script not loaded');
                paystackButton.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Payment System Error';
                paystackButton.disabled = true;
                return;
            }

            paystackButton.addEventListener('click', function() {
                // Disable button to prevent double clicks
                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                
                // Debug: Log payment data
                console.log('Payment Data:', {
                    key: '<?php echo $paystack_public_key; ?>',
                    email: '<?php echo addslashes($payment_data['email']); ?>',
                    amount: <?php echo intval($payment_data['amount']); ?>,
                    currency: 'NGN',
                    ref: '<?php echo $reference; ?>'
                });

                try {
                    let handler = PaystackPop.setup({
                        key: '<?php echo $paystack_public_key; ?>',
                        email: '<?php echo addslashes($payment_data['email']); ?>',
                        amount: <?php echo intval($payment_data['amount']); ?>, // Amount in cents for USD
                        currency: 'NGN', // Changed to USD for dollar payments
                        ref: '<?php echo $reference; ?>',
                        metadata: {
                            order_id: <?php echo $order_id; ?>,
                            customer_name: '<?php echo addslashes($payment_data['customer_name']); ?>',
                            phone: '<?php echo addslashes($payment_data['phone']); ?>'
                        },
                        callback: function(response) {
                            console.log('Payment successful:', response);
                            // Payment successful
                            window.location.href = 'payment_confirmation.php?reference=' + response.reference + '&order_id=<?php echo $order_id; ?>';
                        },
                        onClose: function() {
                            console.log('Payment canceled by user');
                            // Re-enable button
                            paystackButton.disabled = false;
                            paystackButton.innerHTML = '<i class="fas fa-lock"></i> Pay $<?php echo number_format($order['total_amount'], 2); ?> Securely';
                            alert('Payment canceled. You can try again when ready.');
                        }
                    });
                    
                    handler.openIframe();
                } catch (error) {
                    console.error('Paystack setup error:', error);
                    // Re-enable button
                    paystackButton.disabled = false;
                    paystackButton.innerHTML = '<i class="fas fa-lock"></i> Pay $<?php echo number_format($order['total_amount'], 2); ?> Securely';
                    alert('Payment system error. Please try again or contact support.');
                }
            });
        });
    </script>

    <style>
        .alert {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .alert-error {
            background: #fee;
            color: #c53030;
            border: 1px solid #feb2b2;
        }
        
        .payment {
            background: #f8fafc;
            min-height: 100vh;
            padding: 2rem 0;
        }
        
        .payment-container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .payment-header {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .payment-header h1 {
            margin-bottom: 2rem;
            color: var(--text-primary);
        }
        
        .checkout-steps {
            display: flex;
            justify-content: center;
            gap: 2rem;
        }
        
        .step {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
        }
        
        .step.active {
            color: var(--primary-color);
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
        
        .step.active .step-number {
            background: var(--primary-color);
            color: white;
        }
        
        .step.completed .step-number {
            background: var(--success-color);
            color: white;
        }
        
        .payment-content {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 3rem;
            margin-bottom: 2rem;
        }
        
        .payment-details {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }
        
        .order-info,
        .payment-method-section,
        .customer-info {
            background: white;
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .order-info h3,
        .payment-method-section h3,
        .customer-info h3 {
            margin-bottom: 1.5rem;
            color: var(--text-primary);
        }
        
        .order-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
        }
        
        .meta-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .meta-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        .meta-value {
            font-weight: 600;
            font-size: 1rem;
            color: var(--text-primary);
        }
        
        .status-pending {
            color: #f59e0b;
        }
        
        .payment-method-card {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            padding: 1.5rem;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
            background: #fff;
            transition: all 0.3s ease;
        }
        
        .payment-method-card.active {
            border-color: var(--primary-color);
            background: rgba(59, 130, 246, 0.05);
        }
        
        .payment-icon {
            width: 60px;
            height: 60px;
            background: var(--light-color);
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            font-size: 1.5rem;
        }
        
        .payment-method-card.active .payment-icon {
            background: var(--primary-color);
            color: white;
        }
        
        .payment-info h4 {
            margin: 0 0 0.5rem;
            color: var(--text-primary);
        }
        
        .payment-info p {
            margin: 0 0 0.75rem;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .accepted-cards {
            display: flex;
            gap: 0.75rem;
            font-size: 1.2rem;
            color: var(--text-secondary);
        }
        
        .btn-large {
            width: 100%;
            padding: 1rem;
            font-size: 1.1rem;
            margin-bottom: 2rem;
        }
        
        .security-badges {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            text-align: center;
        }
        
        .security-badge {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
        }
        
        .security-badge i {
            font-size: 1.25rem;
            color: var(--success-color);
        }
        
        .security-badge span {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        
        .info-box {
            background: var(--light-color);
            border-radius: var(--border-radius);
            padding: 1.25rem;
        }
        
        .info-box h4 {
            margin: 0 0 1rem;
            font-size: 0.9rem;
            color: var(--text-secondary);
            font-weight: 600;
        }
        
        .info-box p {
            margin: 0 0 0.5rem;
            font-size: 0.9rem;
            color: var(--text-primary);
            line-height: 1.5;
        }
        
        .order-summary {
            background: white;
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .order-summary h3 {
            margin-bottom: 1.5rem;
            color: var(--text-primary);
        }
        
        .order-items {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .order-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: var(--light-color);
            border-radius: var(--border-radius);
        }
        
        .order-item img {
            width: 50px;
            height: 50px;
            border-radius: var(--border-radius);
            object-fit: cover;
        }
        
        .item-details {
            flex: 1;
        }
        
        .item-details h4 {
            margin: 0 0 0.25rem;
            font-size: 0.9rem;
        }
        
        .item-details p {
            margin: 0;
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        .item-price {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .summary-details {
            margin-top: 1.5rem;
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
        
        .payment-footer {
            text-align: center;
            margin-top: 2rem;
        }
        
        @media (max-width: 992px) {
            .payment-content {
                grid-template-columns: 1fr;
            }
            
            .order-summary {
                order: -1;
            }
        }
        
        @media (max-width: 768px) {
            .checkout-steps {
                flex-direction: column;
                align-items: center;
                gap: 1rem;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .security-badges {
                flex-direction: column;
                align-items: center;
                gap: 1.5rem;
            }
        }
    </style>
</body>
</html>
