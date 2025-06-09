<?php
require_once '../config/database.php';
requireLogin('buyer');

header('Content-Type: application/json');

$buyer_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'add':
            $product_id = intval($_POST['product_id']);
            $quantity = intval($_POST['quantity'] ?? 1);
            
            // Check if product exists and is active
            $product_stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND status = 'active'");
            $product_stmt->execute([$product_id]);
            $product = $product_stmt->fetch();
            
            if (!$product) {
                echo json_encode(['success' => false, 'message' => 'Product not found or unavailable']);
                exit;
            }
            
            if ($product['stock_quantity'] < $quantity) {
                echo json_encode(['success' => false, 'message' => 'Insufficient stock']);
                exit;
            }
            
            // Check if item already exists in cart
            $existing_stmt = $pdo->prepare("SELECT * FROM cart WHERE buyer_id = ? AND product_id = ?");
            $existing_stmt->execute([$buyer_id, $product_id]);
            $existing = $existing_stmt->fetch();
            
            if ($existing) {
                // Update quantity
                $new_quantity = $existing['quantity'] + $quantity;
                if ($new_quantity > $product['stock_quantity']) {
                    echo json_encode(['success' => false, 'message' => 'Cannot add more items than available in stock']);
                    exit;
                }
                
                $update_stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE buyer_id = ? AND product_id = ?");
                $update_stmt->execute([$new_quantity, $buyer_id, $product_id]);
            } else {
                // Add new item
                $insert_stmt = $pdo->prepare("INSERT INTO cart (buyer_id, product_id, quantity, added_at) VALUES (?, ?, ?, NOW())");
                $insert_stmt->execute([$buyer_id, $product_id, $quantity]);
            }
            
            echo json_encode(['success' => true, 'message' => 'Product added to cart']);
            break;
            
        case 'remove':
            $product_id = intval($_POST['product_id']);
            
            $delete_stmt = $pdo->prepare("DELETE FROM cart WHERE buyer_id = ? AND product_id = ?");
            $delete_stmt->execute([$buyer_id, $product_id]);
            
            echo json_encode(['success' => true, 'message' => 'Product removed from cart']);
            break;
            
        case 'update':
            $product_id = intval($_POST['product_id']);
            $quantity = intval($_POST['quantity']);
            
            if ($quantity <= 0) {
                // Remove item
                $delete_stmt = $pdo->prepare("DELETE FROM cart WHERE buyer_id = ? AND product_id = ?");
                $delete_stmt->execute([$buyer_id, $product_id]);
                echo json_encode(['success' => true, 'message' => 'Product removed from cart']);
            } else {
                // Update quantity
                $update_stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE buyer_id = ? AND product_id = ?");
                $update_stmt->execute([$quantity, $buyer_id, $product_id]);
                echo json_encode(['success' => true, 'message' => 'Cart updated']);
            }
            break;
            
        case 'clear':
            $clear_stmt = $pdo->prepare("DELETE FROM cart WHERE buyer_id = ?");
            $clear_stmt->execute([$buyer_id]);
            
            echo json_encode(['success' => true, 'message' => 'Cart cleared']);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
?>