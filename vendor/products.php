<?php
require_once '../config/database.php';
requireLogin('vendor');

$vendor_id = $_SESSION['user_id'];
$message = '';

// Handle product actions
if ($_POST) {
    $action = $_POST['action'] ?? '';
    $product_id = intval($_POST['product_id'] ?? 0);
    
    if ($action === 'toggle_status' && $product_id) {
        $stmt = $pdo->prepare("UPDATE products SET status = CASE WHEN status = 'active' THEN 'inactive' ELSE 'active' END WHERE id = ? AND vendor_id = ?");
        if ($stmt->execute([$product_id, $vendor_id])) {
            $message = "Product status updated successfully!";
        }
    } elseif ($action === 'delete' && $product_id) {
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ? AND vendor_id = ?");
        if ($stmt->execute([$product_id, $vendor_id])) {
            $message = "Product deleted successfully!";
        }
    } elseif ($action === 'update_stock' && $product_id) {
        $new_stock = intval($_POST['new_stock'] ?? 0);
        $stmt = $pdo->prepare("UPDATE products SET stock_quantity = ? WHERE id = ? AND vendor_id = ?");
        if ($stmt->execute([$new_stock, $product_id, $vendor_id])) {
            $message = "Stock updated successfully!";
        }
    }
}

// Pagination and filtering
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$category = isset($_GET['category']) ? sanitize_input($_GET['category']) : '';
$status = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';

// Build query
$where_conditions = ["vendor_id = ?"];
$params = [$vendor_id];

if ($search) {
    $where_conditions[] = "(name LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category) {
    $where_conditions[] = "category = ?";
    $params[] = $category;
}

