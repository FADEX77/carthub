<?php
require_once '../config/database.php';
requireLogin('vendor');

$vendor_id = $_SESSION['user_id'];
$error = '';
$success = '';

if ($_POST) {
    // Basic Information
    $name = sanitize_input($_POST['name']);
    $description = sanitize_input($_POST['description']);
    $price = floatval($_POST['price']);
    $stock_quantity = intval($_POST['stock_quantity']);
    $category = sanitize_input($_POST['category']);
    $custom_category = sanitize_input($_POST['custom_category'] ?? '');
    $status = sanitize_input($_POST['status']);
    
    // Detailed Information
    $brand = sanitize_input($_POST['brand'] ?? '');
    $model = sanitize_input($_POST['model'] ?? '');
    $color = sanitize_input($_POST['color'] ?? '');
    $material = sanitize_input($_POST['material'] ?? '');
    $warranty = sanitize_input($_POST['warranty'] ?? '');
    $features = sanitize_input($_POST['features'] ?? '');
    $specifications = sanitize_input($_POST['specifications'] ?? '');
    $shipping_info = sanitize_input($_POST['shipping_info'] ?? '');
    $return_policy = sanitize_input($_POST['return_policy'] ?? '');
    $tags = sanitize_input($_POST['tags'] ?? '');
    $weight = floatval($_POST['weight'] ?? 0);
    $dimensions = sanitize_input($_POST['dimensions'] ?? '');
    $sku = sanitize_input($_POST['sku'] ?? '');
    $video_url = sanitize_input($_POST['video_url'] ?? '');
    $min_order_qty = intval($_POST['min_order_quantity'] ?? 1);
    $max_order_qty = intval($_POST['max_order_quantity'] ?? 0) ?: NULL;
    $availability_status = sanitize_input($_POST['availability_status'] ?? 'in_stock');
    
    // SEO Information
    $meta_title = sanitize_input($_POST['meta_title'] ?? '');
    $meta_description = sanitize_input($_POST['meta_description'] ?? '');
    
    // Use custom category if provided
    if ($category === 'Other' && !empty($custom_category)) {
        $category = $custom_category;
    }
    
    // Handle multiple image uploads
    $uploaded_images = [];
    if (isset($_FILES['product_images'])) {
        $upload_dir = '../uploads/products/';
        
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $max_file_size = 5 * 1024 * 1024; // 5MB
        
        for ($i = 0; $i < count($_FILES['product_images']['name']); $i++) {
            if ($_FILES['product_images']['error'][$i] === UPLOAD_ERR_OK) {
                $file_size = $_FILES['product_images']['size'][$i];
                $file_extension = strtolower(pathinfo($_FILES['product_images']['name'][$i], PATHINFO_EXTENSION));
                
                if ($file_size > $max_file_size) {
                    $error = 'Image file size must be less than 5MB';
                    break;
                }
                
                if (in_array($file_extension, $allowed_extensions)) {
                    $new_filename = uniqid() . '_' . time() . '_' . $i . '.' . $file_extension;
                    $upload_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['product_images']['tmp_name'][$i], $upload_path)) {
                        $uploaded_images[] = $new_filename;
                    } else {
                        $error = 'Failed to upload image ' . ($i + 1);
                        break;
                    }
                } else {
                    $error = 'Invalid image format for image ' . ($i + 1) . '. Please use JPG, PNG, GIF, or WebP';
                    break;
                }
            }
        }
    }
    
    // Validation
    if (empty($name) || empty($description) || $price <= 0 || $stock_quantity < 0) {
        $error = 'Please fill in all required fields with valid values';
    } elseif (empty($uploaded_images)) {
        $error = 'Please upload at least one product image';
    } elseif (empty($error)) {
        // Check if SKU already exists (if provided)
        if (!empty($sku)) {
            $sku_check = $pdo->prepare("SELECT id FROM products WHERE sku = ? AND vendor_id != ?");
            $sku_check->execute([$sku, $vendor_id]);
            if ($sku_check->fetch()) {
                $error = 'SKU already exists. Please use a unique SKU.';
            }
        }
        
        if (empty($error)) {
            $primary_image = $uploaded_images[0];
            $additional_images = implode(',', array_slice($uploaded_images, 1));
            
            $stmt = $pdo->prepare("
                INSERT INTO products (
                    vendor_id, name, description, price, stock_quantity, category, image_path, additional_images,
                    brand, model, color, material, warranty, features, specifications, shipping_info, return_policy,
                    tags, weight, dimensions, sku, video_url, min_order_quantity, max_order_quantity,
                    availability_status, meta_title, meta_description, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            if ($stmt->execute([
                $vendor_id, $name, $description, $price, $stock_quantity, $category, $primary_image, $additional_images,
                $brand, $model, $color, $material, $warranty, $features, $specifications, $shipping_info, $return_policy,
                $tags, $weight, $dimensions, $sku, $video_url, $min_order_qty, $max_order_qty,
                $availability_status, $meta_title, $meta_description, $status
            ])) {
                $product_id = $pdo->lastInsertId();
                $success = "Product added successfully! <a href='../product_detail.php?id=$product_id' target='_blank'>View Product Page</a>";
                $_POST = [];
            } else {
                $error = 'Error adding product. Please try again.';
                foreach ($uploaded_images as $image) {
                    if (file_exists($upload_dir . $image)) {
                        unlink($upload_dir . $image);
                    }
                }
            }
        }
    }
}

$categories = [
    'Electronics', 'Fashion', 'Home & Garden', 'Sports & Outdoors', 'Books & Media', 
    'Beauty & Personal Care', 'Toys & Games', 'Automotive', 'Music & Instruments', 
    'Pet Supplies', 'Health & Wellness', 'Jewelry & Accessories', 'Art & Crafts', 
    'Food & Beverages', 'Office Supplies', 'Baby & Kids', 'Travel & Luggage', 'Other'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Detailed Product - CartHub Vendor</title>
    <link rel="stylesheet" href="../css/modern_style.css">
    <link rel="icon" href="images/carthub-logo.png" type="image/png">
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
                        <li><a href="products.php">My Products</a></li>
                        <li><a href="orders.php">Orders</a></li>
                        <li><a href="analytics.php">Analytics</a></li>
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
                <h1 class="dashboard-title">🛍️ Add Detailed Product</h1>
                <p class="dashboard-subtitle">Create a comprehensive product listing with detailed information</p>
            </div>

            <div class="form-container" style="max-width: 1200px;">
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?php echo $success; ?>
                        <div style="margin-top: 1rem;">
                            <a href="add_product_detailed.php" class="btn btn-primary">Add Another Product</a>
                            <a href="products.php" class="btn btn-secondary">View All Products</a>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" id="detailedProductForm">
                    <!-- Progress Indicator -->
                    <div style="margin-bottom: 2rem;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 1rem;">
                            <span class="step-indicator active" data-step="1">📷 Images</span>
                            <span class="step-indicator" data-step="2">📝 Basic Info</span>
                            <span class="step-indicator" data-step="3">🔧 Details</span>
                            <span class="step-indicator" data-step="4">📦 Shipping</span>
                            <span class="step-indicator" data-step="5">🔍 SEO</span>
                        </div>
                        <div style="height: 4px; background: var(--light-color); border-radius: 2px;">
                            <div id="progress-bar" style="height: 100%; background: var(--primary-color); border-radius: 2px; width: 20%; transition: width 0.3s;"></div>
                        </div>
                    </div>

                    <!-- Step 1: Product Images -->
                    <div class="form-step active" id="step-1">
                        <div class="table-container">
                            <div class="table-header">
                                <h3 class="table-title">📷 Product Images (Step 1/5)</h3>
                            </div>
                            <div style="padding: 2rem;">
                                <div class="form-group">
                                    <label for="product_images">Product Images * (Max 5 images, 5MB each)</label>
                                    <div id="image-upload-area" style="border: 2px dashed var(--border-color); border-radius: var(--border-radius); padding: 3rem; text-align: center; background: var(--light-color);">
                                        <div id="upload-placeholder">
                                            <div style="font-size: 4rem; color: var(--text-secondary); margin-bottom: 1rem;">📷</div>
                                            <h4 style="margin-bottom: 1rem;">Drag & Drop Images Here</h4>
                                            <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">Upload high-quality images to showcase your product</p>
                                            <button type="button" onclick="document.getElementById('product_images').click()" class="btn btn-primary">Choose Images</button>
                                        </div>
                                        <input type="file" name="product_images[]" id="product_images" accept="image/*" multiple required style="display: none;">
                                    </div>
                                    <div id="image-preview" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 1rem; margin-top: 1rem;"></div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="video_url">Product Video URL (Optional)</label>
                                    <input type="url" name="video_url" id="video_url" placeholder="https://youtube.com/watch?v=... or https://vimeo.com/..." value="<?php echo htmlspecialchars($_POST['video_url'] ?? ''); ?>">
                                    <small style="color: var(--text-secondary);">Add a YouTube or Vimeo video to showcase your product</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Basic Information -->
                    <div class="form-step" id="step-2">
                        <div class="table-container">
                            <div class="table-header">
                                <h3 class="table-title">📝 Basic Information (Step 2/5)</h3>
                            </div>
                            <div style="padding: 2rem;">
                                <div class="form-group">
                                    <label for="name">Product Name *</label>
                                    <input type="text" name="name" id="name" required placeholder="Enter a descriptive product name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                                </div>

                                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">
                                    <div class="form-group">
                                        <label for="category">Category *</label>
                                        <select name="category" id="category" required onchange="toggleCustomCategory()">
                                            <option value="">Select Category</option>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?php echo $cat; ?>" <?php echo ($_POST['category'] ?? '') === $cat ? 'selected' : ''; ?>>
                                                    <?php echo $cat; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="form-group" id="custom-category-group" style="display: none;">
                                        <label for="custom_category">Custom Category</label>
                                        <input type="text" name="custom_category" id="custom_category" placeholder="Enter custom category" value="<?php echo htmlspecialchars($_POST['custom_category'] ?? ''); ?>">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="description">Product Description *</label>
                                    <textarea name="description" id="description" required placeholder="Provide a detailed description of your product..." style="min-height: 150px;"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                </div>

                                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                                    <div class="form-group">
                                        <label for="price">Price (USD) *</label>
                                        <input type="number" name="price" id="price" step="0.01" min="0" required placeholder="0.00" value="<?php echo $_POST['price'] ?? ''; ?>">
                                    </div>

                                    <div class="form-group">
                                        <label for="stock_quantity">Stock Quantity *</label>
                                        <input type="number" name="stock_quantity" id="stock_quantity" min="0" required placeholder="0" value="<?php echo $_POST['stock_quantity'] ?? ''; ?>">
                                    </div>

                                    <div class="form-group">
                                        <label for="availability_status">Availability Status</label>
                                        <select name="availability_status" id="availability_status">
                                            <option value="in_stock" <?php echo ($_POST['availability_status'] ?? 'in_stock') === 'in_stock' ? 'selected' : ''; ?>>In Stock</option>
                                            <option value="out_of_stock" <?php echo ($_POST['availability_status'] ?? '') === 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
                                            <option value="pre_order" <?php echo ($_POST['availability_status'] ?? '') === 'pre_order' ? 'selected' : ''; ?>>Pre-Order</option>
                                            <option value="discontinued" <?php echo ($_POST['availability_status'] ?? '') === 'discontinued' ? 'selected' : ''; ?>>Discontinued</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Detailed Information -->
                    <div class="form-step" id="step-3">
                        <div class="table-container">
                            <div class="table-header">
                                <h3 class="table-title">🔧 Detailed Information (Step 3/5)</h3>
                            </div>
                            <div style="padding: 2rem;">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                    <div class="form-group">
                                        <label for="brand">Brand</label>
                                        <input type="text" name="brand" id="brand" placeholder="Product brand" value="<?php echo htmlspecialchars($_POST['brand'] ?? ''); ?>">
                                    </div>

                                    <div class="form-group">
                                        <label for="model">Model</label>
                                        <input type="text" name="model" id="model" placeholder="Product model" value="<?php echo htmlspecialchars($_POST['model'] ?? ''); ?>">
                                    </div>

                                    <div class="form-group">
                                        <label for="color">Color</label>
                                        <input type="text" name="color" id="color" placeholder="Product color" value="<?php echo htmlspecialchars($_POST['color'] ?? ''); ?>">
                                    </div>

                                    <div class="form-group">
                                        <label for="material">Material</label>
                                        <input type="text" name="material" id="material" placeholder="Product material" value="<?php echo htmlspecialchars($_POST['material'] ?? ''); ?>">
                                    </div>

                                    <div class="form-group">
                                        <label for="weight">Weight (lbs)</label>
                                        <input type="number" name="weight" id="weight" step="0.01" min="0" placeholder="0.00" value="<?php echo $_POST['weight'] ?? ''; ?>">
                                    </div>

                                    <div class="form-group">
                                        <label for="dimensions">Dimensions</label>
                                        <input type="text" name="dimensions" id="dimensions" placeholder="L x W x H (inches)" value="<?php echo htmlspecialchars($_POST['dimensions'] ?? ''); ?>">
                                    </div>

                                    <div class="form-group">
                                        <label for="sku">SKU</label>
                                        <input type="text" name="sku" id="sku" placeholder="Product SKU/Code" value="<?php echo htmlspecialchars($_POST['sku'] ?? ''); ?>">
                                    </div>

                                    <div class="form-group">
                                        <label for="warranty">Warranty</label>
                                        <input type="text" name="warranty" id="warranty" placeholder="e.g., 1 year manufacturer warranty" value="<?php echo htmlspecialchars($_POST['warranty'] ?? ''); ?>">
                                    </div>
                                </div>

                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                    <div class="form-group">
                                        <label for="min_order_quantity">Minimum Order Quantity</label>
                                        <input type="number" name="min_order_quantity" id="min_order_quantity" min="1" value="<?php echo $_POST['min_order_quantity'] ?? '1'; ?>">
                                    </div>

                                    <div class="form-group">
                                        <label for="max_order_quantity">Maximum Order Quantity (Optional)</label>
                                        <input type="number" name="max_order_quantity" id="max_order_quantity" min="1" placeholder="Leave empty for no limit" value="<?php echo $_POST['max_order_quantity'] ?? ''; ?>">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="features">Key Features</label>
                                    <textarea name="features" id="features" placeholder="List the key features of your product (one per line)" style="min-height: 100px;"><?php echo htmlspecialchars($_POST['features'] ?? ''); ?></textarea>
                                    <small style="color: var(--text-secondary);">Enter each feature on a new line</small>
                                </div>

                                <div class="form-group">
                                    <label for="specifications">Technical Specifications</label>
                                    <textarea name="specifications" id="specifications" placeholder="Detailed technical specifications" style="min-height: 100px;"><?php echo htmlspecialchars($_POST['specifications'] ?? ''); ?></textarea>
                                </div>

                                <div class="form-group">
                                    <label for="tags">Tags</label>
                                    <input type="text" name="tags" id="tags" placeholder="e.g., wireless, bluetooth, portable, waterproof (separate with commas)" value="<?php echo htmlspecialchars($_POST['tags'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 4: Shipping & Returns -->
                    <div class="form-step" id="step-4">
                        <div class="table-container">
                            <div class="table-header">
                                <h3 class="table-title">📦 Shipping & Returns (Step 4/5)</h3>
                            </div>
                            <div style="padding: 2rem;">
                                <div class="form-group">
                                    <label for="shipping_info">Shipping Information</label>
                                    <textarea name="shipping_info" id="shipping_info" placeholder="Shipping methods, timeframes, and costs..." style="min-height: 100px;"><?php echo htmlspecialchars($_POST['shipping_info'] ?? ''); ?></textarea>
                                </div>

                                <div class="form-group">
                                    <label for="return_policy">Return Policy</label>
                                    <textarea name="return_policy" id="return_policy" placeholder="Return conditions, timeframes, and process..." style="min-height: 100px;"><?php echo htmlspecialchars($_POST['return_policy'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 5: SEO & Publishing -->
                    <div class="form-step" id="step-5">
                        <div class="table-container">
                            <div class="table-header">
                                <h3 class="table-title">🔍 SEO & Publishing (Step 5/5)</h3>
                            </div>
                            <div style="padding: 2rem;">
                                <div class="form-group">
                                    <label for="meta_title">SEO Title</label>
                                    <input type="text" name="meta_title" id="meta_title" placeholder="SEO-friendly title for search engines" value="<?php echo htmlspecialchars($_POST['meta_title'] ?? ''); ?>">
                                    <small style="color: var(--text-secondary);">Recommended: 50-60 characters</small>
                                </div>

                                <div class="form-group">
                                    <label for="meta_description">SEO Description</label>
                                    <textarea name="meta_description" id="meta_description" placeholder="Brief description for search engine results" style="min-height: 80px;"><?php echo htmlspecialchars($_POST['meta_description'] ?? ''); ?></textarea>
                                    <small style="color: var(--text-secondary);">Recommended: 150-160 characters</small>
                                </div>

                                <div class="form-group">
                                    <label for="status">Publication Status *</label>
                                    <select name="status" id="status" required>
                                        <option value="active" <?php echo ($_POST['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active (Visible to customers)</option>
                                        <option value="inactive" <?php echo ($_POST['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Draft (Hidden from customers)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Navigation Buttons -->
                    <div style="display: flex; justify-content: space-between; margin-top: 2rem; padding: 1rem; background: var(--light-color); border-radius: var(--border-radius);">
                        <button type="button" id="prev-btn" onclick="previousStep()" class="btn btn-secondary" style="display: none;">← Previous</button>
                        <div style="flex: 1;"></div>
                        <button type="button" id="next-btn" onclick="nextStep()" class="btn btn-primary">Next →</button>
                        <button type="submit" id="submit-btn" class="btn btn-primary" style="display: none;">✅ Create Product</button>
                    </div>
                </form>
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

    <style>
        .step-indicator {
            padding: 0.5rem 1rem;
            background: var(--light-color);
            border-radius: var(--border-radius);
            font-size: 0.875rem;
            color: var(--text-secondary);
            transition: var(--transition);
        }

        .step-indicator.active {
            background: var(--primary-color);
            color: white;
        }

        .step-indicator.completed {
            background: var(--success-color);
            color: white;
        }

        .form-step {
            display: none;
        }

        .form-step.active {
            display: block;
        }

        @media (max-width: 768px) {
            .step-indicator {
                font-size: 0.75rem;
                padding: 0.25rem 0.5rem;
            }
        }
    </style>

    <script>
        let currentStep = 1;
        let selectedFiles = [];

        // Step navigation
        function nextStep() {
            if (validateCurrentStep()) {
                if (currentStep < 5) {
                    currentStep++;
                    showStep(currentStep);
                }
            }
        }

        function previousStep() {
            if (currentStep > 1) {
                currentStep--;
                showStep(currentStep);
            }
        }

        function showStep(step) {
            // Hide all steps
            document.querySelectorAll('.form-step').forEach(s => s.classList.remove('active'));
            document.querySelectorAll('.step-indicator').forEach(s => s.classList.remove('active'));
            
            // Show current step
            document.getElementById(`step-${step}`).classList.add('active');
            document.querySelector(`[data-step="${step}"]`).classList.add('active');
            
            // Mark completed steps
            for (let i = 1; i < step; i++) {
                document.querySelector(`[data-step="${i}"]`).classList.add('completed');
            }
            
            // Update progress bar
            const progress = (step / 5) * 100;
            document.getElementById('progress-bar').style.width = progress + '%';
            
            // Update navigation buttons
            document.getElementById('prev-btn').style.display = step > 1 ? 'block' : 'none';
            document.getElementById('next-btn').style.display = step < 5 ? 'block' : 'none';
            document.getElementById('submit-btn').style.display = step === 5 ? 'block' : 'none';
        }

        function validateCurrentStep() {
            switch (currentStep) {
                case 1:
                    if (selectedFiles.length === 0) {
                        alert('Please upload at least one product image');
                        return false;
                    }
                    break;
                case 2:
                    const name = document.getElementById('name').value;
                    const description = document.getElementById('description').value;
                    const price = document.getElementById('price').value;
                    const category = document.getElementById('category').value;
                    
                    if (!name || !description || !price || !category) {
                        alert('Please fill in all required fields');
                        return false;
                    }
                    break;
            }
            return true;
        }

        // Image handling (same as before)
        document.getElementById('product_images').addEventListener('change', function(e) {
            handleFiles(e.target.files);
        });

        function handleFiles(files) {
            if (files.length > 5) {
                alert('Maximum 5 images allowed');
                return;
            }
            selectedFiles = Array.from(files);
            displayImagePreviews();
        }

        function displayImagePreviews() {
            const preview = document.getElementById('image-preview');
            const placeholder = document.getElementById('upload-placeholder');
            
            if (selectedFiles.length > 0) {
                placeholder.style.display = 'none';
                preview.innerHTML = '';
                
                selectedFiles.forEach((file, index) => {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const imageContainer = document.createElement('div');
                        imageContainer.style.cssText = 'position: relative; border: 2px solid var(--border-color); border-radius: var(--border-radius); overflow: hidden;';
                        
                        imageContainer.innerHTML = `
                            <img src="${e.target.result}" style="width: 100%; height: 150px; object-fit: cover;">
                            <div style="position: absolute; top: 0.5rem; right: 0.5rem;">
                                <button type="button" onclick="removeImage(${index})" style="background: var(--danger-color); color: white; border: none; border-radius: 50%; width: 24px; height: 24px; cursor: pointer;">&times;</button>
                            </div>
                            <div style="position: absolute; bottom: 0; left: 0; right: 0; background: rgba(0,0,0,0.7); color: white; padding: 0.25rem; font-size: 0.75rem; text-align: center;">
                                ${index === 0 ? 'Main Image' : `Image ${index + 1}`}
                            </div>
                        `;
                        preview.appendChild(imageContainer);
                    };
                    reader.readAsDataURL(file);
                });
            } else {
                placeholder.style.display = 'block';
                preview.innerHTML = '';
            }
        }

        function removeImage(index) {
            selectedFiles.splice(index, 1);
            displayImagePreviews();
            
            const dt = new DataTransfer();
            selectedFiles.forEach(file => dt.items.add(file));
            document.getElementById('product_images').files = dt.files;
        }

        function toggleCustomCategory() {
            const category = document.getElementById('category').value;
            const customGroup = document.getElementById('custom-category-group');
            
            if (category === 'Other') {
                customGroup.style.display = 'block';
                document.getElementById('custom_category').required = true;
            } else {
                customGroup.style.display = 'none';
                document.getElementById('custom_category').required = false;
            }
        }

        // Initialize
        showStep(1);
        toggleCustomCategory();
    </script>
</body>
</html>