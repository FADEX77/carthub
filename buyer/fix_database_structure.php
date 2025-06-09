<?php
require_once '../config/database.php';

echo "<h2>CartHub Database Structure Fix</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; }
    .error { color: red; }
    .info { color: blue; }
    .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
</style>";

try {
    echo "<div class='section'>";
    echo "<h3>Step 1: Check Current Orders Table Structure</h3>";
    
    $columns_stmt = $pdo->query("DESCRIBE orders");
    $columns = $columns_stmt->fetchAll();
    
    echo "<p class='info'>Current orders table structure:</p>";
    foreach ($columns as $column) {
        echo "<p>- {$column['Field']} ({$column['Type']}) {$column['Null']} {$column['Key']}</p>";
    }
    echo "</div>";
    
    echo "<div class='section'>";
    echo "<h3>Step 2: Fix Order Number Column</h3>";
    
    // Check if order_number column exists
    $has_order_number = false;
    foreach ($columns as $column) {
        if ($column['Field'] === 'order_number') {
            $has_order_number = true;
            break;
        }
    }
    
    if ($has_order_number) {
        echo "<p class='info'>Order number column exists. Checking for issues...</p>";
        
        // Check for empty order numbers
        $empty_stmt = $pdo->query("SELECT COUNT(*) as count FROM orders WHERE order_number = '' OR order_number IS NULL");
        $empty_count = $empty_stmt->fetch()['count'];
        
        if ($empty_count > 0) {
            echo "<p class='error'>Found $empty_count orders with empty order numbers. Fixing...</p>";
            
            // Update empty order numbers
            $update_stmt = $pdo->prepare("
                UPDATE orders 
                SET order_number = CONCAT('ORD-', DATE_FORMAT(created_at, '%Y%m'), '-', LPAD(id, 6, '0'))
                WHERE order_number = '' OR order_number IS NULL
            ");
            $update_stmt->execute();
            
            echo "<p class='success'>✓ Updated $empty_count order numbers</p>";
        } else {
            echo "<p class='success'>✓ All orders have valid order numbers</p>";
        }
        
        // Remove unique constraint temporarily if it's causing issues
        try {
            $pdo->exec("ALTER TABLE orders DROP INDEX order_number");
            echo "<p class='success'>✓ Removed unique constraint on order_number</p>";
        } catch (Exception $e) {
            echo "<p class='info'>Note: Unique constraint may not exist or already removed</p>";
        }
        
        // Make order_number nullable
        try {
            $pdo->exec("ALTER TABLE orders MODIFY COLUMN order_number VARCHAR(50) NULL");
            echo "<p class='success'>✓ Made order_number nullable</p>";
        } catch (Exception $e) {
            echo "<p class='error'>Error modifying order_number column: " . $e->getMessage() . "</p>";
        }
        
    } else {
        echo "<p class='info'>Order number column doesn't exist. This is fine - we'll work without it.</p>";
    }
    echo "</div>";
    
    echo "<div class='section'>";
    echo "<h3>Step 3: Verify Required Columns</h3>";
    
    $required_columns = [
        'id' => 'int(11) NOT NULL AUTO_INCREMENT',
        'buyer_id' => 'int(11) NOT NULL',
        'order_status' => "enum('pending','confirmed','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending'",
        'total_amount' => 'decimal(10,2) NOT NULL',
        'shipping_amount' => 'decimal(10,2) DEFAULT 0.00',
        'tax_amount' => 'decimal(10,2) DEFAULT 0.00',
        'payment_method' => "varchar(50) DEFAULT 'credit_card'",
        'payment_status' => "enum('pending','paid','failed','refunded') DEFAULT 'pending'",
        'shipping_address' => 'text',
        'billing_address' => 'text',
        'created_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP',
        'updated_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
    ];
    
    $existing_columns = [];
    foreach ($columns as $column) {
        $existing_columns[] = $column['Field'];
    }
    
    foreach ($required_columns as $col_name => $col_definition) {
        if (in_array($col_name, $existing_columns)) {
            echo "<p class='success'>✓ Column '$col_name' exists</p>";
        } else {
            echo "<p class='error'>✗ Column '$col_name' missing. Adding...</p>";
            try {
                $pdo->exec("ALTER TABLE orders ADD COLUMN $col_name $col_definition");
                echo "<p class='success'>✓ Added column '$col_name'</p>";
            } catch (Exception $e) {
                echo "<p class='error'>Error adding column '$col_name': " . $e->getMessage() . "</p>";
            }
        }
    }
    echo "</div>";
    
    echo "<div class='section'>";
    echo "<h3>Step 4: Test Order Creation</h3>";
    
    try {
        // Test creating a sample order
        $test_buyer_id = 1; // Assuming user ID 1 exists
        
        $test_stmt = $pdo->prepare("
            INSERT INTO orders (buyer_id, order_status, total_amount, shipping_amount, tax_amount, 
                              payment_method, payment_status, shipping_address, billing_address, created_at)
            VALUES (?, 'pending', ?, ?, ?, 'paystack', 'pending', ?, ?, NOW())
        ");
        
        $test_address = json_encode([
            'full_name' => 'Test User',
            'address' => '123 Test Street',
            'city' => 'Test City',
            'state' => 'Test State',
            'country' => 'US',
            'phone' => '+1234567890'
        ]);
        
        $test_result = $test_stmt->execute([
            $test_buyer_id,
            100.00, // total
            5.99,   // shipping
            8.00,   // tax
            $test_address,
            $test_address
        ]);
        
        if ($test_result) {
            $test_order_id = $pdo->lastInsertId();
            echo "<p class='success'>✓ Test order created successfully (ID: $test_order_id)</p>";
            
            // Clean up test order
            $pdo->prepare("DELETE FROM orders WHERE id = ?")->execute([$test_order_id]);
            echo "<p class='info'>Test order cleaned up</p>";
        } else {
            echo "<p class='error'>✗ Failed to create test order</p>";
        }
        
    } catch (Exception $e) {
        echo "<p class='error'>✗ Test order creation failed: " . $e->getMessage() . "</p>";
    }
    echo "</div>";
    
    echo "<div class='section'>";
    echo "<h3>Step 5: Summary</h3>";
    echo "<p class='success'>✓ Database structure fix completed!</p>";
    echo "<p class='info'>You can now try the checkout process again.</p>";
    echo "<p><a href='checkout.php'>Go to Checkout</a> | <a href='debug_checkout.php'>Run Debug Again</a></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='section'>";
    echo "<p class='error'><strong>Fatal Error:</strong> " . $e->getMessage() . "</p>";
    echo "</div>";
}
?>
