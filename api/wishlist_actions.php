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
            
            // Check if product exists
            $product_stmt = $pdo->prepare("SELECT id FROM products WHERE id = ?");
            $product_stmt->execute([$product_id]);
            
            if (!$product_stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Product not found']);
                exit;
            }
            
            // Check if already in wishlist
            $existing_stmt = $pdo->prepare("SELECT id FROM wishlist WHERE buyer_id = ? AND product_id = ?");
            $existing_stmt->execute([$buyer_id, $product_id]);
            
            if ($existing_stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Product already in wishlist']);
                exit;
            }
            
            // Add to wishlist
            $insert_stmt = $pdo->prepare("INSERT INTO wishlist (buyer_id, product_id, added_at) VALUES (?, ?, NOW())");
            $insert_stmt->execute([$buyer_id, $product_id]);
            
            echo json_encode(['success' => true, 'message' => 'Product added to wishlist']);
            break;
            
        case 'remove':
            $product_id = intval($_POST['product_id']);
            
            $delete_stmt = $pdo->prepare("DELETE FROM wishlist WHERE buyer_id = ? AND product_id = ?");
            $delete_stmt->execute([$buyer_id, $product_id]);
            
            echo json_encode(['success' => true, 'message' => 'Product removed from wishlist']);
            break;
            
        case 'clear':
            $clear_stmt = $pdo->prepare("DELETE FROM wishlist WHERE buyer_id = ?");
            $clear_stmt->execute([$buyer_id]);
            
            echo json_encode(['success' => true, 'message' => 'Wishlist cleared']);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
?>