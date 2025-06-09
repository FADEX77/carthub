<?php
require_once 'config/database.php';

// If user is already logged in, redirect to appropriate dashboard
if (isLoggedIn()) {
    $userType = getUserType();
    switch ($userType) {
        case 'admin':
            header('Location: admin/dashboard.php');
            break;
        case 'vendor':
            header('Location: vendor/dashboard.php');
            break;
        case 'buyer':
            header('Location: buyer/dashboard.php');
            break;
    }
    exit();
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Search and filter
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$category = isset($_GET['category']) ? sanitize_input($_GET['category']) : '';

// Build query
$where_conditions = ["p.status = 'active'"];
$params = [];

if ($search) {
    $where_conditions[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category) {
    $where_conditions[] = "p.category = ?";
    $params[] = $category;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count
$count_sql = "SELECT COUNT(*) FROM products p JOIN users u ON p.vendor_id = u.id WHERE $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_products = $count_stmt->fetchColumn();
$total_pages = ceil($total_products / $per_page);

// Get products
$sql = "
    SELECT p.*, u.full_name as vendor_name 
    FROM products p 
    JOIN users u ON p.vendor_id = u.id 
    WHERE $where_clause
    ORDER BY p.created_at DESC 
    LIMIT $per_page OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get categories for filter
$categories_stmt = $pdo->query("SELECT DISTINCT category FROM products WHERE status = 'active' ORDER BY category");
$categories = $categories_stmt->fetchAll(PDO::FETCH_COLUMN);

// Get stats
$stats_stmt = $pdo->query("
    SELECT 
        (SELECT COUNT(*) FROM products WHERE status = 'active') as total_products,
        (SELECT COUNT(*) FROM users WHERE user_type = 'vendor' AND status = 'active') as total_vendors,
        (SELECT COUNT(*) FROM users WHERE user_type = 'buyer' AND status = 'active') as total_buyers,
        (SELECT COUNT(DISTINCT category) FROM products WHERE status = 'active') as total_categories
");
$stats = $stats_stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CartHub - Your Premium Online Marketplace</title>
    <link rel="stylesheet" href="css/modern_style.css">
    <link rel="icon" href="images/carthub-logo.png" type="image/png">
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <a href="index.php" class="logo">
                    <img src="images/carthub-logo.png" alt="CartHub Logo">
                    CartHub
                </a>
                <nav>
                    <ul class="nav-links">
                        <li><a href="#products">Products</a></li>
                        <li><a href="#features">Features</a></li>
                        <li><a href="signup.php" class="btn btn-secondary">Sign Up</a></li>
                        <li><a href="buyer/login.php" class="btn btn-primary">Login</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <!-- Hero Section -->
        <section class="hero">
            <div class="container">
                <div class="hero-content">
                    <h1>Welcome to CartHub</h1>
                    <p>Discover amazing products from trusted vendors worldwide. Your premium shopping experience starts here.</p>
                    <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                        <a href="signup.php" class="btn btn-primary" style="font-size: 1.125rem; padding: 1rem 2.5rem;">Start Shopping</a>
                        <a href="#products" class="btn btn-secondary" style="font-size: 1.125rem; padding: 1rem 2.5rem;">Browse Products</a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Stats Section -->
        <section style="padding: 4rem 0; background: white;">
            <div class="container">
                <div class="dashboard-stats">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo number_format($stats['total_products']); ?></div>
                        <div class="stat-label">Products Available</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo number_format($stats['total_vendors']); ?></div>
                        <div class="stat-label">Trusted Vendors</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo number_format($stats['total_buyers']); ?></div>
                        <div class="stat-label">Happy Customers</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo number_format($stats['total_categories']); ?></div>
                        <div class="stat-label">Categories</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section id="features" class="features">
            <div class="container">
                <div class="section-header">
                    <h2 class="section-title">Why Choose CartHub?</h2>
                    <p class="section-subtitle">Experience the future of online shopping with our cutting-edge platform</p>
                </div>
                <div class="features-grid">
                    <div class="feature-card">
                        <div class="feature-icon">🛒</div>
                        <h3 class="feature-title">For Smart Buyers</h3>
                        <p class="feature-description">Discover premium products from verified vendors. Enjoy secure transactions, fast shipping, and exceptional customer service.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">🏪</div>
                        <h3 class="feature-title">For Ambitious Vendors</h3>
                        <p class="feature-description">Reach thousands of customers globally. Easy product management, secure payments, and powerful analytics to grow your business.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">🔒</div>
                        <h3 class="feature-title">Secure & Reliable</h3>
                        <p class="feature-description">Advanced security measures protect your data and transactions. Shop and sell with complete peace of mind.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Products Section -->
        <section id="products" class="products-section">
            <div class="container">
                <div class="section-header">
                    <h2 class="section-title">Featured Products</h2>
                    <p class="section-subtitle">Discover our handpicked selection of premium products from top vendors</p>
                </div>

                <!-- Search and Filters -->
                <div class="search-filters">
                    <form method="GET" action="#products">
                        <div class="search-row">
                            <div class="form-group" style="margin-bottom: 0;">
                                <input type="text" name="search" class="search-input" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <select name="category" class="search-input">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category === $cat ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">Search</button>
                        </div>
                    </form>
                </div>

                <?php if (!empty($products)): ?>
                <div class="product-grid">
                    <?php foreach ($products as $product): ?>
                        <div class="product-card">
                            <a href="product_detail.php?id=<?php echo $product['id']; ?>" style="text-decoration: none; color: inherit;">
                                <img src="uploads/products/<?php echo htmlspecialchars($product['image_path']); ?>" 
                                    alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                    style="width: 100%; height: 200px; object-fit: cover; border-radius: var(--border-radius);">
                                <div style="padding: 1rem;">
                                    <div style="background: var(--light-color); padding: 0.25rem 0.75rem; border-radius: var(--border-radius); display: inline-block; margin-bottom: 0.5rem; font-size: 0.75rem;">
                                        <?php echo htmlspecialchars($product['category']); ?>
                                    </div>
                                    <h4 style="margin-bottom: 0.5rem; font-size: 1rem;"><?php echo htmlspecialchars($product['name']); ?></h4>
                                    <p style="color: var(--text-secondary); font-size: 0.875rem; margin-bottom: 0.5rem;">
                                        <?php echo htmlspecialchars(substr($product['description'], 0, 80)) . '...'; ?>
                                    </p>
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <div style="font-weight: 600; color: var(--primary-color); font-size: 1.1rem;">
                                            $<?php echo number_format($product['price'], 2); ?>
                                        </div>
                                        <div style="font-size: 0.75rem; color: var(--text-secondary);">
                                            <?php echo $product['stock_quantity']; ?> in stock
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>#products">Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>#products"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>#products">Next</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php else: ?>
                <div style="text-align: center; padding: 4rem 0;">
                    <h3 style="color: var(--text-secondary); margin-bottom: 1rem;">No products found</h3>
                    <p style="color: var(--text-secondary);">Try adjusting your search criteria or browse all categories.</p>
                </div>
                <?php endif; ?>

                <div style="text-align: center; margin-top: 3rem;">
                    <a href="buyer/login.php" class="btn btn-primary" style="font-size: 1.125rem; padding: 1rem 2.5rem;">Login to Start Shopping</a>
                </div>
            </div>
        </section>
    </main>

    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>CartHub</h3>
                    <p>Your premium online marketplace for quality products and trusted vendors.</p>
                </div>
                <div class="footer-section">
                    <h3>For Buyers</h3>
                    <a href="buyer/login.php">Login</a><br>
                    <a href="signup.php">Sign Up</a><br>
                    <a href="#products">Browse Products</a>
                </div>
                <div class="footer-section">
                    <h3>For Vendors</h3>
                    <a href="vendor/login.php">Vendor Login</a><br>
                    <a href="signup.php">Become a Vendor</a><br>
                    <a href="#features">Learn More</a>
                </div>
                <div class="footer-section">
                    <h3>Support</h3>
                    <a href="mailto:support@carthub.com">Contact Us</a><br>
                    <a href="#">Help Center</a><br>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2025 CartHub. All rights reserved. | Designed by FADEX</p>
            </div>
        </div>
    </footer>

    <script src="js/script.js"></script>
</body>
</html>