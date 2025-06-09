<?php
require_once '../config/database.php';
requireLogin('buyer');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$buyer_id = $_SESSION['user_id'];

// Get cart items
$cart_stmt = $pdo->prepare("
    SELECT c.*, p.name, p.price, p.image_path, p.stock_quantity, p.vendor_id, u.full_name as vendor_name
    FROM cart c
    JOIN products p ON c.product_id = p.id
    JOIN users u ON p.vendor_id = u.id
    WHERE c.buyer_id = ?
    ORDER BY c.added_at DESC
");
$cart_stmt->execute([$buyer_id]);
$cart_items = $cart_stmt->fetchAll();

// Redirect if cart is empty
if (empty($cart_items)) {
    $_SESSION['error'] = 'Your cart is empty. Please add items before checkout.';
    header('Location: cart.php');
    exit;
}

// Get user data
$user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->execute([$buyer_id]);
$user = $user_stmt->fetch();

// Calculate totals
$subtotal = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}

$shipping = 5.99; // Default shipping cost
$tax_rate = 0.08; // 8% tax rate
$tax = $subtotal * $tax_rate;
$total = $subtotal + $tax + $shipping;

// Handle form submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    try {
        // Validate form data
        $required_fields = [
            'shipping_full_name' => 'Full name',
            'shipping_address' => 'Address',
            'shipping_city' => 'City',
            'shipping_state' => 'State',
            'shipping_country' => 'Country',
            'shipping_phone' => 'Phone number'
        ];
        
        $validation_errors = [];
        foreach ($required_fields as $field => $label) {
            if (empty($_POST[$field])) {
                $validation_errors[] = $label . ' is required';
            }
        }
        
        if (!empty($validation_errors)) {
            throw new Exception('Please fill in all required fields: ' . implode(', ', $validation_errors));
        }
        
        // Check stock availability
        foreach ($cart_items as $item) {
            if ($item['quantity'] > $item['stock_quantity']) {
                throw new Exception('Insufficient stock for ' . $item['name'] . '. Available: ' . $item['stock_quantity']);
            }
        }
        
        // Use htmlspecialchars directly instead of sanitize function
        $shipping_address = [
            'full_name' => htmlspecialchars(trim($_POST['shipping_full_name']), ENT_QUOTES, 'UTF-8'),
            'address' => htmlspecialchars(trim($_POST['shipping_address']), ENT_QUOTES, 'UTF-8'),
            'city' => htmlspecialchars(trim($_POST['shipping_city']), ENT_QUOTES, 'UTF-8'),
            'state' => htmlspecialchars(trim($_POST['shipping_state']), ENT_QUOTES, 'UTF-8'),
            'zip_code' => htmlspecialchars(trim($_POST['shipping_zip'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'country' => htmlspecialchars(trim($_POST['shipping_country']), ENT_QUOTES, 'UTF-8'),
            'phone' => htmlspecialchars(trim($_POST['shipping_phone']), ENT_QUOTES, 'UTF-8')
        ];
        
        // Handle billing address
        if (isset($_POST['same_as_shipping']) || empty($_POST['billing_full_name'])) {
            $billing_address = $shipping_address;
        } else {
            $billing_address = [
                'full_name' => htmlspecialchars(trim($_POST['billing_full_name']), ENT_QUOTES, 'UTF-8'),
                'address' => htmlspecialchars(trim($_POST['billing_address']), ENT_QUOTES, 'UTF-8'),
                'city' => htmlspecialchars(trim($_POST['billing_city']), ENT_QUOTES, 'UTF-8'),
                'state' => htmlspecialchars(trim($_POST['billing_state']), ENT_QUOTES, 'UTF-8'),
                'zip_code' => htmlspecialchars(trim($_POST['billing_zip'] ?? ''), ENT_QUOTES, 'UTF-8'),
                'country' => htmlspecialchars(trim($_POST['billing_country']), ENT_QUOTES, 'UTF-8'),
                'phone' => htmlspecialchars(trim($_POST['billing_phone']), ENT_QUOTES, 'UTF-8')
            ];
        }
        
        // Create order in database
        $pdo->beginTransaction();
        
        // Generate unique order number
        $order_number = 'ORD-' . date('Ym') . '-' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
        
        // Check if order_number column exists
        $columns_stmt = $pdo->query("SHOW COLUMNS FROM orders LIKE 'order_number'");
        $has_order_number = $columns_stmt->rowCount() > 0;
        
        if ($has_order_number) {
            // Insert order with order_number
            $order_stmt = $pdo->prepare("
                INSERT INTO orders (buyer_id, order_number, order_status, total_amount, shipping_amount, tax_amount, 
                                  payment_method, payment_status, shipping_address, billing_address, created_at)
                VALUES (?, ?, 'pending', ?, ?, ?, 'paystack', 'pending', ?, ?, NOW())
            ");
            
            $order_success = $order_stmt->execute([
                $buyer_id,
                $order_number,
                $total,
                $shipping,
                $tax,
                json_encode($shipping_address),
                json_encode($billing_address)
            ]);
        } else {
            // Insert order without order_number
            $order_stmt = $pdo->prepare("
                INSERT INTO orders (buyer_id, order_status, total_amount, shipping_amount, tax_amount, 
                                  payment_method, payment_status, shipping_address, billing_address, created_at)
                VALUES (?, 'pending', ?, ?, ?, 'paystack', 'pending', ?, ?, NOW())
            ");
            
            $order_success = $order_stmt->execute([
                $buyer_id,
                $total,
                $shipping,
                $tax,
                json_encode($shipping_address),
                json_encode($billing_address)
            ]);
        }
        
        if (!$order_success) {
            $error_info = $order_stmt->errorInfo();
            throw new Exception('Failed to create order: ' . $error_info[2]);
        }
        
        $order_id = $pdo->lastInsertId();
        
        if (!$order_id) {
            throw new Exception('Failed to get order ID');
        }
        
        // Insert order items
        // Check the structure of order_items table
$columns_stmt = $pdo->query("SHOW COLUMNS FROM order_items");
$columns = [];
while ($col = $columns_stmt->fetch()) {
    $columns[] = $col['Field'];
}

// Prepare the SQL statement based on available columns
$fields = ['order_id', 'product_id', 'vendor_id', 'quantity'];
$placeholders = ['?', '?', '?', '?'];
$values = [$order_id, $item['product_id'], $item['vendor_id'], $item['quantity']];

// Add price fields if they exist
if (in_array('unit_price', $columns)) {
    $fields[] = 'unit_price';
    $placeholders[] = '?';
    $values[] = $item['price'];
} else if (in_array('price', $columns)) {
    $fields[] = 'price';
    $placeholders[] = '?';
    $values[] = $item['price'];
}

if (in_array('total_price', $columns)) {
    $fields[] = 'total_price';
    $placeholders[] = '?';
    $values[] = $item['price'] * $item['quantity'];
}

// Add product name if it exists
if (in_array('product_name', $columns)) {
    $fields[] = 'product_name';
    $placeholders[] = '?';
    $values[] = $item['name'];
}

// Build the SQL query
$sql = "INSERT INTO order_items (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";

// Insert order items
    $item_stmt = $pdo->prepare($sql);

    foreach ($cart_items as $item) {
        $item_total = $item['price'] * $item['quantity'];
        
        // Update values array for this specific item
        $values = [$order_id, $item['product_id'], $item['vendor_id'], $item['quantity']];
        
        // Add price fields if they exist
        if (in_array('unit_price', $columns)) {
            $values[] = $item['price'];
        } else if (in_array('price', $columns)) {
            $values[] = $item['price'];
        }
        
        if (in_array('total_price', $columns)) {
            $values[] = $item_total;
        }
        
        // Add product name if it exists
        if (in_array('product_name', $columns)) {
            $values[] = $item['name'];
        }
        
        $item_success = $item_stmt->execute($values);
        
        if (!$item_success) {
            $error_info = $item_stmt->errorInfo();
            throw new Exception('Failed to add order item: ' . $error_info[2]);
        }
        
        // Update product stock
        $stock_stmt = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ? AND stock_quantity >= ?");
        $stock_success = $stock_stmt->execute([$item['quantity'], $item['product_id'], $item['quantity']]);
        
        if (!$stock_success || $stock_stmt->rowCount() === 0) {
            throw new Exception('Failed to update stock for ' . $item['name'] . '. Item may be out of stock.');
        }
            
            if (!$item_success) {
                $error_info = $item_stmt->errorInfo();
                throw new Exception('Failed to add order item: ' . $error_info[2]);
            }
            
            // Update product stock
            $stock_stmt = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ? AND stock_quantity >= ?");
            $stock_success = $stock_stmt->execute([$item['quantity'], $item['product_id'], $item['quantity']]);
            
            if (!$stock_success || $stock_stmt->rowCount() === 0) {
                throw new Exception('Failed to update stock for ' . $item['name'] . '. Item may be out of stock.');
            }
        }
        
        $pdo->commit();
        
        // Redirect to Paystack payment
        $payment_data = [
            'order_id' => $order_id,
            'amount' => $total * 100, // Paystack expects amount in kobo (cents)
            'email' => $user['email'],
            'customer_name' => $shipping_address['full_name'],
            'phone' => $shipping_address['phone']
        ];
        
        // Store payment data in session for Paystack callback
        $_SESSION['payment_data'] = $payment_data;
        
        // Redirect to payment page - IMPORTANT: This is the key part that needs to work
        header('Location: payment.php?order_id=' . $order_id);
        exit; // Make sure to exit after redirect
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
        error_log("Checkout error: " . $error . " - User ID: " . $buyer_id);
    }
}

?>

<!DOCTYPE html>
<hlang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - CartHub</title>
    <link rel="stylesheet" href="../css/modern_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="icon" href="images/carthub-logo.png" type="image/png">
</head>
<>
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
                        <li><a href="wishlist.php">Wishlist</a></li>
                        <li><a href="../logout.php" class="btn btn-danger">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main class="checkout">
        <div class="container">
            <div class="checkout-header">
                <h1>Checkout</h1>
                <div class="checkout-steps">
                    <div class="step active">
                        <span class="step-number">1</span>
                        <span class="step-label">Shipping</span>
                    </div>
                    <div class="step">
                        <span class="step-number">2</span>
                        <span class="step-label">Payment</span>
                    </div>
                    <div class="step">
                        <span class="step-number">3</span>
                        <span class="step-label">Confirmation</span>
                    </div>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <!-- IMPORTANT: Make sure the form action is empty or points to the same page -->
            <form method="POST" action="" class="checkout-form" id="checkout-form">
                <div class="checkout-container">
                    <div class="checkout-main">
                        <!-- Shipping Information -->
                        <div class="form-section">
                            <h3><i class="fas fa-shipping-fast"></i> Shipping Information</h3>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="shipping_full_name">Full Name *</label>
                                    <input type="text" name="shipping_full_name" id="shipping_full_name" required
                                           value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="shipping_phone">Phone Number *</label>
                                    <input type="tel" name="shipping_phone" id="shipping_phone" required
                                           value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="shipping_address">Address *</label>
                                <input type="text" name="shipping_address" id="shipping_address" required
                                       value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="shipping_city">City *</label>
                                    <input type="text" name="shipping_city" id="shipping_city" required
                                           value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="shipping_state">State *</label>
                                    <input type="text" name="shipping_state" id="shipping_state" required
                                           value="<?php echo htmlspecialchars($user['state'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="shipping_zip">ZIP Code</label>
                                    <input type="text" name="shipping_zip" id="shipping_zip"
                                           value="<?php echo htmlspecialchars($user['zip_code'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="shipping_country">Country *</label>
                                <select name="shipping_country" id="shipping_country" required>
                                    <option value="">Select Country</option>
                                    <option value="NG" <?php echo ($user['country'] ?? '') === 'NG' ? 'selected' : ''; ?>>Nigeria</option>
                                    <option value="GH" <?php echo ($user['country'] ?? '') === 'GH' ? 'selected' : ''; ?>>Ghana</option>
                                    <option value="KE" <?php echo ($user['country'] ?? '') === 'KE' ? 'selected' : ''; ?>>Kenya</option>
                                    <option value="ZA" <?php echo ($user['country'] ?? '') === 'ZA' ? 'selected' : ''; ?>>South Africa</option>
                                    <option value="US" <?php echo ($user['country'] ?? '') === 'US' ? 'selected' : ''; ?>>United States</option>
                                    <option value="UK" <?php echo ($user['country'] ?? '') === 'UK' ? 'selected' : ''; ?>>United Kingdom</option>
                                    <option value="CA" <?php echo ($user['country'] ?? '') === 'CA' ? 'selected' : ''; ?>>Canada</option>
                                </select>
                            </div>
                        </div>

                        <!-- Billing Information -->
                        <div class="form-section">
                            <div class="section-header">
                                <h3><i class="fas fa-credit-card"></i> Billing Information</h3>
                                <label class="checkbox-container">
                                    <input type="checkbox" id="same_as_shipping" name="same_as_shipping" checked onchange="toggleBillingAddress()">
                                    <span class="checkmark"></span>
                                    Same as shipping address
                                </label>
                            </div>
                            
                            <div id="billing_fields" style="display: none;">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="billing_full_name">Full Name</label>
                                        <input type="text" name="billing_full_name" id="billing_full_name"
                                               value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="billing_phone">Phone Number</label>
                                        <input type="tel" name="billing_phone" id="billing_phone"
                                               value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="billing_address">Address</label>
                                    <input type="text" name="billing_address" id="billing_address"
                                           value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="billing_city">City</label>
                                        <input type="text" name="billing_city" id="billing_city"
                                               value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="billing_state">State</label>
                                        <input type="text" name="billing_state" id="billing_state"
                                               value="<?php echo htmlspecialchars($user['state'] ?? ''); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="billing_zip">ZIP Code</label>
                                        <input type="text" name="billing_zip" id="billing_zip"
                                               value="<?php echo htmlspecialchars($user['zip_code'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="billing_country">Country</label>
                                    <select name="billing_country" id="billing_country">
                                        <option value="">Select Country</option>
                                        <option value="NG" <?php echo ($user['country'] ?? '') === 'NG' ? 'selected' : ''; ?>>Nigeria</option>
                                        <option value="GH" <?php echo ($user['country'] ?? '') === 'GH' ? 'selected' : ''; ?>>Ghana</option>
                                        <option value="KE" <?php echo ($user['country'] ?? '') === 'KE' ? 'selected' : ''; ?>>Kenya</option>
                                        <option value="ZA" <?php echo ($user['country'] ?? '') === 'ZA' ? 'selected' : ''; ?>>South Africa</option>
                                        <option value="US" <?php echo ($user['country'] ?? '') === 'US' ? 'selected' : ''; ?>>United States</option>
                                        <option value="UK" <?php echo ($user['country'] ?? '') === 'UK' ? 'selected' : ''; ?>>United Kingdom</option>
                                        <option value="CA" <?php echo ($user['country'] ?? '') === 'CA' ? 'selected' : ''; ?>>Canada</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Order Review -->
                        <div class="form-section">
                            <h3><i class="fas fa-list"></i> Order Review</h3>
                            <div class="order-items">
                                <?php foreach ($cart_items as $item): ?>
                                    <div class="order-item">
                                        <img src="<?php echo $item['image_path'] ? '../uploads/products/' . $item['image_path'] : '../images/placeholder.png'; ?>" 
                                             alt="<?php echo htmlspecialchars($item['name']); ?>">
                                        <div class="item-details">
                                            <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                                            <p>by <?php echo htmlspecialchars($item['vendor_name']); ?></p>
                                            <p>Quantity: <?php echo $item['quantity']; ?></p>
                                            <p>Stock: <?php echo $item['stock_quantity']; ?> available</p>
                                        </div>
                                        <div class="item-price">
                                            $<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Order Summary -->
                    <div class="checkout-sidebar">
                        <div class="order-summary">
                            <h3>Order Summary</h3>
                            
                            <div class="summary-row">
                                <span>Subtotal</span>
                                <span>$<?php echo number_format($subtotal, 2); ?></span>
                            </div>
                            
                            <div class="summary-row">
                                <span>Shipping</span>
                                <span>$<?php echo number_format($shipping, 2); ?></span>
                            </div>
                            
                            <div class="summary-row">
                                <span>Tax (8%)</span>
                                <span>$<?php echo number_format($tax, 2); ?></span>
                            </div>
                            
                            <div class="summary-divider"></div>
                            
                            <div class="summary-row total">
                                <span>Total</span>
                                <span>$<?php echo number_format($total, 2); ?></span>
                            </div>
                            
                            <div class="payment-info">
                                <div class="payment-method">
                                    <i class="fas fa-credit-card"></i>
                                    <span>Pay with Paystack</span>
                                </div>
                                <div class="security-info">
                                    <i class="fas fa-lock"></i>
                                    <span>Secure SSL encrypted payment</span>
                                </div>
                            </div>
                            
                            <!-- IMPORTANT: Make sure the button type is submit and has name="place_order" -->
                            <button type="submit" name="place_order" class="btn btn-primary btn-large" id="place-order-btn">
                                <i class="fas fa-credit-card"></i>
                                Proceed to Payment
                            </button>
                            
                            <div class="accepted-cards">
                                <p>We Accept</p>
                                <div class="card-icons">
                                    <i class="fab fa-cc-visa"></i>
                                    <i class="fab fa-cc-mastercard"></i>
                                    <i class="fab fa-cc-amex"></i>
                                    <i class="fas fa-university"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
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
        function toggleBillingAddress() {
            const checkbox = document.getElementById('same_as_shipping');
            const billingFields = document.getElementById('billing_fields');
            
            if (checkbox.checked) {
                billingFields.style.display = 'none';
                copyShippingToBilling();
            } else {
                billingFields.style.display = 'block';
            }
        }
        
        function copyShippingToBilling() {
            const shippingFields = ['full_name', 'phone', 'address', 'city', 'state', 'zip', 'country'];
            
            shippingFields.forEach(field => {
                const shippingField = document.getElementById('shipping_' + field);
                const billingField = document.getElementById('billing_' + field);
                
                if (shippingField && billingField) {
                    billingField.value = shippingField.value;
                }
            });
        }
        
        // Copy shipping to billing when shipping fields change
        document.addEventListener('DOMContentLoaded', function() {
            const shippingFields = document.querySelectorAll('[id^="shipping_"]');
            
            shippingFields.forEach(field => {
                field.addEventListener('input', function() {
                    if (document.getElementById('same_as_shipping').checked) {
                        copyShippingToBilling();
                    }
                });
            });
            
            // Initial copy
            copyShippingToBilling();
            
            // Disable form resubmission on page refresh
            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }
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
        
        .checkout {
            background: #f8fafc;
            min-height: 100vh;
            padding: 2rem 0;
        }
        
        .checkout-header {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .checkout-header h1 {
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
        
        .checkout-container {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 3rem;
        }
        
        .checkout-main {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }
        
        .form-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .form-section h3 {
            margin-bottom: 1.5rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .checkbox-container {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .form-row.three-cols {
            grid-template-columns: 1fr 1fr 1fr;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-primary);
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
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
            padding: 1rem;
            background: var(--light-color);
            border-radius: var(--border-radius);
        }
        
        .order-item img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: var(--border-radius);
        }
        
        .item-details {
            flex: 1;
        }
        
        .item-details h4 {
            margin: 0 0 0.25rem;
            font-size: 1rem;
        }
        
        .item-details p {
            margin: 0 0 0.25rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        .item-price {
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .checkout-sidebar {
            position: sticky;
            top: 2rem;
            height: fit-content;
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
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
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
        
        .payment-info {
            margin: 1.5rem 0;
        }
        
        .payment-method,
        .security-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        
        .payment-method i {
            color: var(--primary-color);
        }
        
        .security-info i {
            color: var(--success-color);
        }
        
        .btn-large {
            width: 100%;
            padding: 1rem;
            font-size: 1rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }
        
        .btn-large:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .accepted-cards {
            text-align: center;
        }
        
        .accepted-cards p {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }
        
        .card-icons {
            display: flex;
            justify-content: center;
            gap: 1rem;
            font-size: 1.5rem;
            color: var(--text-secondary);
        }
        
        @media (max-width: 992px) {
            .checkout-container {
                grid-template-columns: 1fr;
            }
            
            .checkout-sidebar {
                order: -1;
            }
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .checkout-steps {
                flex-direction: column;
                align-items: center;
                gap: 1rem;
            }
        }
    </style>
</body>
</html>
