<?php
require_once '../config/database.php';
requireLogin('buyer');

$buyer_id = $_SESSION['user_id'];

// Get cart count for header
$cart_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM cart WHERE buyer_id = ?");
$cart_count_stmt->execute([$buyer_id]);
$cart_count = $cart_count_stmt->fetchColumn();

// Get wishlist items
$wishlist_stmt = $pdo->prepare("
    SELECT w.*, p.name, p.price, p.image_path, p.stock_quantity, p.status, u.full_name as vendor_name,
    (SELECT COUNT(*) FROM reviews r WHERE r.product_id = p.id) as review_count,
    (SELECT AVG(rating) FROM reviews r WHERE r.product_id = p.id) as avg_rating
    FROM wishlist w
    JOIN products p ON w.product_id = p.id
    JOIN users u ON p.vendor_id = u.id
    WHERE w.buyer_id = ?
    ORDER BY w.added_at DESC
");
$wishlist_stmt->execute([$buyer_id]);
$wishlist_items = $wishlist_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Wishlist - CartHub</title>
    <link rel="stylesheet" href="../css/modern_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="icon" href="../images/carthub-logo.png" type="image/png">
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
                        <li><a href="cart.php">Cart (<?php echo $cart_count; ?>)</a></li>
                        <li><a href="orders.php">My Orders</a></li>
                        <li><a href="wishlist.php" class="active">Wishlist</a></li>
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
                <h1 class="dashboard-title">My Wishlist</h1>
                <p class="dashboard-subtitle">Save your favorite products for later</p>
            </div>

            <?php if (!empty($wishlist_items)): ?>
                <div class="wishlist-actions">
                    <div class="wishlist-count">
                        <span><?php echo count($wishlist_items); ?> items in your wishlist</span>
                    </div>
                    <div class="wishlist-controls">
                        <button class="btn btn-secondary" onclick="addAllToCart()">Add All to Cart</button>
                        <button class="btn btn-danger" onclick="clearWishlist()">Clear Wishlist</button>
                    </div>
                </div>

                <div class="wishlist-grid">
                    <?php foreach ($wishlist_items as $item): ?>
                        <div class="wishlist-item" data-product-id="<?php echo $item['product_id']; ?>">
                            <div class="product-image">
                                <a href="product_detail.php?id=<?php echo $item['product_id']; ?>">
                                    <img src="<?php echo $item['image_path'] ? '../uploads/products/' . $item['image_path'] : '/placeholder.svg?height=200&width=300'; ?>" 
                                         alt="<?php echo htmlspecialchars($item['name']); ?>">
                                </a>
                                <button class="remove-btn" onclick="removeFromWishlist(<?php echo $item['product_id']; ?>)">
                                    <i class="fas fa-times"></i>
                                </button>
                                <?php if ($item['status'] !== 'active'): ?>
                                    <div class="unavailable-overlay">
                                        <span>Unavailable</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="product-info">
                                <div class="product-category"><?php echo htmlspecialchars($item['category'] ?? 'General'); ?></div>
                                <h3 class="product-name">
                                    <a href="product_detail.php?id=<?php echo $item['product_id']; ?>">
                                        <?php echo htmlspecialchars($item['name']); ?>
                                    </a>
                                </h3>
                                <div class="product-vendor">by <?php echo htmlspecialchars($item['vendor_name']); ?></div>
                                
                                <div class="product-rating">
                                    <?php 
                                    $rating = round($item['avg_rating'] ?? 0);
                                    for ($i = 1; $i <= 5; $i++) {
                                        echo '<i class="' . ($i <= $rating ? 'fas' : 'far') . ' fa-star"></i>';
                                    }
                                    ?>
                                    <span>(<?php echo $item['review_count'] ?? 0; ?>)</span>
                                </div>
                                
                                <div class="product-price">$<?php echo number_format($item['price'], 2); ?></div>
                                
                                <div class="product-stock">
                                    <?php if ($item['stock_quantity'] > 0): ?>
                                        <span class="in-stock"><?php echo $item['stock_quantity']; ?> in stock</span>
                                    <?php else: ?>
                                        <span class="out-of-stock">Out of stock</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="wishlist-date">
                                    Added <?php echo date('M j, Y', strtotime($item['added_at'])); ?>
                                </div>
                            </div>
                            
                            <div class="product-actions">
                                <?php if ($item['status'] === 'active' && $item['stock_quantity'] > 0): ?>
                                    <button class="btn btn-primary" onclick="addToCart(<?php echo $item['product_id']; ?>)">
                                        Add to Cart
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-secondary" disabled>
                                        Unavailable
                                    </button>
                                <?php endif; ?>
                                <a href="product_detail.php?id=<?php echo $item['product_id']; ?>" class="btn btn-outline">
                                    View Details
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-wishlist">
                    <div class="empty-wishlist-icon">
                        <i class="fas fa-heart"></i>
                    </div>
                    <h2>Your wishlist is empty</h2>
                    <p>Save products you love to your wishlist and never lose track of them.</p>
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
                    const cartCountElement = document.querySelector('.nav-links a[href="cart.php"]');
                    if (cartCountElement) {
                        const currentCount = parseInt(cartCountElement.textContent.match(/\d+/)[0] || 0);
                        cartCountElement.textContent = `Cart (${currentCount + 1})`;
                    }
                } else {
                    alert(data.message || 'Failed to add product to cart');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }
        
        function removeFromWishlist(productId) {
            if (confirm('Remove this item from your wishlist?')) {
                fetch('../api/wishlist_actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=remove&product_id=${productId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove item from DOM
                        const item = document.querySelector(`.wishlist-item[data-product-id="${productId}"]`);
                        if (item) {
                            item.remove();
                        }
                        
                        // Update count
                        const countElement = document.querySelector('.wishlist-count span');
                        if (countElement) {
                            const currentCount = parseInt(countElement.textContent.match(/\d+/)[0] || 0);
                            const newCount = currentCount - 1;
                            countElement.textContent = `${newCount} items in your wishlist`;
                            
                            // Show empty state if no items left
                            if (newCount === 0) {
                                window.location.reload();
                            }
                        }
                    } else {
                        alert(data.message || 'Failed to remove item from wishlist');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                });
            }
        }
        
        function addAllToCart() {
            const availableItems = document.querySelectorAll('.wishlist-item .btn-primary:not([disabled])');
            if (availableItems.length === 0) {
                alert('No available items to add to cart.');
                return;
            }
            
            if (confirm(`Add ${availableItems.length} available items to your cart?`)) {
                let addedCount = 0;
                let totalItems = availableItems.length;
                
                availableItems.forEach(button => {
                    const productId = button.closest('.wishlist-item').dataset.productId;
                    
                    fetch('../api/cart_actions.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=add&product_id=${productId}&quantity=1`
                    })
                    .then(response => response.json())
                    .then(data => {
                        addedCount++;
                        if (addedCount === totalItems) {
                            alert(`${totalItems} items added to cart!`);
                            // Update cart count
                            const cartCountElement = document.querySelector('.nav-links a[href="cart.php"]');
                            if (cartCountElement) {
                                const currentCount = parseInt(cartCountElement.textContent.match(/\d+/)[0] || 0);
                                cartCountElement.textContent = `Cart (${currentCount + totalItems})`;
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });
                });
            }
        }
        
        function clearWishlist() {
            if (confirm('Are you sure you want to clear your entire wishlist?')) {
                fetch('../api/wishlist_actions.php', {
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
                        alert(data.message || 'Failed to clear wishlist');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                });
            }
        }
    </script>

    <style>
        .wishlist-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding: 1rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .wishlist-count span {
            font-weight: 500;
            color: var(--text-secondary);
        }
        
        .wishlist-controls {
            display: flex;
            gap: 1rem;
        }
        
        .wishlist-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 2rem;
        }
        
        .wishlist-item {
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .wishlist-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .product-image {
            position: relative;
            height: 200px;
            overflow: hidden;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s;
        }
        
        .wishlist-item:hover .product-image img {
            transform: scale(1.05);
        }
        
        .remove-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: white;
            border: none;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: all 0.3s;
            color: var(--danger-color);
        }
        
        .remove-btn:hover {
            transform: scale(1.1);
            background: var(--danger-color);
            color: white;
        }
        
        .unavailable-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
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
        }
        
        .product-name a {
            color: var(--text-primary);
            text-decoration: none;
        }
        
        .product-vendor {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 0.75rem;
        }
        
        .product-rating {
            color: #f59e0b;
            margin-bottom: 0.75rem;
            font-size: 0.875rem;
        }
        
        .product-rating span {
            color: var(--text-secondary);
            margin-left: 0.25rem;
        }
        
        .product-price {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .product-stock {
            margin-bottom: 0.75rem;
            font-size: 0.875rem;
        }
        
        .in-stock {
            color: var(--success-color);
        }
        
        .out-of-stock {
            color: var(--danger-color);
        }
        
        .wishlist-date {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }
        
        .product-actions {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .product-actions .btn {
            font-size: 0.875rem;
            padding: 0.75rem;
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        
        .btn-outline:hover {
            background: var(--light-color);
        }
        
        .empty-wishlist {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .empty-wishlist-icon {
            font-size: 5rem;
            color: var(--danger-color);
            opacity: 0.3;
            margin-bottom: 1.5rem;
        }
        
        .empty-wishlist h2 {
            margin-bottom: 1rem;
        }
        
        .empty-wishlist p {
            color: var(--text-secondary);
            margin-bottom: 2rem;
        }
        
        @media (max-width: 768px) {
            .wishlist-actions {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .wishlist-controls {
                width: 100%;
                justify-content: center;
            }
            
            .wishlist-grid {
                grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            }
        }
    </style>
</body>
</html>