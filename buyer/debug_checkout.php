<?php
require_once '../config/database.php';
requireLogin('buyer');

// Debug script to check what's causing the checkout failure
$buyer_id = $_SESSION['user_id'];

echo "<h2>CartHub Checkout Debug</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; }
    .error { color: red; }
    .info { color: blue; }
    .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
</style>";

// Check 1: Database connection
echo "<div class='section'>";
echo "<h3>1. Database Connection</h3>";
try {
    $test_query = $pdo->query("SELECT 1");
    echo "<p class='success'>✓ Database connection successful</p>";
} catch (Exception $e) {
    echo "<p class='error'>✗ Database connection failed: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Check 2: User session
echo "<div class='section'>";
echo "<h3>2. User Session</h3>";
if (isset($_SESSION['user_id'])) {
    echo "<p class='success'>✓ User logged in (ID: " . $_SESSION['user_id'] . ")</p>";
    
    // Get user details
    $user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $user_stmt->execute([$buyer_id]);
    $user = $user_stmt->fetch();
    
    if ($user) {
        echo "<p class='success'>✓ User found: " . htmlspecialchars($user['full_name']) . " (" . htmlspecialchars($user['email']) . ")</p>";
    } else {
        echo "<p class='error'>✗ User not found in database</p>";
    }
} else {
    echo "<p class='error'>✗ User not logged in</p>";
}
echo "</div>";

// Check 3: Cart items
echo "<div class='section'>";
echo "<h3>3. Cart Items</h3>";
try {
    $cart_stmt = $pdo->prepare("
        SELECT c.*, p.name, p.price, p.stock_quantity, u.full_name as vendor_name
        FROM cart c
        JOIN products p ON c.product_id = p.id
        JOIN users u ON p.vendor_id = u.id
        WHERE c.buyer_id = ?
    ");
    $cart_stmt->execute([$buyer_id]);
    $cart_items = $cart_stmt->fetchAll();
    
    if (!empty($cart_items)) {
        echo "<p class='success'>✓ Found " . count($cart_items) . " items in cart</p>";
        foreach ($cart_items as $item) {
            echo "<p class='info'>- " . htmlspecialchars($item['name']) . " (Qty: " . $item['quantity'] . ", Price: $" . $item['price'] . ", Stock: " . $item['stock_quantity'] . ")</p>";
            
            // Check stock availability
            if ($item['quantity'] > $item['stock_quantity']) {
                echo "<p class='error'>  ⚠ Insufficient stock for " . htmlspecialchars($item['name']) . "</p>";
            }
        }
    } else {
        echo "<p class='error'>✗ Cart is empty</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>✗ Error fetching cart: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Check 4: Required tables exist
echo "<div class='section'>";
echo "<h3>4. Database Tables</h3>";
$required_tables = ['orders', 'order_items', 'cart', 'products', 'users'];

foreach ($required_tables as $table) {
    try {
        $check_stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($check_stmt->rowCount() > 0) {
            echo "<p class='success'>✓ Table '$table' exists</p>";
            
            // Check table structure for orders
            if ($table === 'orders') {
                $columns_stmt = $pdo->query("DESCRIBE orders");
                $columns = $columns_stmt->fetchAll(PDO::FETCH_COLUMN);
                $required_columns = ['id', 'buyer_id', 'order_status', 'total_amount', 'shipping_amount', 'tax_amount', 'payment_method', 'payment_status', 'shipping_address', 'billing_address'];
                
                foreach ($required_columns as $col) {
                    if (in_array($col, $columns)) {
                        echo "<p class='success'>  ✓ Column '$col' exists</p>";
                    } else {
                        echo "<p class='error'>  ✗ Column '$col' missing</p>";
                    }
                }
            }
        } else {
            echo "<p class='error'>✗ Table '$table' does not exist</p>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>✗ Error checking table '$table': " . $e->getMessage() . "</p>";
    }
}
echo "</div>";

// Check 5: Test order creation
echo "<div class='section'>";
echo "<h3>5. Test Order Creation</h3>";

if (!empty($cart_items)) {
    try {
        // Calculate totals
        $subtotal = 0;
        foreach ($cart_items as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }
        $shipping = 5.99;
        $tax = $subtotal * 0.08;
        $total = $subtotal + $tax + $shipping;
        
        echo "<p class='info'>Calculated totals: Subtotal: $" . number_format($subtotal, 2) . ", Tax: $" . number_format($tax, 2) . ", Shipping: $" . number_format($shipping, 2) . ", Total: $" . number_format($total, 2) . "</p>";
        
        // Test sample addresses
        $shipping_address = [
            'full_name' => 'Test User',
            'address' => '123 Test Street',
            'city' => 'Test City',
            'state' => 'Test State',
            'zip_code' => '12345',
            'country' => 'US',
            'phone' => '+1234567890'
        ];
        
        $billing_address = $shipping_address;
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // Test order insertion
        $order_stmt = $pdo->prepare("
            INSERT INTO orders (buyer_id, order_status, total_amount, shipping_amount, tax_amount, 
                              payment_method, payment_status, shipping_address, billing_address, created_at)
            VALUES (?, 'pending', ?, ?, ?, 'paystack', 'pending', ?, ?, NOW())
        ");
        
        $order_result = $order_stmt->execute([
            $buyer_id,
            $total,
            $shipping,
            $tax,
            json_encode($shipping_address),
            json_encode($billing_address)
        ]);
        
        if ($order_result) {
            $test_order_id = $pdo->lastInsertId();
            echo "<p class='success'>✓ Test order created successfully (ID: $test_order_id)</p>";
            
            // Test order items insertion
            $item_stmt = $pdo->prepare("
                INSERT INTO order_items (order_id, product_id, vendor_id, quantity, unit_price, total_price, product_name)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $items_success = 0;
            foreach ($cart_items as $item) {
                $item_total = $item['price'] * $item['quantity'];
                $item_result = $item_stmt->execute([
                    $test_order_id,
                    $item['product_id'],
                    $item['vendor_id'] ?? 1, // Default vendor if missing
                    $item['quantity'],
                    $item['price'],
                    $item_total,
                    $item['name']
                ]);
                
                if ($item_result) {
                    $items_success++;
                    echo "<p class='success'>  ✓ Order item added: " . htmlspecialchars($item['name']) . "</p>";
                } else {
                    echo "<p class='error'>  ✗ Failed to add order item: " . htmlspecialchars($item['name']) . "</p>";
                    $error_info = $item_stmt->errorInfo();
                    echo "<p class='error'>    Error: " . $error_info[2] . "</p>";
                }
            }
            
            if ($items_success === count($cart_items)) {
                echo "<p class='success'>✓ All order items added successfully</p>";
                echo "<p class='success'>✓ Test order creation completed successfully!</p>";
            } else {
                echo "<p class='error'>✗ Some order items failed to add</p>";
            }
            
            // Rollback test transaction
            $pdo->rollBack();
            echo "<p class='info'>Test transaction rolled back (no actual data saved)</p>";
            
        } else {
            echo "<p class='error'>✗ Failed to create test order</p>";
            $error_info = $order_stmt->errorInfo();
            echo "<p class='error'>Error: " . $error_info[2] . "</p>";
            $pdo->rollBack();
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<p class='error'>✗ Exception during test order creation: " . $e->getMessage() . "</p>";
        echo "<p class='error'>File: " . $e->getFile() . " Line: " . $e->getLine() . "</p>";
    }
} else {
    echo "<p class='error'>Cannot test order creation - cart is empty</p>";
}
echo "</div>";

// Check 6: PHP Configuration
echo "<div class='section'>";
echo "<h3>6. PHP Configuration</h3>";
echo "<p class='info'>PHP Version: " . phpversion() . "</p>";
echo "<p class='info'>PDO Available: " . (extension_loaded('pdo') ? 'Yes' : 'No') . "</p>";
echo "<p class='info'>PDO MySQL Available: " . (extension_loaded('pdo_mysql') ? 'Yes' : 'No') . "</p>";
echo "<p class='info'>JSON Available: " . (function_exists('json_encode') ? 'Yes' : 'No') . "</p>";
echo "<p class='info'>Error Reporting: " . error_reporting() . "</p>";
echo "</div>";

echo "<div class='section'>";
echo "<h3>7. Recommendations</h3>";
echo "<p class='info'>If all checks pass above, the issue might be:</p>";
echo "<ul>";
echo "<li>Form validation failing (check required fields)</li>";
echo "<li>JavaScript errors preventing form submission</li>";
echo "<li>Session timeout or CSRF issues</li>";
echo "<li>Database permissions or constraints</li>";
echo "</ul>";
echo "<p class='info'>Check the browser console for JavaScript errors and server error logs for more details.</p>";
echo "</div>";
?>