if ($status) {
    $where_conditions[] = "status = ?";
    $params[] = $status;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count
$count_sql = "SELECT COUNT(*) FROM products WHERE $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_products = $count_stmt->fetchColumn();
$total_pages = ceil($total_products / $per_page);

// Get products
$sql = "SELECT * FROM products WHERE $where_clause ORDER BY created_at DESC LIMIT $per_page OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get categories for filter
$categories_stmt = $pdo->prepare("SELECT DISTINCT category FROM products WHERE vendor_id = ? ORDER BY category");
$categories_stmt->execute([$vendor_id]);
$categories = $categories_stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Products - CartHub Vendor</title>
    <link rel="stylesheet" href="../css/modern_style.css">
    <link rel="icon" href="../images/carthub-logo.png" type="image/png">
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <a href="../index.php" class="logo">
                    <img src="../images/carthub-logo.png" alt="CartHub Logo">
                    CartHub Vendor
                </a>
                <nav>
                    <ul class="nav-links">
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><a href="products.php" class="btn-secondary">My Products</a></li>
                        <li><a href="add_product.php">Add Product</a></li>
                        <li><a href="orders.php">Orders</a></li>
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
                <h1 class="dashboard-title">My Products</h1>
                <p class="dashboard-subtitle">Manage your product inventory and listings</p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>

            <!-- Search and Filters -->
            <div class="search-filters">
                <form method="GET">
                    <div style="display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 1rem; align-items: end;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="search">Search Products</label>
                            <input type="text" name="search" id="search" class="search-input" placeholder="Search by name or description..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="category">Category</label>
                            <select name="category" id="category" class="search-input">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category === $cat ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="status">Status</label>
                            <select name="status" id="status" class="search-input">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Filter</button>
                    </div>
                </form>
            </div>

            <!-- Products Grid -->
            <?php if (!empty($products)): ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 2rem; margin: 2rem 0;">
                <?php foreach ($products as $product): ?>
                <div class="product-card" style="position: relative;">
                    <!-- Status Badge -->
                    <div style="position: absolute; top: 1rem; right: 1rem; z-index: 10;">
                        <span class="product-category" style="background: <?php echo $product['status'] === 'active' ? 'rgba(16, 185, 129, 0.1)' : 'rgba(239, 68, 68, 0.1)'; ?>; color: <?php echo $product['status'] === 'active' ? '#059669' : '#dc2626'; ?>;">
                            <?php echo ucfirst($product['status']); ?>
                        </span>
                    </div>
                    
                    <img src="<?php echo $product['image_path'] ? '../uploads/products/' . $product['image_path'] : '/placeholder.svg?height=240&width=350'; ?>" 
                         alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image">
                    
                    <div class="product-info">
                        <div class="product-category"><?php echo htmlspecialchars($product['category']); ?></div>
                        <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                        <p class="product-price">$<?php echo number_format($product['price'], 2); ?></p>
                        <p class="product-description"><?php echo htmlspecialchars(substr($product['description'], 0, 100)) . '...'; ?></p>
                        
                        <!-- Stock Info -->
                        <div style="display: flex; justify-content: space-between; align-items: center; margin: 1rem 0;">
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <span style="color: var(--text-secondary);">Stock:</span>
                                <span style="font-weight: 600; color: <?php echo $product['stock_quantity'] <= 10 ? 'var(--danger-color)' : 'var(--success-color)'; ?>;">
                                    <?php echo $product['stock_quantity']; ?>
                                </span>
                            </div>
                            <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                Added: <?php echo date('M j, Y', strtotime($product['created_at'])); ?>
                            </div>
                        </div>

                        <!-- Quick Stock Update -->
                        <form method="POST" style="margin-bottom: 1rem;" onsubmit="return confirm('Update stock quantity?')">
                            <input type="hidden" name="action" value="update_stock">
                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                            <div style="display: flex; gap: 0.5rem;">
                                <input type="number" name="new_stock" min="0" value="<?php echo $product['stock_quantity']; ?>" 
                                       style="flex: 1; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 6px;">
                                <button type="submit" class="btn" style="padding: 0.5rem 1rem; font-size: 0.875rem;">Update</button>
                            </div>
                        </form>

                        <!-- Action Buttons -->
                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                            <a href="edit_product.php?id=<?php echo $product['id']; ?>" class="btn btn-secondary" style="flex: 1; text-align: center; font-size: 0.875rem;">Edit</a>
                            
                            <form method="POST" style="flex: 1;" onsubmit="return confirm('Toggle product status?')">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                <button type="submit" class="btn" style="width: 100%; font-size: 0.875rem; background: <?php echo $product['status'] === 'active' ? 'var(--warning-color)' : 'var(--success-color)'; ?>; color: white;">
                                    <?php echo $product['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                                </button>
                            </form>
                            
                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this product?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                <button type="submit" class="btn btn-danger" style="font-size: 0.875rem;">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&status=<?php echo urlencode($status); ?>">Previous</a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&status=<?php echo urlencode($status); ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&status=<?php echo urlencode($status); ?>">Next</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php else: ?>
            <div style="text-align: center; padding: 4rem 0;">
                <div style="font-size: 4rem; margin-bottom: 2rem;">📦</div>
                <h3 style="color: var(--text-secondary); margin-bottom: 1rem;">No products found</h3>
                <p style="color: var(--text-secondary); margin-bottom: 2rem;">
                    <?php if ($search || $category || $status): ?>
                        Try adjusting your search criteria or filters.
                    <?php else: ?>
                        Start by adding your first product to your store.
                    <?php endif; ?>
                </p>
                <a href="add_product.php" class="btn btn-primary">Add Your First Product</a>
            </div>
            <?php endif; ?>

            <!-- Summary Stats -->
            <div style="background: var(--light-color); padding: 2rem; border-radius: var(--border-radius); margin-top: 2rem;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 2rem; text-align: center;">
                    <div>
                        <div style="font-size: 2rem; font-weight: 700; color: var(--primary-color);"><?php echo $total_products; ?></div>
                        <div style="color: var(--text-secondary);">Total Products</div>
                    </div>
                    <div>
                        <div style="font-size: 2rem; font-weight: 700; color: var(--success-color);">
                            <?php 
                            $active_count = 0;
                            foreach ($products as $p) if ($p['status'] === 'active') $active_count++;
                            echo $active_count;
                            ?>
                        </div>
                        <div style="color: var(--text-secondary);">Active Products</div>
                    </div>
                    <div>
                        <div style="font-size: 2rem; font-weight: 700; color: var(--warning-color);">
                            <?php 
                            $low_stock_count = 0;
                            foreach ($products as $p) if ($p['stock_quantity'] <= 10) $low_stock_count++;
                            echo $low_stock_count;
                            ?>
                        </div>
                        <div style="color: var(--text-secondary);">Low Stock Items</div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <div class="footer-bottom">
                <p>&copy; 2025 CartHub Vendor Portal. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>