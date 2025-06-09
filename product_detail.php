<?php
require_once 'config/database.php';

$product_id = intval($_GET['id'] ?? 0);

if (!$product_id) {
    header('Location: index.php');
    exit();
}

// Optimized single query to get all product data with vendor info
$product_query = "
    SELECT 
        p.*,
        u.full_name as vendor_name, 
        u.email as vendor_email, 
        u.phone as vendor_phone,
        u.address as vendor_address,
        COALESCE(AVG(pr.rating), 0) as avg_rating,
        COUNT(pr.id) as review_count,
        p.view_count + 1 as new_view_count
    FROM products p
    JOIN users u ON p.vendor_id = u.id
    LEFT JOIN product_reviews pr ON p.id = pr.product_id AND pr.status = 'approved'
    WHERE p.id = ? AND p.status = 'active' AND p.visibility = 'public'
    GROUP BY p.id
";

$product_stmt = $pdo->prepare($product_query);
$product_stmt->execute([$product_id]);
$product = $product_stmt->fetch();

if (!$product) {
    header('Location: index.php');
    exit();
}

// Update view count efficiently
$update_views = $pdo->prepare("UPDATE products SET view_count = view_count + 1 WHERE id = ?");
$update_views->execute([$product_id]);

// Track product view (optimized - only if user is logged in)
if (isset($_SESSION['user_id'])) {
    $view_stmt = $pdo->prepare("
        INSERT INTO product_views (product_id, user_id, ip_address, viewed_at) 
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE viewed_at = NOW()
    ");
    $view_stmt->execute([$product_id, $_SESSION['user_id'], $_SERVER['REMOTE_ADDR']]);
}

// Get recent reviews (limit to 5 for performance)
$reviews_query = "
    SELECT pr.*, u.full_name as reviewer_name
    FROM product_reviews pr
    JOIN users u ON pr.buyer_id = u.id
    WHERE pr.product_id = ? AND pr.status = 'approved'
    ORDER BY pr.created_at DESC
    LIMIT 5
";
$reviews_stmt = $pdo->prepare($reviews_query);
$reviews_stmt->execute([$product_id]);
$reviews = $reviews_stmt->fetchAll();

// Get related products (optimized with limit and specific fields)
$related_query = "
    SELECT id, name, slug, price, image_path, short_description, avg_rating, review_count
    FROM (
        SELECT 
            p.id, p.name, p.slug, p.price, p.image_path, p.short_description, 
            COALESCE(AVG(pr.rating), 0) as avg_rating,
            COUNT(pr.id) as review_count,
            RAND() as random_order
        FROM products p
        LEFT JOIN product_reviews pr ON p.id = pr.product_id AND pr.status = 'approved'
        WHERE p.category = ? AND p.id != ? AND p.status = 'active' AND p.visibility = 'public'
        GROUP BY p.id
        ORDER BY random_order
        LIMIT 4
    ) as related_products
";
$related_stmt = $pdo->prepare($related_query);
$related_stmt->execute([$product['category'], $product_id]);
$related_products = $related_stmt->fetchAll();

// Parse additional images efficiently
$all_images = [$product['image_path']];
if (!empty($product['additional_images'])) {
    $additional_images = array_filter(explode(',', $product['additional_images']));
    $all_images = array_merge($all_images, $additional_images);
}

// Parse features efficiently
$features = !empty($product['features']) ? array_filter(explode("\n", $product['features'])) : [];

// Calculate average rating
$avg_rating = round($product['avg_rating'], 1);
$review_count = $product['review_count'];

// Generate structured data for SEO
$structured_data = [
    "@context" => "https://schema.org/",
    "@type" => "Product",
    "name" => $product['name'],
    "description" => $product['description'],
    "image" => "uploads/products/" . $product['image_path'],
    "brand" => $product['brand'] ?: $product['vendor_name'],
    "sku" => $product['sku'],
    "offers" => [
        "@type" => "Offer",
        "price" => $product['price'],
        "priceCurrency" => "USD",
        "availability" => $product['stock_quantity'] > 0 ? "https://schema.org/InStock" : "https://schema.org/OutOfStock"
    ]
];

if ($review_count > 0) {
    $structured_data["aggregateRating"] = [
        "@type" => "AggregateRating",
        "ratingValue" => $avg_rating,
        "reviewCount" => $review_count
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['meta_title'] ?: $product['name'] . ' - CartHub'); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($product['meta_description'] ?: $product['short_description'] ?: substr($product['description'], 0, 160)); ?>">
    
    <!-- Open Graph tags -->
    <meta property="og:title" content="<?php echo htmlspecialchars($product['name']); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($product['short_description'] ?: substr($product['description'], 0, 160)); ?>">
    <meta property="og:image" content="uploads/products/<?php echo htmlspecialchars($product['image_path']); ?>">
    <meta property="og:type" content="product">
    
    <!-- Structured Data -->
    <script type="application/ld+json">
    <?php echo json_encode($structured_data, JSON_UNESCAPED_SLASHES); ?>
    </script>
    
    <link rel="stylesheet" href="css/modern_style.css">
    <link rel="preload" href="uploads/products/<?php echo htmlspecialchars($product['image_path']); ?>" as="image">
    <link rel="icon" href="images/carthub-logo.png" type="image/png">
    
    <style>
        .product-gallery {
            display: grid;
            grid-template-columns: 100px 1fr;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .thumbnail-list {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            max-height: 500px;
            overflow-y: auto;
        }

        .thumbnail {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: var(--border-radius);
            cursor: pointer;
            border: 2px solid transparent;
            transition: var(--transition);
        }

        .thumbnail.active {
            border-color: var(--primary-color);
        }

        .thumbnail:hover {
            border-color: var(--primary-color);
            opacity: 0.8;
        }

        .main-image {
            width: 100%;
            max-height: 500px;
            object-fit: cover;
            border-radius: var(--border-radius);
            cursor: zoom-in;
        }

        .rating-stars {
            color: #fbbf24;
            font-size: 1.2rem;
        }

        .feature-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .feature-list li {
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-color);
            position: relative;
            padding-left: 2rem;
        }

        .feature-list li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: var(--success-color);
            font-weight: bold;
            font-size: 1.1rem;
        }

        .feature-list li:last-child {
            border-bottom: none;
        }

        .quantity-selector {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin: 1.5rem 0;
        }

        .quantity-input {
            width: 80px;
            text-align: center;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 1rem;
        }

        .quantity-btn {
            background: var(--light-color);
            border: 1px solid var(--border-color);
            width: 40px;
            height: 40px;
            border-radius: var(--border-radius);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            transition: var(--transition);
        }

        .quantity-btn:hover {
            background: var(--primary-color);
            color: white;
        }

        .product-tabs {
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 2rem;
            overflow-x: auto;
        }

        .tab-buttons {
            display: flex;
            gap: 0;
            min-width: max-content;
        }

        .tab-button {
            background: none;
            border: none;
            padding: 1rem 2rem;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: var(--transition);
            white-space: nowrap;
            font-size: 1rem;
        }

        .tab-button.active {
            border-bottom-color: var(--primary-color);
            color: var(--primary-color);
            font-weight: 600;
        }

        .tab-button:hover {
            background: var(--light-color);
        }

        .tab-content {
            display: none;
            animation: fadeIn 0.3s ease-in;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .product-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            background: var(--light-color);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .info-label {
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .info-value {
            color: var(--text-primary);
        }

        .stock-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .stock-in {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }

        .stock-low {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
        }

        .stock-out {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }

        .vendor-card {
            background: var(--light-color);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin-top: 2rem;
        }

        .vendor-avatar {
            width: 60px;
            height: 60px;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.5rem;
        }

        .review-card {
            background: var(--light-color);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
        }

        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }

        .reviewer-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .reviewer-name {
            font-weight: 600;
        }

        .review-date {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .related-products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }

        .related-product-card {
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: var(--transition);
            text-decoration: none;
            color: inherit;
        }

        .related-product-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }

        .related-product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .related-product-info {
            padding: 1rem;
        }

        .related-product-name {
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: -webkit-box;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .related-product-price {
            font-weight: 600;
            color: var(--primary-color);
            font-size: 1.1rem;
        }

        @media (max-width: 768px) {
            .product-gallery {
                grid-template-columns: 1fr;
            }

            .thumbnail-list {
                flex-direction: row;
                overflow-x: auto;
                max-height: none;
            }

            .tab-button {
                padding: 0.75rem 1rem;
                font-size: 0.875rem;
            }

            .product-info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="white-bg">
    <header>
        <div class="container">
            <div class="header-content">
                <a href="index.php" class="logo">
                    <img src="images/carthub-logo.png" alt="CartHub Logo">
                    CartHub
                </a>
                <nav>
                    <ul class="nav-links">
                        <li><a href="index.php">Home</a></li>
                        <li><a href="products.php">Products</a></li>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <?php if ($_SESSION['user_type'] === 'buyer'): ?>
                                <li><a href="buyer/dashboard.php">Dashboard</a></li>
                                <li><a href="cart.php">Cart (<span id="cart-count">0</span>)</a></li>
                            <?php endif; ?>
                            <li><a href="logout.php" class="btn btn-danger">Logout</a></li>
                        <?php else: ?>
                            <li><a href="buyer/login.php" class="btn btn-primary">Login</a></li>
                            <li><a href="signup.php" class="btn btn-secondary">Sign Up</a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main style="padding: 2rem 0;">
        <div class="container">
            <!-- Breadcrumb -->
            <nav style="margin-bottom: 2rem; color: var(--text-secondary); font-size: 0.875rem;">
                <a href="index.php" style="color: var(--primary-color);">Home</a> > 
                <a href="products.php?category=<?php echo urlencode($product['category']); ?>" style="color: var(--primary-color);"><?php echo htmlspecialchars($product['category']); ?></a> > 
                <?php if ($product['subcategory']): ?>
                    <a href="products.php?subcategory=<?php echo urlencode($product['subcategory']); ?>" style="color: var(--primary-color);"><?php echo htmlspecialchars($product['subcategory']); ?></a> > 
                <?php endif; ?>
                <span><?php echo htmlspecialchars($product['name']); ?></span>
            </nav>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 3rem; margin-bottom: 3rem;">
                <!-- Product Images -->
                <div>
                    <div class="product-gallery">
                        <div class="thumbnail-list">
                            <?php foreach ($all_images as $index => $image): ?>
                                <?php if ($image): ?>
                                <img src="uploads/products/<?php echo htmlspecialchars($image); ?>" 
                                     alt="Product Image <?php echo $index + 1; ?>" 
                                     class="thumbnail <?php echo $index === 0 ? 'active' : ''; ?>"
                                     onclick="changeMainImage(this, '<?php echo htmlspecialchars($image); ?>')"
                                     loading="<?php echo $index === 0 ? 'eager' : 'lazy'; ?>">
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <div>
                            <img id="main-image" 
                                 src="uploads/products/<?php echo htmlspecialchars($product['image_path']); ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                 class="main-image"
                                 loading="eager">
                        </div>
                    </div>

                    <?php if ($product['video_url']): ?>
                    <div style="margin-top: 2rem;">
                        <h4 style="margin-bottom: 1rem;">📹 Product Video</h4>
                        <div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; border-radius: var(--border-radius);">
                            <?php
                            $video_url = $product['video_url'];
                            if (strpos($video_url, 'youtube.com') !== false || strpos($video_url, 'youtu.be') !== false) {
                                preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\n?#]+)/', $video_url, $matches);
                                $video_id = $matches[1] ?? '';
                                if ($video_id) {
                                    echo '<iframe src="https://www.youtube.com/embed/' . $video_id . '" frameborder="0" allowfullscreen style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;"></iframe>';
                                }
                            }
                            ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Product Information -->
                <div>
                    <?php if ($product['category']): ?>
                    <div style="background: var(--light-color); padding: 0.5rem 1rem; border-radius: var(--border-radius); display: inline-block; margin-bottom: 1rem; font-size: 0.875rem;">
                        <?php echo htmlspecialchars($product['category']); ?>
                        <?php if ($product['subcategory']): ?>
                            > <?php echo htmlspecialchars($product['subcategory']); ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <h1 style="margin-bottom: 1rem; font-size: 2rem; line-height: 1.2;"><?php echo htmlspecialchars($product['name']); ?></h1>

                    <?php if ($product['short_description']): ?>
                    <p style="color: var(--text-secondary); font-size: 1.1rem; margin-bottom: 1.5rem; line-height: 1.5;">
                        <?php echo htmlspecialchars($product['short_description']); ?>
                    </p>
                    <?php endif; ?>

                    <?php if ($review_count > 0): ?>
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem;">
                        <div class="rating-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <?php if ($i <= $avg_rating): ?>
                                    ★
                                <?php elseif ($i - 0.5 <= $avg_rating): ?>
                                    ☆
                                <?php else: ?>
                                    ☆
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
                        <span><?php echo $avg_rating; ?> (<?php echo $review_count; ?> review<?php echo $review_count !== 1 ? 's' : ''; ?>)</span>
                        <a href="#reviews-tab" style="color: var(--primary-color); text-decoration: none;">Read reviews</a>
                    </div>
                    <?php endif; ?>

                    <div style="margin-bottom: 1.5rem;">
                        <div style="font-size: 2.5rem; font-weight: 700; color: var(--primary-color); margin-bottom: 0.5rem;">
                            $<?php echo number_format($product['price'], 2); ?>
                        </div>
                        <?php if ($product['compare_price'] && $product['compare_price'] > $product['price']): ?>
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <span style="text-decoration: line-through; color: var(--text-secondary); font-size: 1.2rem;">
                                $<?php echo number_format($product['compare_price'], 2); ?>
                            </span>
                            <span style="background: var(--danger-color); color: white; padding: 0.25rem 0.75rem; border-radius: var(--border-radius); font-size: 0.875rem; font-weight: 600;">
                                Save $<?php echo number_format($product['compare_price'] - $product['price'], 2); ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Product Details Grid -->
                    <div class="product-info-grid">
                        <?php if ($product['brand']): ?>
                        <div class="info-item">
                            <span class="info-label">Brand</span>
                            <span class="info-value"><?php echo htmlspecialchars($product['brand']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($product['model']): ?>
                        <div class="info-item">
                            <span class="info-label">Model</span>
                            <span class="info-value"><?php echo htmlspecialchars($product['model']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($product['sku']): ?>
                        <div class="info-item">
                            <span class="info-label">SKU</span>
                            <span class="info-value"><?php echo htmlspecialchars($product['sku']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($product['color']): ?>
                        <div class="info-item">
                            <span class="info-label">Color</span>
                            <span class="info-value"><?php echo htmlspecialchars($product['color']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($product['size']): ?>
                        <div class="info-item">
                            <span class="info-label">Size</span>
                            <span class="info-value"><?php echo htmlspecialchars($product['size']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($product['material']): ?>
                        <div class="info-item">
                            <span class="info-label">Material</span>
                            <span class="info-value"><?php echo htmlspecialchars($product['material']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($product['weight']): ?>
                        <div class="info-item">
                            <span class="info-label">Weight</span>
                            <span class="info-value"><?php echo $product['weight']; ?> lbs</span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($product['warranty']): ?>
                        <div class="info-item">
                            <span class="info-label">Warranty</span>
                            <span class="info-value"><?php echo htmlspecialchars($product['warranty']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Stock Status -->
                    <div style="margin-bottom: 1.5rem;">
                        <?php if ($product['stock_quantity'] > 10): ?>
                            <div class="stock-indicator stock-in">
                                ✅ In Stock (<?php echo $product['stock_quantity']; ?> available)
                            </div>
                        <?php elseif ($product['stock_quantity'] > 0): ?>
                            <div class="stock-indicator stock-low">
                                ⚠️ Low Stock (Only <?php echo $product['stock_quantity']; ?> left)
                            </div>
                        <?php else: ?>
                            <div class="stock-indicator stock-out">
                                ❌ Out of Stock
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Add to Cart -->
                    <?php if ($product['stock_quantity'] > 0 && isset($_SESSION['user_id']) && $_SESSION['user_type'] === 'buyer'): ?>
                    <form id="add-to-cart-form" style="margin-bottom: 2rem;">
                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                        
                        <div class="quantity-selector">
                            <label for="quantity" style="font-weight: 600;">Quantity:</label>
                            <button type="button" class="quantity-btn" onclick="decreaseQuantity()">−</button>
                            <input type="number" name="quantity" id="quantity" class="quantity-input" 
                                   value="<?php echo $product['min_order_quantity']; ?>" 
                                   min="<?php echo $product['min_order_quantity']; ?>" 
                                   max="<?php echo $product['max_order_quantity'] ?: $product['stock_quantity']; ?>">
                            <button type="button" class="quantity-btn" onclick="increaseQuantity()">+</button>
                        </div>

                        <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                            <button type="submit" class="btn btn-primary" style="flex: 2; padding: 1rem; font-size: 1.1rem;">
                                🛒 Add to Cart
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="buyNow()" style="flex: 1; padding: 1rem;">
                                ⚡ Buy Now
                            </button>
                        </div>
                    </form>
                    <?php elseif (!isset($_SESSION['user_id'])): ?>
                    <div style="background: var(--light-color); padding: 1.5rem; border-radius: var(--border-radius); text-align: center; margin-bottom: 2rem;">
                        <p style="margin-bottom: 1rem;">Please log in to purchase this product</p>
                        <a href="buyer/login.php" class="btn btn-primary">Login to Buy</a>
                    </div>
                    <?php endif; ?>

                    <!-- Vendor Information -->
                    <div class="vendor-card">
                        <h4 style="margin-bottom: 1rem;">🏪 Sold by</h4>
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div class="vendor-avatar">
                                <?php echo strtoupper(substr($product['vendor_name'], 0, 1)); ?>
                            </div>
                            <div style="flex: 1;">
                                <div style="font-weight: 600; font-size: 1.1rem;"><?php echo htmlspecialchars($product['vendor_name']); ?></div>
                                <div style="color: var(--text-secondary); font-size: 0.875rem; margin: 0.25rem 0;">Verified Vendor</div>
                                <?php if ($product['vendor_phone']): ?>
                                <div style="color: var(--text-secondary); font-size: 0.875rem;">📞 <?php echo htmlspecialchars($product['vendor_phone']); ?></div>
                                <?php endif; ?>
                            </div>
                            <button class="btn btn-secondary" onclick="contactVendor()">Contact Vendor</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Product Tabs -->
            <div class="product-tabs">
                <div class="tab-buttons">
                    <button class="tab-button active" onclick="showTab('description')">📝 Description</button>
                    <?php if (!empty($features)): ?>
                    <button class="tab-button" onclick="showTab('features')">⭐ Features</button>
                    <?php endif; ?>
                    <?php if ($product['specifications']): ?>
                    <button class="tab-button" onclick="showTab('specifications')">🔧 Specifications</button>
                    <?php endif; ?>
                    <?php if ($product['shipping_info'] || $product['return_policy']): ?>
                    <button class="tab-button" onclick="showTab('shipping')">📦 Shipping & Returns</button>
                    <?php endif; ?>
                    <button class="tab-button" onclick="showTab('reviews')">⭐ Reviews (<?php echo $review_count; ?>)</button>
                </div>
            </div>

            <!-- Tab Contents -->
            <div id="description-tab" class="tab-content active">
                <h3 style="margin-bottom: 1.5rem;">Product Description</h3>
                <div style="line-height: 1.7; font-size: 1.1rem;">
                    <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                </div>
                
                <?php if ($product['ingredients']): ?>
                <div style="margin-top: 2rem;">
                    <h4 style="margin-bottom: 1rem;">Ingredients</h4>
                    <div style="background: var(--light-color); padding: 1rem; border-radius: var(--border-radius);">
                        <?php echo nl2br(htmlspecialchars($product['ingredients'])); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($product['care_instructions']): ?>
                <div style="margin-top: 2rem;">
                    <h4 style="margin-bottom: 1rem;">Care Instructions</h4>
                    <div style="background: var(--light-color); padding: 1rem; border-radius: var(--border-radius);">
                        <?php echo nl2br(htmlspecialchars($product['care_instructions'])); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($features)): ?>
            <div id="features-tab" class="tab-content">
                <h3 style="margin-bottom: 1.5rem;">Key Features</h3>
                <ul class="feature-list">
                    <?php foreach ($features as $feature): ?>
                        <?php if (trim($feature)): ?>
                        <li><?php echo htmlspecialchars(trim($feature)); ?></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <?php if ($product['specifications']): ?>
            <div id="specifications-tab" class="tab-content">
                <h3 style="margin-bottom: 1.5rem;">Technical Specifications</h3>
                <div style="background: var(--light-color); padding: 2rem; border-radius: var(--border-radius);">
                    <div style="white-space: pre-line; line-height: 1.6; font-family: monospace;">
                        <?php echo htmlspecialchars($product['specifications']); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($product['shipping_info'] || $product['return_policy']): ?>
            <div id="shipping-tab" class="tab-content">
                <h3 style="margin-bottom: 1.5rem;">Shipping & Returns</h3>
                
                <?php if ($product['shipping_info']): ?>
                <div style="margin-bottom: 2rem;">
                    <h4 style="margin-bottom: 1rem;">📦 Shipping Information</h4>
                    <div style="background: var(--light-color); padding: 1.5rem; border-radius: var(--border-radius);">
                        <?php echo nl2br(htmlspecialchars($product['shipping_info'])); ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($product['return_policy']): ?>
                <div>
                    <h4 style="margin-bottom: 1rem;">🔄 Return Policy</h4>
                    <div style="background: var(--light-color); padding: 1.5rem; border-radius: var(--border-radius);">
                        <?php echo nl2br(htmlspecialchars($product['return_policy'])); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div id="reviews-tab" class="tab-content">
                <h3 style="margin-bottom: 1.5rem;">Customer Reviews</h3>
                
                <?php if (!empty($reviews)): ?>
                <div style="margin-bottom: 2rem;">
                    <div style="display: flex; align-items: center; gap: 2rem; background: var(--light-color); padding: 1.5rem; border-radius: var(--border-radius);">
                        <div style="text-align: center;">
                            <div style="font-size: 3rem; font-weight: bold; color: var(--primary-color);"><?php echo $avg_rating; ?></div>
                            <div class="rating-stars" style="font-size: 1.5rem; margin: 0.5rem 0;">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <?php echo $i <= $avg_rating ? '★' : '☆'; ?>
                                <?php endfor; ?>
                            </div>
                            <div style="color: var(--text-secondary);"><?php echo $review_count; ?> review<?php echo $review_count !== 1 ? 's' : ''; ?></div>
                        </div>
                        <div style="flex: 1;">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                            <?php
                            $star_count_query = $pdo->prepare("SELECT COUNT(*) FROM product_reviews WHERE product_id = ? AND rating = ? AND status = 'approved'");
                            $star_count_query->execute([$product_id, $i]);
                            $star_count = $star_count_query->fetchColumn();
                            $percentage = $review_count > 0 ? ($star_count / $review_count) * 100 : 0;
                            ?>
                            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem;">
                                <span style="width: 60px;"><?php echo $i; ?> star<?php echo $i !== 1 ? 's' : ''; ?></span>
                                <div style="flex: 1; background: #e5e7eb; height: 8px; border-radius: 4px; overflow: hidden;">
                                    <div style="width: <?php echo $percentage; ?>%; height: 100%; background: #fbbf24;"></div>
                                </div>
                                <span style="width: 40px; text-align: right; font-size: 0.875rem;"><?php echo $star_count; ?></span>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>

                <div style="display: grid; gap: 1.5rem;">
                    <?php foreach ($reviews as $review): ?>
                    <div class="review-card">
                        <div class="review-header">
                            <div class="reviewer-info">
                                <div class="reviewer-name"><?php echo htmlspecialchars($review['reviewer_name']); ?></div>
                                <div class="rating-stars" style="font-size: 1rem; margin: 0.25rem 0;">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <?php echo $i <= $review['rating'] ? '★' : '☆'; ?>
                                    <?php endfor; ?>
                                </div>
                                <div class="review-date"><?php echo date('M j, Y', strtotime($review['created_at'])); ?></div>
                            </div>
                            <?php if ($review['verified_purchase']): ?>
                            <div style="background: var(--success-color); color: white; padding: 0.25rem 0.75rem; border-radius: var(--border-radius); font-size: 0.75rem; height: fit-content;">
                                ✅ Verified Purchase
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($review['review_title']): ?>
                        <h5 style="margin-bottom: 0.75rem; font-weight: 600;"><?php echo htmlspecialchars($review['review_title']); ?></h5>
                        <?php endif; ?>
                        
                        <?php if ($review['review_text']): ?>
                        <p style="line-height: 1.6; margin-bottom: 1rem;"><?php echo nl2br(htmlspecialchars($review['review_text'])); ?></p>
                        <?php endif; ?>
                        
                        <?php if ($review['pros'] || $review['cons']): ?>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem;">
                            <?php if ($review['pros']): ?>
                            <div>
                                <h6 style="color: var(--success-color); margin-bottom: 0.5rem;">👍 Pros</h6>
                                <p style="font-size: 0.875rem; line-height: 1.5;"><?php echo nl2br(htmlspecialchars($review['pros'])); ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($review['cons']): ?>
                            <div>
                                <h6 style="color: var(--danger-color); margin-bottom: 0.5rem;">👎 Cons</h6>
                                <p style="font-size: 0.875rem; line-height: 1.5;"><?php echo nl2br(htmlspecialchars($review['cons'])); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                            <button onclick="markHelpful(<?php echo $review['id']; ?>)" style="background: none; border: 1px solid var(--border-color); padding: 0.5rem 1rem; border-radius: var(--border-radius); cursor: pointer; font-size: 0.875rem;">
                                👍 Helpful (<?php echo $review['helpful_count']; ?>)
                            </button>
                            <button onclick="reportReview(<?php echo $review['id']; ?>)" style="background: none; border: none; color: var(--text-secondary); cursor: pointer; font-size: 0.875rem;">
                                🚩 Report
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($review_count > 5): ?>
                <div style="text-align: center; margin-top: 2rem;">
                    <button onclick="loadMoreReviews()" class="btn btn-secondary">Load More Reviews</button>
                </div>
                <?php endif; ?>

                <?php else: ?>
                <div style="text-align: center; padding: 4rem 2rem;">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">⭐</div>
                    <h4 style="margin-bottom: 1rem;">No reviews yet</h4>
                    <p style="color: var(--text-secondary); margin-bottom: 2rem;">Be the first to review this product and help other customers!</p>
                    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_type'] === 'buyer'): ?>
                    <button onclick="writeReview()" class="btn btn-primary">Write a Review</button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Related Products -->
            <?php if (!empty($related_products)): ?>
            <div style="margin-top: 4rem;">
                <h3 style="margin-bottom: 2rem;">Related Products</h3>
                <div class="related-products-grid">
                    <?php foreach ($related_products as $related): ?>
                    <a href="product_detail.php?id=<?php echo $related['id']; ?>" class="related-product-card">
                        <img src="uploads/products/<?php echo htmlspecialchars($related['image_path']); ?>" 
                             alt="<?php echo htmlspecialchars($related['name']); ?>" 
                             class="related-product-image"
                             loading="lazy">
                        <div class="related-product-info">
                            <h4 class="related-product-name"><?php echo htmlspecialchars($related['name']); ?></h4>
                            <?php if ($related['short_description']): ?>
                            <p style="color: var(--text-secondary); font-size: 0.875rem; margin: 0.5rem 0; display: -webkit-box; -webkit-box-orient: vertical; overflow: hidden;">
                                <?php echo htmlspecialchars($related['short_description']); ?>
                            </p>
                            <?php endif; ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1rem;">
                                <div class="related-product-price">$<?php echo number_format($related['price'], 2); ?></div>
                                <?php if ($related['review_count'] > 0): ?>
                                <div style="display: flex; align-items: center; gap: 0.25rem; font-size: 0.875rem;">
                                    <span style="color: #fbbf24;">★</span>
                                    <span><?php echo round($related['avg_rating'], 1); ?></span>
                                    <span style="color: var(--text-secondary);">(<?php echo $related['review_count']; ?>)</span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Image Zoom Modal -->
    <div id="imageModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 1000; cursor: zoom-out;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); max-width: 90%; max-height: 90%;">
            <img id="modalImage" style="max-width: 100%; max-height: 100%; object-fit: contain;">
        </div>
        <button onclick="closeImageModal()" style="position: absolute; top: 2rem; right: 2rem; background: rgba(255,255,255,0.2); border: none; color: white; font-size: 2rem; width: 50px; height: 50px; border-radius: 50%; cursor: pointer;">&times;</button>
    </div>

    <footer>
        <div class="container">
            <div class="footer-bottom">
                <p>&copy; 2025 CartHub. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // Image gallery functionality
        function changeMainImage(thumbnail, imagePath) {
            document.getElementById('main-image').src = 'uploads/products/' + imagePath;
            
            // Update active thumbnail
            document.querySelectorAll('.thumbnail').forEach(t => t.classList.remove('active'));
            thumbnail.classList.add('active');
        }

        // Tab functionality
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Add active class to clicked tab button
            event.target.classList.add('active');
        }

        // Quantity controls
        function increaseQuantity() {
            const input = document.getElementById('quantity');
            const max = parseInt(input.getAttribute('max'));
            const current = parseInt(input.value);
            if (current < max) {
                input.value = current + 1;
            }
        }

        function decreaseQuantity() {
            const input = document.getElementById('quantity');
            const min = parseInt(input.getAttribute('min'));
            const current = parseInt(input.value);
            if (current > min) {
                input.value = current - 1;
            }
        }

        // Add to cart functionality
        document.getElementById('add-to-cart-form')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const button = this.querySelector('button[type="submit"]');
            const originalText = button.innerHTML;
            
            button.innerHTML = '⏳ Adding...';
            button.disabled = true;
            
            fetch('add_to_cart.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    button.innerHTML = '✅ Added!';
                    // Update cart count if element exists
                    const cartCount = document.getElementById('cart-count');
                    if (cartCount && data.cart_count) {
                        cartCount.textContent = data.cart_
                    }
                } else {
                    button.innerHTML = '❌ Error!';
                    alert(data.message || 'Failed to add to cart.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                button.innerHTML = '❌ Error!';
                alert('An error occurred. Please try again.');
            })
            .finally(() => {
                setTimeout(() => {
                    button.innerHTML = originalText;
                    button.disabled = false;
                }, 2000);
            });
        });

        // Buy now functionality
        function buyNow() {
            const form = document.getElementById('add-to-cart-form');
            const formData = new FormData(form);

            fetch('add_to_cart.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'cart.php';
                } else {
                    alert(data.message || 'Failed to add to cart.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }

        // Contact vendor functionality
        function contactVendor() {
            // You can implement a contact form or redirect to a contact page
            alert('Contact vendor functionality not implemented yet.');
        }

        // Mark review as helpful
        function markHelpful(reviewId) {
            fetch('mark_helpful.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'review_id=' + reviewId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update helpful count on the page
                    const button = document.querySelector(`[onclick="markHelpful(${reviewId})"]`);
                    button.innerHTML = `👍 Helpful (${data.helpful_count})`;
                } else {
                    alert(data.message || 'Failed to mark as helpful.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }

        // Report review
        function reportReview(reviewId) {
            if (confirm('Are you sure you want to report this review?')) {
                fetch('report_review.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'review_id=' + reviewId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Review reported successfully.');
                    } else {
                        alert(data.message || 'Failed to report review.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                });
            }
        }

        // Write a review
        function writeReview() {
            // You can implement a review form or redirect to a review page
            alert('Write a review functionality not implemented yet.');
        }

        // Load more reviews
        function loadMoreReviews() {
            // You can implement pagination or load more reviews via AJAX
            alert('Load more reviews functionality not implemented yet.');
        }

        // Image zoom functionality
        document.getElementById('main-image').addEventListener('click', function() {
            document.getElementById('modalImage').src = this.src;
            document.getElementById('imageModal').style.display = 'flex';
        });

        function closeImageModal() {
            document.getElementById('imageModal').style.display = 'none';
        }
    </script>
</body>
</html>
