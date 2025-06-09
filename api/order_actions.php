<?php
require_once '../config/database.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'buyer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$buyer_id = $_SESSION['user_id'];

// Get POST data
$action = $_POST['action'] ?? '';
$order_id = intval($_POST['order_id'] ?? 0);

if (!$order_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit;
}

// Verify order belongs to user
$order_stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND buyer_id = ?");
$order_stmt->execute([$order_id, $buyer_id]);
$order = $order_stmt->fetch();

if (!$order) {
    echo json_encode(['success' => false, 'message' => 'Order not found or access denied']);
    exit;
}

try {
    switch ($action) {
        case 'cancel':
            // Check if order can be cancelled
            if (!in_array($order['order_status'], ['pending', 'confirmed'])) {
                echo json_encode(['success' => false, 'message' => 'Order cannot be cancelled at this stage']);
                exit;
            }
            
            // Update order status to cancelled
            $update_stmt = $pdo->prepare("
                UPDATE orders 
                SET order_status = 'cancelled', updated_at = NOW() 
                WHERE id = ? AND buyer_id = ?
            ");
            $update_stmt->execute([$order_id, $buyer_id]);
            
            if ($update_stmt->rowCount() > 0) {
                // If payment was made, you might want to initiate refund process here
                // For now, we'll just update the payment status
                if ($order['payment_status'] === 'paid') {
                    $refund_stmt = $pdo->prepare("
                        UPDATE orders 
                        SET payment_status = 'refunded' 
                        WHERE id = ? AND buyer_id = ?
                    ");
                    $refund_stmt->execute([$order_id, $buyer_id]);
                }
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Order cancelled successfully',
                    'order_status' => 'cancelled'
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to cancel order']);
            }
            break;
            
        case 'reorder':
            // Get order items
            $items_stmt = $pdo->prepare("
                SELECT product_id, quantity, price 
                FROM order_items 
                WHERE order_id = ?
            ");
            $items_stmt->execute([$order_id]);
            $items = $items_stmt->fetchAll();
            
            if (empty($items)) {
                echo json_encode(['success' => false, 'message' => 'No items found in original order']);
                exit;
            }
            
            // Add items to cart
            $added_count = 0;
            foreach ($items as $item) {
                // Check if product still exists and is available
                $product_stmt = $pdo->prepare("
                    SELECT id, stock_quantity 
                    FROM products 
                    WHERE id = ? AND status = 'active'
                ");
                $product_stmt->execute([$item['product_id']]);
                $product = $product_stmt->fetch();
                
                if ($product && $product['stock_quantity'] >= $item['quantity']) {
                    // Check if item already in cart
                    $cart_check_stmt = $pdo->prepare("
                        SELECT id, quantity 
                        FROM cart 
                        WHERE buyer_id = ? AND product_id = ?
                    ");
                    $cart_check_stmt->execute([$buyer_id, $item['product_id']]);
                    $cart_item = $cart_check_stmt->fetch();
                    
                    if ($cart_item) {
                        // Update quantity
                        $update_cart_stmt = $pdo->prepare("
                            UPDATE cart 
                            SET quantity = quantity + ?, updated_at = NOW() 
                            WHERE id = ?
                        ");
                        $update_cart_stmt->execute([$item['quantity'], $cart_item['id']]);
                    } else {
                        // Add new item to cart
                        $add_cart_stmt = $pdo->prepare("
                            INSERT INTO cart (buyer_id, product_id, quantity, created_at) 
                            VALUES (?, ?, ?, NOW())
                        ");
                        $add_cart_stmt->execute([$buyer_id, $item['product_id'], $item['quantity']]);
                    }
                    $added_count++;
                }
            }
            
            if ($added_count > 0) {
                echo json_encode([
                    'success' => true, 
                    'message' => "$added_count items added to cart",
                    'added_count' => $added_count
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'No items could be added to cart']);
            }
            break;
            
        case 'track':
            // Return tracking information
            $tracking_number = $order['tracking_number'] ?? 'TRK' . str_pad($order_id, 8, '0', STR_PAD_LEFT);
            
            echo json_encode([
                'success' => true,
                'tracking_number' => $tracking_number,
                'status' => $order['order_status'],
                'estimated_delivery' => date('Y-m-d', strtotime($order['created_at'] . ' +7 days'))
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
?>
