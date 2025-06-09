<?php
require_once '../config/database.php';
requireLogin('buyer');

$buyer_id = $_SESSION['user_id'];

// Get cart items
$cart_stmt = $pdo->prepare("
    SELECT c.*, p.name, p.price, p.image_path, p.stock_quantity, u.full_name as vendor_name
    FROM cart c
    JOIN products p ON c.product_id = p.id
    JOIN users u ON p.vendor_id = u.id
    WHERE c.buyer_id = ?
    ORDER BY c.added_at DESC
");
$cart_stmt->execute([$buyer_id]);
$cart_items = $cart_stmt->fetchAll();

// Calculate totals
$subtotal = 0;
$shipping = 5.99; // Default shipping cost
$tax_rate = 0.08; // 8% tax rate

foreach ($cart_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}

$tax = $subtotal * $tax_rate;
$total = $subtotal + $tax + $shipping;

// Handle quantity updates via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_quantity') {
    $product_id = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity']);
    
    if ($quantity <= 0) {
        // Remove item if quantity is 0 or negative
        $delete_stmt = $pdo->prepare("DELETE FROM cart WHERE buyer_id = ? AND product_id = ?");
        $delete_stmt->execute([$buyer_id, $product_id]);
        echo json_encode(['success' => true, 'removed' => true]);
        exit;
    } else {
        // Update quantity
        $update_stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE buyer_id = ? AND product_id = ?");
        $update_stmt->execute([$quantity, $buyer_id, $product_id]);
        echo json_encode(['success' => true]);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - CartHub</title>
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
                        <li><a href="cart.php" class="active">Cart (<?php echo count($cart_items); ?>)</a></li>
                        <li><a href="orders.php">My Orders</a></li>
                        <li><a href="wishlist.php">Wishlist</a></li>
                        <li><a href="settings.php">Settings</a></li>
                        <li><a href="../logout.php" class="btn btn-danger">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main class="dashboard">
        <div class="container">
            <div class="dashboard-header">
                <h1 class="dashboard-title">Shopping Cart</h1>
                <p class="dashboard-subtitle">Review your items and proceed to checkout</p>
            </div>

            <?php if (!empty($cart_items)): ?>
                <div class="cart-container">
                    <div class="cart-items">
                        <div class="table-container">
                            <div class="table-header">
                                <h3 class="table-title">Cart Items (<?php echo count($cart_items); ?>)</h3>
                            </div>
                            <table class="cart-table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Price</th>
                                        <th>Quantity</th>
                                        <th>Total</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cart_items as $item): ?>
                                        <tr class="cart-row" data-product-id="<?php echo $item['product_id']; ?>">
                                            <td class="product-cell">
                                                <div class="product-info">
                                                    <img src="<?php echo $item['image_path'] ? '../uploads/products/' . $item['image_path'] : '/placeholder.svg?height=80&width=80'; ?>" 
                                                         alt="<?php echo htmlspecialchars($item['name']); ?>">
                                                    <div>
                                                        <h4><a href="product_detail.php?id=<?php echo $item['product_id']; ?>"><?php echo htmlspecialchars($item['name']); ?></a></h4>
                                                        <p>by <?php echo htmlspecialchars($item['vendor_name']); ?></p>
                                                        <p class="stock-info"><?php echo $item['stock_quantity']; ?> in stock</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="price-cell">$<?php echo number_format($item['price'], 2); ?></td>
                                            <td class="quantity-cell">
                                                <div class="quantity-control">
                                                    <button class="quantity-btn minus" onclick="updateQuantity(<?php echo $item['product_id']; ?>, -1)">-</button>
                                                    <input type="number" value="<?php echo $item['quantity']; ?>" min="1" max="<?php echo $item['stock_quantity']; ?>" 
                                                           onchange="updateQuantity(<?php echo $item['product_id']; ?>, this.value, true)">
                                                    <button class="quantity-btn plus" onclick="updateQuantity(<?php echo $item['product_id']; ?>, 1)">+</button>
                                                </div>
                                            </td>
                                            <td class="total-cell">$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                                            <td class="actions-cell">
                                                <button class="btn-icon remove-btn" onclick="removeItem(<?php echo $item['product_id']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                <button class="btn-icon save-btn" onclick="saveForLater(<?php echo $item['product_id']; ?>)">
                                                    <i class="fas fa-heart"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="cart-actions">
                            <a href="browse.php" class="btn btn-secondary">Continue Shopping</a>
                            <button class="btn btn-danger" onclick="clearCart()">Clear Cart</button>
                        </div>
                    </div>
                    
                    <div class="cart-summary">
                        <div class="summary-container">
                            <h3>Order Summary</h3>
                            
                            <div class="summary-row">
                                <span>Subtotal</span>
                                <span id="subtotal">$<?php echo number_format($subtotal, 2); ?></span>
                            </div>
                            
                            <div class="summary-row">
                                <span>Shipping</span>
                                <span id="shipping">$<?php echo number_format($shipping, 2); ?></span>
                            </div>
                            
                            <div class="summary-row">
                                <span>Tax (8%)</span>
                                <span id="tax">$<?php echo number_format($tax, 2); ?></span>
                            </div>
                            
                            <div class="summary-divider"></div>
                            
                            <div class="summary-row total">
                                <span>Total</span>
                                <span id="total">$<?php echo number_format($total, 2); ?></span>
                            </div>
                            
                            <div class="promo-code">
                                <input type="text" placeholder="Promo Code">
                                <button class="btn btn-secondary">Apply</button>
                            </div>
                            
                            <button class="btn btn-primary checkout-btn" onclick="proceedToCheckout()">
                                Proceed to Checkout
                            </button>
                            
                            <div class="payment-methods">
                                <p>We Accept</p>
                                <div class="payment-icons">
                                    <i class="fab fa-cc-visa"></i>
                                    <i class="fab fa-cc-mastercard"></i>
                                    <i class="fab fa-cc-amex"></i>
                                    <i class="fab fa-cc-paypal"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty-cart">
                    <div class="empty-cart-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <h2>Your cart is empty</h2>
                    <p>Looks like you haven't added any products to your cart yet.</p>
                    <a href="browse.php" class="btn btn-primary">Start Shopping</a>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <div class="container">
            <div class="footer-bottom">
                <p>&copy; 2024 CartHub. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        function updateQuantity(productId, change, isAbsolute = false) {
            const row = document.querySelector(`.cart-row[data-product-id="${productId}"]`);
            const input = row.querySelector('.quantity-control input');
            const currentQty = parseInt(input.value);
            
            let newQty;
            if (isAbsolute) {
                newQty = parseInt(change);
            } else {
                newQty = currentQty + parseInt(change);
            }
            
            // Ensure quantity is within valid range
            newQty = Math.max(0, Math.min(newQty, parseInt(input.max)));
            
            // Update UI immediately for better UX
            input.value = newQty;
            
            // Send update to server
            fetch('cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_quantity&product_id=${productId}&quantity=${newQty}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.removed || newQty === 0) {
                        // Remove row if item was removed
                        row.remove();
                        updateCartCount(-1);
                    }
                    
                    // Update totals
                    updateCartTotals();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // Revert to original value on error
                input.value = currentQty;
                alert('Failed to update quantity. Please try again.');
            });
        }
        
        function removeItem(productId) {
            if (confirm('Are you sure you want to remove this item from your cart?')) {
                updateQuantity(productId, 0, true);
            }
        }
        
        function saveForLater(productId) {
            fetch('../api/wishlist_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=add&product_id=${productId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Item saved to your wishlist!');
                } else {
                    alert(data.message || 'Failed to save item to wishlist');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }
        
        function clearCart() {
            if (confirm('Are you sure you want to clear your entire cart?')) {
                fetch('../api/cart_actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=clear'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert(data.message || 'Failed to clear cart');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                });
            }
        }
        
        function updateCartTotals() {
            // Calculate new totals based on current quantities
            let subtotal = 0;
            const rows = document.querySelectorAll('.cart-row');
            
            rows.forEach(row => {
                const price = parseFloat(row.querySelector('.price-cell').textContent.replace('$', ''));
                const quantity = parseInt(row.querySelector('.quantity-control input').value);
                const total = price * quantity;
                
                // Update row total
                row.querySelector('.total-cell').textContent = '$' + total.toFixed(2);
                
                subtotal += total;
            });
            
            // Update summary
            const shipping = <?php echo $shipping; ?>;
            const taxRate = <?php echo $tax_rate; ?>;
            const tax = subtotal * taxRate;
            const total = subtotal + tax + shipping;
            
            document.getElementById('subtotal').textContent = '$' + subtotal.toFixed(2);
            document.getElementById('tax').textContent = '$' + tax.toFixed(2);
            document.getElementById('total').textContent = '$' + total.toFixed(2);
            
            // Update cart count in header if needed
            if (rows.length !== <?php echo count($cart_items); ?>) {
                updateCartCount(rows.length - <?php echo count($cart_items); ?>);
            }
        }
        
        function updateCartCount(change) {
            const cartCountElement = document.querySelector('.nav-links a[href="cart.php"]');
            if (cartCountElement) {
                const currentCount = parseInt(cartCountElement.textContent.match(/\d+/)[0] || 0);
                const newCount = Math.max(0, currentCount + change);
                cartCountElement.textContent = `Cart (${newCount})`;
            }
        }
        
        function proceedToCheckout() {
            window.location.href = 'checkout.php';
        }
    </script>

    <style>
        .cart-container {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 2rem;
        }
        
        .cart-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .cart-table th {
            text-align: left;
            padding: 1rem;
            background-color: var(--light-color);
            font-weight: 600;
            color: var(--text-secondary);
        }
        
        .cart-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }
        
        .product-cell {
            width: 40%;
        }
        
        .product-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .product-info img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: var(--border-radius);
        }
        
        .product-info h4 {
            margin: 0 0 0.25rem;
            font-size: 1rem;
        }
        
        .product-info h4 a {
            color: var(--text-primary);
            text-decoration: none;
        }
        
        .product-info p {
            margin: 0 0 0.25rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        .stock-info {
            font-size: 0.75rem !important;
            color: var(--success-color) !important;
        }
        
        .quantity-control {
            display: flex;
            align-items: center;
            max-width: 120px;
        }
        
        .quantity-control input {
            width: 40px;
            text-align: center;
            border: 1px solid var(--border-color);
            border-radius: 0;
            padding: 0.5rem;
        }
        
        .quantity-control input::-webkit-outer-spin-button,
        .quantity-control input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        
        .quantity-btn {
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--light-color);
            border: 1px solid var(--border-color);
            cursor: pointer;
            font-weight: bold;
        }
        
        .quantity-btn.minus {
            border-radius: var(--border-radius) 0 0 var(--border-radius);
        }
        
        .quantity-btn.plus {
            border-radius: 0 var(--border-radius) var(--border-radius) 0;
        }
        
        .btn-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 0.5rem;
        }
        
        .remove-btn {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }
        
        .save-btn {
            background-color: rgba(59, 130, 246, 0.1);
            color: var(--primary-color);
        }
        
        .btn-icon:hover {
            transform: scale(1.1);
        }
        
        .cart-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 2rem;
        }
        
        .summary-container {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .summary-container h3 {
            margin-top: 0;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border-color);
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
            margin: 1rem 0;
        }
        
        .promo-code {
            display: flex;
            gap: 0.5rem;
            margin: 1.5rem 0;
        }
        
        .promo-code input {
            flex: 1;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
        }
        
        .checkout-btn {
            width: 100%;
            padding: 1rem;
            font-size: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .payment-methods {
            text-align: center;
        }
        
        .payment-methods p {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }
        
        .payment-icons {
            display: flex;
            justify-content: center;
            gap: 1rem;
            font-size: 1.5rem;
            color: var(--text-secondary);
        }
        
        .empty-cart {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .empty-cart-icon {
            font-size: 5rem;
            color: var(--text-secondary);
            opacity: 0.3;
            margin-bottom: 1.5rem;
        }
        
        .empty-cart h2 {
            margin-bottom: 1rem;
        }
        
        .empty-cart p {
            color: var(--text-secondary);
            margin-bottom: 2rem;
        }
        
        @media (max-width: 992px) {
            .cart-container {
                grid-template-columns: 1fr;
            }
            
            .cart-table {
                font-size: 0.9rem;
            }
        }
        
        @media (max-width: 768px) {
            .cart-table thead {
                display: none;
            }
            
            .cart-table, .cart-table tbody, .cart-table tr, .cart-table td {
                display: block;
                width: 100%;
            }
            
            .cart-table tr {
                margin-bottom: 1.5rem;
                border: 1px solid var(--border-color);
                border-radius: var(--border-radius);
                padding: 1rem;
            }
            
            .cart-table td {
                padding: 0.5rem 0;
                border-bottom: none;
                text-align: right;
                position: relative;
                padding-left: 50%;
            }
            
            .cart-table td::before {
                content: attr(data-label);
                position: absolute;
                left: 0;
                width: 45%;
                font-weight: 600;
                text-align: left;
            }
            
            .product-info {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .actions-cell {
                display: flex;
                justify-content: flex-end;
                gap: 1rem;
            }
            
            .btn-icon {
                margin-bottom: 0;
            }
        }
    </style>
</body>
</html>