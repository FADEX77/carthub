<?php
require_once '../config/database.php';
requireLogin('buyer');

$buyer_id = $_SESSION['user_id'];

// Get buyer statistics
$stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM orders WHERE buyer_id = ?) as total_orders,
        (SELECT SUM(total_amount) FROM orders WHERE buyer_id = ? AND order_status != 'cancelled') as total_spent,
        (SELECT COUNT(*) FROM cart WHERE buyer_id = ?) as cart_items,
        (SELECT COUNT(*) FROM wishlist WHERE buyer_id = ?) as wishlist_items,
        (SELECT COUNT(*) FROM orders WHERE buyer_id = ? AND order_status = 'pending') as pending_orders
";
$stats_stmt = $pdo->prepare($stats_query);
$stats_stmt->execute([$buyer_id, $buyer_id, $buyer_id, $buyer_id, $buyer_id]);
$stats = $stats_stmt->fetch();

// Get recent orders
$orders_stmt = $pdo->prepare("
    SELECT o.*, COUNT(oi.id) as item_count
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.buyer_id = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT 5
");
$orders_stmt->execute([$buyer_id]);
$orders = $orders_stmt->fetchAll();

// Get cart items
$cart_stmt = $pdo->prepare("
    SELECT c.*, p.name, p.price, p.image_path, u.full_name as vendor_name
    FROM cart c
    JOIN products p ON c.product_id = p.id
    JOIN users u ON p.vendor_id = u.id
    WHERE c.buyer_id = ?
    ORDER BY c.added_at DESC
    LIMIT 3
");
$cart_stmt->execute([$buyer_id]);
$cart_items = $cart_stmt->fetchAll();

// Get recommended products (random for now)
$recommended_stmt = $pdo->query("
    SELECT p.*, u.full_name as vendor_name
    FROM products p
    JOIN users u ON p.vendor_id = u.id
    WHERE p.status = 'active'
    ORDER BY RAND()
    LIMIT 6
");
$recommended = $recommended_stmt->fetchAll();

// Get recent wishlist items
$wishlist_stmt = $pdo->prepare("
    SELECT w.*, p.name, p.price, p.image_path
    FROM wishlist w
    JOIN products p ON w.product_id = p.id
    WHERE w.buyer_id = ?
    ORDER BY w.added_at DESC
    LIMIT 3
");
$wishlist_stmt->execute([$buyer_id]);
$wishlist_items = $wishlist_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buyer Dashboard - CartHub</title>
    <link rel="stylesheet" href="../css/modern_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="icon" href="../images/carthub-logo.png" type="image/png">
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
                        <li><a href="dashboard.php" class="active">Dashboard</a></li>
                        <li><a href="browse.php">Browse Products</a></li>
                        <li><a href="cart.php">Cart (<?php echo $stats['cart_items']; ?>)</a></li>
                        <li><a href="orders.php">My Orders</a></li>
                        <li><a href="wishlist.php">Wishlist (<?php echo $stats['wishlist_items']; ?>)</a></li>
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
                <h1 class="dashboard-title">🛍️ Welcome Back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h1>
                <p class="dashboard-subtitle">Ready to discover amazing products and manage your orders?</p>
            </div>

            <!-- Statistics Cards -->
            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo number_format($stats['total_orders']); ?></div>
                        <div class="stat-label">Total Orders</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number">$<?php echo number_format($stats['total_spent'] ?? 0, 2); ?></div>
                        <div class="stat-label">Total Spent</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo number_format($stats['cart_items']); ?></div>
                        <div class="stat-label">Items in Cart</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-heart"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo number_format($stats['wishlist_items']); ?></div>
                        <div class="stat-label">Wishlist Items</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo number_format($stats['pending_orders']); ?></div>
                        <div class="stat-label">Pending Orders</div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <h3>Quick Actions</h3>
                <div class="action-grid">
                    <a href="browse.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-search"></i>
                        </div>
                        <div class="action-content">
                            <h4>Browse Products</h4>
                            <p>Discover new products from our vendors</p>
                        </div>
                    </a>
                    
                    <a href="cart.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="action-content">
                            <h4>View Cart</h4>
                            <p>Review and checkout your items</p>
                        </div>
                    </a>
                    
                    <a href="orders.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-list-alt"></i>
                        </div>
                        <div class="action-content">
                            <h4>My Orders</h4>
                            <p>Track your order history and status</p>
                        </div>
                    </a>
                    
                    <a href="wishlist.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-heart"></i>
                        </div>
                        <div class="action-content">
                            <h4>Wishlist</h4>
                            <p>Save products for later purchase</p>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Dashboard Content Grid -->
            <div class="dashboard-grid">
                <!-- Recent Orders -->
                <div class="dashboard-section">
                    <div class="section-header">
                        <h3>Recent Orders</h3>
                        <a href="orders.php" class="view-all-link">View All</a>
                    </div>
                    
                    <?php if (!empty($orders)): ?>
                        <div class="orders-list">
                            <?php foreach ($orders as $order): ?>
                                <div class="order-item">
                                    <div class="order-info">
                                        <div class="order-id">#<?php echo $order['id']; ?></div>
                                        <div class="order-date"><?php echo date('M j, Y', strtotime($order['created_at'])); ?></div>
                                    </div>
                                    <div class="order-details">
                                        <div class="order-amount">$<?php echo number_format($order['total_amount'], 2); ?></div>
                                        <div class="order-status <?php echo $order['order_status']; ?>">
                                            <?php echo ucfirst($order['order_status']); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-shopping-bag"></i>
                            <p>No orders yet</p>
                            <a href="browse.php" class="btn btn-primary">Start Shopping</a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Shopping Cart Preview -->
                <div class="dashboard-section">
                    <div class="section-header">
                        <h3>Shopping Cart</h3>
                        <a href="cart.php" class="view-all-link">View Cart</a>
                    </div>
                    
                    <?php if (!empty($cart_items)): ?>
                        <div class="cart-preview">
                            <?php foreach ($cart_items as $item): ?>
                                <div class="cart-item-preview">
                                    <img src="<?php echo $item['image_path'] ? '../uploads/products/' . $item['image_path'] : '/placeholder.svg?height=50&width=50'; ?>" 
                                         alt="<?php echo htmlspecialchars($item['name']); ?>">
                                    <div class="item-info">
                                        <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                        <div class="item-price">$<?php echo number_format($item['price'], 2); ?> × <?php echo $item['quantity']; ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-shopping-cart"></i>
                            <p>Your cart is empty</p>
                            <a href="browse.php" class="btn btn-primary">Start Shopping</a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Wishlist Preview -->
                <div class="dashboard-section">
                    <div class="section-header">
                        <h3>Wishlist</h3>
                        <a href="wishlist.php" class="view-all-link">View All</a>
                    </div>
                    
                    <?php if (!empty($wishlist_items)): ?>
                        <div class="wishlist-preview">
                            <?php foreach ($wishlist_items as $item): ?>
                                <div class="wishlist-item-preview">
                                    <img src="<?php echo $item['image_path'] ? '../uploads/products/' . $item['image_path'] : '/placeholder.svg?height=50&width=50'; ?>" 
                                         alt="<?php echo htmlspecialchars($item['name']); ?>">
                                    <div class="item-info">
                                        <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                        <div class="item-price">$<?php echo number_format($item['price'], 2); ?></div>
                                    </div>
                                    <button class="btn btn-sm btn-primary" onclick="addToCart(<?php echo $item['product_id']; ?>)">
                                        Add to Cart
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-heart"></i>
                            <p>No saved items</p>
                            <a href="browse.php" class="btn btn-primary">Browse Products</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recommended Products -->
            <div class="recommended-section">
                <div class="section-header">
                    <h2>Recommended for You</h2>
                    <p>Discover products you might love</p>
                </div>
                
                <div class="product-grid">
                    <?php foreach (array_slice($recommended, 0, 6) as $product): ?>
                        <div class="product-card">
                            <a href="product_detail.php?id=<?php echo $product['id']; ?>" style="text-decoration: none; color: inherit;">
                                <div class="product-image">
                                    <img src="<?php echo $product['image_path'] ? '../uploads/products/' . $product['image_path'] : '/placeholder.svg?height=200&width=300'; ?>" 
                                         alt="<?php echo htmlspecialchars($product['name']); ?>">
                                </div>
                                <div class="product-info">
                                    <div class="product-category"><?php echo htmlspecialchars($product['category']); ?></div>
                                    <h4 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h4>
                                    <div class="product-vendor">by <?php echo htmlspecialchars($product['vendor_name']); ?></div>
                                    <div class="product-price">$<?php echo number_format($product['price'], 2); ?></div>
                                    <div class="product-stock"><?php echo $product['stock_quantity']; ?> in stock</div>
                                </div>
                            </a>
                            <div class="product-actions">
                                <button onclick="event.preventDefault(); addToCart(<?php echo $product['id']; ?>)" 
                                        class="btn btn-primary btn-sm">
                                    Add to Cart
                                </button>
                                <button onclick="event.preventDefault(); toggleWishlist(<?php echo $product['id']; ?>)" 
                                        class="btn btn-secondary btn-sm">
                                    <i class="fas fa-heart"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="section-footer">
                    <a href="browse.php" class="btn btn-secondary">View All Products</a>
                </div>
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

    <script>
        function addToCart(productId) {
            fetch('../api/cart_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=add&product_id=${productId}&quantity=1`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Product added to cart!');
                    // Update cart count in header
                    updateCartCount(1);
                } else {
                    alert(data.message || 'Failed to add product to cart');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }

        function toggleWishlist(productId) {
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
                    alert('Product added to wishlist!');
                    // Update wishlist count in header
                    updateWishlistCount(1);
                } else {
                    alert(data.message || 'Product already in wishlist');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }

        function updateCartCount(change) {
            const cartElement = document.querySelector('.nav-links a[href="cart.php"]');
            if (cartElement) {
                const currentCount = parseInt(cartElement.textContent.match(/\d+/)[0] || 0);
                const newCount = Math.max(0, currentCount + change);
                cartElement.textContent = `Cart (${newCount})`;
            }
        }

        function updateWishlistCount(change) {
            const wishlistElement = document.querySelector('.nav-links a[href="wishlist.php"]');
            if (wishlistElement) {
                const currentCount = parseInt(wishlistElement.textContent.match(/\d+/)[0] || 0);
                const newCount = Math.max(0, currentCount + change);
                wishlistElement.textContent = `Wishlist (${newCount})`;
            }
        }
    </script>

    <style>
        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }
        
        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }
        
        .stat-content {
            flex: 1;
        }
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .quick-actions {
            margin-bottom: 3rem;
        }
        
        .quick-actions h3 {
            margin-bottom: 1.5rem;
            color: var(--text-primary);
        }
        
        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }
        
        .action-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            text-decoration: none;
            color: inherit;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }
        
        .action-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .action-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }
        
        .action-content h4 {
            margin: 0 0 0.5rem;
            color: var(--text-primary);
        }
        
        .action-content p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }
        
        .dashboard-section {
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .section-header h3 {
            margin: 0;
            color: var(--text-primary);
        }
        
        .view-all-link {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .view-all-link:hover {
            text-decoration: underline;
        }
        
        .orders-list {
            padding: 1rem;
        }
        
        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .order-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .order-id {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .order-date {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        .order-details {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.25rem;
        }
        
        .order-amount {
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .order-status {
            font-size: 0.875rem;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-weight: 500;
        }
        
        .order-status.pending {
            background: rgba(245, 158, 11, 0.1);
            color: #d97706;
        }
        
        .order-status.confirmed {
            background: rgba(59, 130, 246, 0.1);
            color: #2563eb;
        }
        
        .order-status.shipped {
            background: rgba(139, 92, 246, 0.1);
            color: #7c3aed;
        }
        
        .order-status.delivered {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
        }
        
        .cart-preview,
        .wishlist-preview {
            padding: 1rem;
        }
        
        .cart-item-preview,
        .wishlist-item-preview {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .cart-item-preview:last-child,
        .wishlist-item-preview:last-child {
            border-bottom: none;
        }
        
        .cart-item-preview img,
        .wishlist-item-preview img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: var(--border-radius);
        }
        
        .item-info {
            flex: 1;
        }
        
        .item-name {
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }
        
        .item-price {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        
        .empty-state {
            padding: 3rem 1.5rem;
            text-align: center;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--text-secondary);
            opacity: 0.3;
            margin-bottom: 1rem;
        }
        
        .empty-state p {
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
        }
        
        .recommended-section {
            margin-bottom: 3rem;
        }
        
        .recommended-section .section-header {
            text-align: center;
            margin-bottom: 2rem;
            padding: 0;
            border: none;
        }
        
        .recommended-section .section-header h2 {
            margin: 0 0 0.5rem;
            color: var(--text-primary);
        }
        
        .recommended-section .section-header p {
            margin: 0;
            color: var(--text-secondary);
        }
        
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .product-card {
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .product-image {
            height: 200px;
            overflow: hidden;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s;
        }
        
        .product-card:hover .product-image img {
            transform: scale(1.05);
        }
        
        .product-info {
            padding: 1.5rem;
        }
        
        .product-category {
            display: inline-block;
            background: var(--light-color);
            padding: 0.25rem 0.75rem;
            border-radius: var(--border-radius);
            font-size: 0.75rem;
            margin-bottom: 0.75rem;
            color: var(--text-secondary);
        }
        
        .product-name {
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            line-height: 1.4;
            color: var(--text-primary);
        }
        
        .product-vendor {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 0.75rem;
        }
        
        .product-price {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .product-stock {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }
        
        .product-actions {
            display: flex;
            gap: 0.5rem;
            padding: 0 1.5rem 1.5rem;
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }
        
        .section-footer {
            text-align: center;
            margin-top: 2rem;
        }
        
        @media (max-width: 768px) {
            .dashboard-stats {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
            
            .action-grid {
                grid-template-columns: 1fr;
            }
            
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .product-grid {
                grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            }
            
            .order-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .order-details {
                align-items: flex-start;
            }
        }
    </style>
</body>
</html>