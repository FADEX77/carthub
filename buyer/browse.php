<?php
require_once '../config/database.php';
requireLogin('buyer');

$buyer_id = $_SESSION['user_id'];

// Get cart count for header
$cart_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM cart WHERE buyer_id = ?");
$cart_count_stmt->execute([$buyer_id]);
$cart_count = $cart_count_stmt->fetchColumn();

// Handle search and filtering
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$min_price = $_GET['min_price'] ?? '';
$max_price = $_GET['max_price'] ?? '';

// Build query
$query = "
    SELECT p.*, u.full_name as vendor_name, 
    (SELECT COUNT(*) FROM reviews r WHERE r.product_id = p.id) as review_count,
    (SELECT AVG(rating) FROM reviews r WHERE r.product_id = p.id) as avg_rating
    FROM products p
    JOIN users u ON p.vendor_id = u.id
    WHERE p.status = 'active'
";

$params = [];

if (!empty($search)) {
    $query .= " AND (p.name LIKE ? OR p.description LIKE ? OR p.tags LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($category)) {
    $query .= " AND p.category = ?";
    $params[] = $category;
}

if (!empty($min_price)) {
    $query .= " AND p.price >= ?";
    $params[] = $min_price;
}

if (!empty($max_price)) {
    $query .= " AND p.price <= ?";
    $params[] = $max_price;
}

// Add sorting
switch ($sort) {
    case 'price_low':
        $query .= " ORDER BY p.price ASC";
        break;
    case 'price_high':
        $query .= " ORDER BY p.price DESC";
        break;
    case 'popular':
        $query .= " ORDER BY review_count DESC";
        break;
    case 'rating':
        $query .= " ORDER BY avg_rating DESC";
        break;
    case 'newest':
    default:
        $query .= " ORDER BY p.created_at DESC";
        break;
}

// Get categories for filter
$categories_stmt = $pdo->query("SELECT DISTINCT category FROM products WHERE status = 'active' ORDER BY category");
$categories = $categories_stmt->fetchAll(PDO::FETCH_COLUMN);

// Execute query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get wishlist items for the current user
$wishlist_stmt = $pdo->prepare("SELECT product_id FROM wishlist WHERE buyer_id = ?");
$wishlist_stmt->execute([$buyer_id]);
$wishlist_items = $wishlist_stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Products - CartHub</title>
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
                        <li><a href="browse.php" class="active">Browse Products</a></li>
                        <li><a href="cart.php">Cart (<?php echo $cart_count; ?>)</a></li>
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
                <h1 class="dashboard-title">Browse Products</h1>
                <p class="dashboard-subtitle">Discover amazing products from our vendors</p>
            </div>

            <!-- Search and Filter -->
            <div class="filter-container">
                <form method="GET" action="browse.php" class="filter-form">
                    <div class="search-box">
                        <input type="text" name="search" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-primary">Search</button>
                    </div>
                    
                    <div class="filter-options">
                        <div class="filter-group">
                            <label for="category">Category</label>
                            <select name="category" id="category" onchange="this.form.submit()">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat; ?>" <?php echo $category === $cat ? 'selected' : ''; ?>>
                                        <?php echo $cat; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="sort">Sort By</label>
                            <select name="sort" id="sort" onchange="this.form.submit()">
                                <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest</option>
                                <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                                <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                                <option value="popular" <?php echo $sort === 'popular' ? 'selected' : ''; ?>>Most Popular</option>
                                <option value="rating" <?php echo $sort === 'rating' ? 'selected' : ''; ?>>Highest Rated</option>
                            </select>
                        </div>
                        
                        <div class="filter-group price-range">
                            <label>Price Range</label>
                            <div class="price-inputs">
                                <input type="number" name="min_price" placeholder="Min" value="<?php echo htmlspecialchars($min_price); ?>">
                                <span>to</span>
                                <input type="number" name="max_price" placeholder="Max" value="<?php echo htmlspecialchars($max_price); ?>">
                                <button type="submit" class="btn btn-secondary">Apply</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Products Grid -->
            <div class="product-grid">
                <?php if (count($products) > 0): ?>
                    <?php foreach ($products as $product): ?>
                        <div class="product-card">
                            <div class="product-image">
                                <a href="product_detail.php?id=<?php echo $product['id']; ?>">
                                    <img src="<?php echo $product['image_path'] ? '../uploads/products/' . $product['image_path'] : '/placeholder.svg?height=200&width=300'; ?>" 
                                         alt="<?php echo htmlspecialchars($product['name']); ?>">
                                </a>
                                <button class="wishlist-btn <?php echo in_array($product['id'], $wishlist_items) ? 'active' : ''; ?>" 
                                        onclick="toggleWishlist(<?php echo $product['id']; ?>, this)">
                                    <i class="fas <?php echo in_array($product['id'], $wishlist_items) ? 'fa-heart' : 'fa-heart-o'; ?>"></i>
                                </button>
                            </div>
                            <div class="product-info">
                                <div class="product-category"><?php echo htmlspecialchars($product['category']); ?></div>
                                <h3 class="product-name">
                                    <a href="product_detail.php?id=<?php echo $product['id']; ?>">
                                        <?php echo htmlspecialchars($product['name']); ?>
                                    </a>
                                </h3>
                                <div class="product-meta">
                                    <div class="product-vendor">by <?php echo htmlspecialchars($product['vendor_name']); ?></div>
                                    <div class="product-rating">
                                        <?php 
                                        $rating = round($product['avg_rating'] ?? 0);
                                        for ($i = 1; $i <= 5; $i++) {
                                            echo '<i class="' . ($i <= $rating ? 'fas' : 'far') . ' fa-star"></i>';
                                        }
                                        ?>
                                        <span>(<?php echo $product['review_count'] ?? 0; ?>)</span>
                                    </div>
                                </div>
                                <div class="product-price-stock">
                                    <div class="product-price">$<?php echo number_format($product['price'], 2); ?></div>
                                    <div class="product-stock"><?php echo $product['stock_quantity']; ?> in stock</div>
                                </div>
                                <div class="product-actions">
                                    <button class="btn btn-primary add-to-cart-btn" onclick="addToCart(<?php echo $product['id']; ?>)">
                                        Add to Cart
                                    </button>
                                    <a href="product_detail.php?id=<?php echo $product['id']; ?>" class="btn btn-secondary">
                                        View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-products">
                        <h3>No products found</h3>
                        <p>Try adjusting your search or filter criteria</p>
                        <a href="browse.php" class="btn btn-primary">Clear Filters</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <div class="footer-bottom">
                <p>&copy; 2025 CartHub. All rights reserved. | Designed by FADEX</p>
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

        function toggleWishlist(productId, button) {
            const action = button.classList.contains('active') ? 'remove' : 'add';
            
            fetch('../api/wishlist_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=${action}&product_id=${productId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (action === 'add') {
                        button.classList.add('active');
                        button.querySelector('i').classList.replace('fa-heart-o', 'fa-heart');
                    } else {
                        button.classList.remove('active');
                        button.querySelector('i').classList.replace('fa-heart', 'fa-heart-o');
                    }
                } else {
                    alert(data.message || 'Failed to update wishlist');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }
    </script>

    <style>
        .filter-container {
            background: #f9fafb;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        
        .filter-form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .search-box {
            display: flex;
            gap: 0.5rem;
        }
        
        .search-box input {
            flex: 1;
            padding: 0.75rem;
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
        }
        
        .filter-options {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            min-width: 200px;
        }
        
        .filter-group label {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-secondary);
        }
        
        .filter-group select {
            padding: 0.75rem;
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
            background-color: white;
        }
        
        .price-inputs {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .price-inputs input {
            width: 100px;
            padding: 0.75rem;
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
        }
        
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 2rem;
        }
        
        .product-card {
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s, box-shadow 0.3s;
            background: white;
        }
        
        .product-card:hover {
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
        
        .product-card:hover .product-image img {
            transform: scale(1.05);
        }
        
        .wishlist-btn {
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
        }
        
        .wishlist-btn i {
            color: #888;
            font-size: 1.2rem;
            transition: all 0.3s;
        }
        
        .wishlist-btn.active i {
            color: var(--danger-color);
        }
        
        .wishlist-btn:hover {
            transform: scale(1.1);
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
            margin-bottom: 0.75rem;
            line-height: 1.4;
        }
        
        .product-name a {
            color: var(--text-primary);
            text-decoration: none;
        }
        
        .product-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            font-size: 0.875rem;
        }
        
        .product-vendor {
            color: var(--text-secondary);
        }
        
        .product-rating {
            color: #f59e0b;
        }
        
        .product-rating span {
            color: var(--text-secondary);
            margin-left: 0.25rem;
        }
        
        .product-price-stock {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .product-price {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .product-stock {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        .product-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .product-actions .btn {
            flex: 1;
            font-size: 0.875rem;
            padding: 0.75rem;
        }
        
        .no-products {
            grid-column: 1 / -1;
            text-align: center;
            padding: 3rem;
            background: var(--light-color);
            border-radius: var(--border-radius);
        }
        
        @media (max-width: 768px) {
            .filter-options {
                flex-direction: column;
            }
            
            .filter-group {
                width: 100%;
            }
            
            .product-grid {
                grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            }
        }
    </style>
</body>
</html>