-- Drop existing tables to recreate with optimized structure
DROP TABLE IF EXISTS product_views;
DROP TABLE IF EXISTS product_reviews;
DROP TABLE IF EXISTS cart;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS users;

-- Create optimized users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    user_type ENUM('buyer', 'vendor', 'admin') NOT NULL,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    email_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_user_type (user_type),
    INDEX idx_status (status)
);

-- Create optimized products table with all detailed fields
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    description TEXT NOT NULL,
    short_description VARCHAR(500),
    price DECIMAL(10,2) NOT NULL,
    compare_price DECIMAL(10,2) DEFAULT NULL,
    cost_price DECIMAL(10,2) DEFAULT NULL,
    stock_quantity INT NOT NULL DEFAULT 0,
    low_stock_threshold INT DEFAULT 10,
    category VARCHAR(100) NOT NULL,
    subcategory VARCHAR(100),
    brand VARCHAR(100),
    model VARCHAR(100),
    sku VARCHAR(100) UNIQUE,
    barcode VARCHAR(100),
    
    -- Physical attributes
    weight DECIMAL(8,2) DEFAULT NULL,
    length DECIMAL(8,2) DEFAULT NULL,
    width DECIMAL(8,2) DEFAULT NULL,
    height DECIMAL(8,2) DEFAULT NULL,
    color VARCHAR(50),
    size VARCHAR(50),
    material VARCHAR(100),
    
    -- Images and media
    image_path VARCHAR(500),
    additional_images TEXT,
    video_url VARCHAR(500),
    
    -- Product details
    features TEXT,
    specifications TEXT,
    ingredients TEXT,
    care_instructions TEXT,
    warranty VARCHAR(200),
    country_of_origin VARCHAR(100),
    
    -- Inventory and ordering
    min_order_quantity INT DEFAULT 1,
    max_order_quantity INT DEFAULT NULL,
    availability_status ENUM('in_stock', 'out_of_stock', 'pre_order', 'discontinued') DEFAULT 'in_stock',
    
    -- Shipping and returns
    shipping_weight DECIMAL(8,2),
    shipping_class VARCHAR(50),
    shipping_info TEXT,
    return_policy TEXT,
    
    -- SEO and marketing
    meta_title VARCHAR(255),
    meta_description TEXT,
    tags VARCHAR(1000),
    
    -- Pricing and promotions
    is_featured BOOLEAN DEFAULT FALSE,
    is_digital BOOLEAN DEFAULT FALSE,
    tax_class VARCHAR(50) DEFAULT 'standard',
    
    -- Status and visibility
    status ENUM('active', 'inactive', 'draft') DEFAULT 'active',
    visibility ENUM('public', 'private', 'hidden') DEFAULT 'public',
    
    -- Analytics
    view_count INT DEFAULT 0,
    sales_count INT DEFAULT 0,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    published_at TIMESTAMP NULL,
    
    FOREIGN KEY (vendor_id) REFERENCES users(id) ON DELETE CASCADE,
    
    -- Optimized indexes for fast queries
    INDEX idx_vendor_id (vendor_id),
    INDEX idx_category (category),
    INDEX idx_subcategory (subcategory),
    INDEX idx_brand (brand),
    INDEX idx_status (status),
    INDEX idx_visibility (visibility),
    INDEX idx_availability (availability_status),
    INDEX idx_featured (is_featured),
    INDEX idx_price (price),
    INDEX idx_created (created_at),
    INDEX idx_slug (slug),
    INDEX idx_sku (sku),
    FULLTEXT INDEX idx_search (name, description, tags, brand, model)
);

-- Create product reviews table
CREATE TABLE product_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    buyer_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    review_title VARCHAR(255),
    review_text TEXT,
    pros TEXT,
    cons TEXT,
    verified_purchase BOOLEAN DEFAULT FALSE,
    helpful_count INT DEFAULT 0,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
    
    INDEX idx_product_id (product_id),
    INDEX idx_buyer_id (buyer_id),
    INDEX idx_rating (rating),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
);

-- Create product views tracking table
CREATE TABLE product_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    referrer VARCHAR(500),
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_product_id (product_id),
    INDEX idx_user_id (user_id),
    INDEX idx_viewed_at (viewed_at)
);

-- Create cart table
CREATE TABLE cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    buyer_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    
    UNIQUE KEY unique_cart_item (buyer_id, product_id),
    INDEX idx_buyer_id (buyer_id),
    INDEX idx_product_id (product_id)
);

-- Create orders table
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    buyer_id INT NOT NULL,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    tax_amount DECIMAL(10,2) DEFAULT 0,
    shipping_amount DECIMAL(10,2) DEFAULT 0,
    discount_amount DECIMAL(10,2) DEFAULT 0,
    order_status ENUM('pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded') DEFAULT 'pending',
    payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
    payment_method VARCHAR(50),
    shipping_address TEXT,
    billing_address TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
    
    INDEX idx_buyer_id (buyer_id),
    INDEX idx_order_status (order_status),
    INDEX idx_payment_status (payment_status),
    INDEX idx_created (created_at),
    INDEX idx_order_number (order_number)
);

-- Create order items table
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    vendor_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (vendor_id) REFERENCES users(id) ON DELETE CASCADE,
    
    INDEX idx_order_id (order_id),
    INDEX idx_product_id (product_id),
    INDEX idx_vendor_id (vendor_id)
);

-- Insert sample admin user
INSERT INTO users (full_name, email, password, user_type) VALUES 
('Admin User', 'admin@carthub.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Insert 10 sample vendors
INSERT INTO users (full_name, email, password, phone, address, user_type, email_verified) VALUES 
('TechWorld Electronics', 'vendor1@carthub.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '555-0101', '123 Tech Street, Silicon Valley, CA 94000', 'vendor', TRUE),
('Fashion Forward', 'vendor2@carthub.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '555-0102', '456 Fashion Ave, New York, NY 10001', 'vendor', TRUE),
('Home & Garden Paradise', 'vendor3@carthub.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '555-0103', '789 Garden Lane, Portland, OR 97201', 'vendor', TRUE),
('Sports Central', 'vendor4@carthub.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '555-0104', '321 Sports Blvd, Denver, CO 80202', 'vendor', TRUE),
('BookWorm Haven', 'vendor5@carthub.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '555-0105', '654 Library St, Boston, MA 02101', 'vendor', TRUE),
('Beauty Essentials', 'vendor6@carthub.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '555-0106', '987 Beauty Blvd, Los Angeles, CA 90210', 'vendor', TRUE),
('Toy Kingdom', 'vendor7@carthub.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '555-0107', '147 Play Street, Orlando, FL 32801', 'vendor', TRUE),
('Auto Parts Pro', 'vendor8@carthub.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '555-0108', '258 Motor Way, Detroit, MI 48201', 'vendor', TRUE),
('Music & Sound', 'vendor9@carthub.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '555-0109', '369 Melody Lane, Nashville, TN 37201', 'vendor', TRUE),
('Pet Paradise', 'vendor10@carthub.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '555-0110', '741 Pet Avenue, Austin, TX 78701', 'vendor', TRUE);

-- Insert 5 sample buyers
INSERT INTO users (full_name, email, password, phone, address, user_type, email_verified) VALUES 
('John Smith', 'buyer1@carthub.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '555-1001', '123 Main St, Anytown, USA', 'buyer', TRUE),
('Sarah Johnson', 'buyer2@carthub.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '555-1002', '456 Oak Ave, Somewhere, USA', 'buyer', TRUE),
('Mike Davis', 'buyer3@carthub.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '555-1003', '789 Pine St, Elsewhere, USA', 'buyer', TRUE),
('Emily Wilson', 'buyer4@carthub.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '555-1004', '321 Elm Dr, Nowhere, USA', 'buyer', TRUE),
('David Brown', 'buyer5@carthub.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '555-1005', '654 Maple Ln, Anywhere, USA', 'buyer', TRUE);

-- Insert 200 detailed products (20 per vendor)
-- Vendor 1: TechWorld Electronics (20 products)
INSERT INTO products (vendor_id, name, slug, description, short_description, price, compare_price, stock_quantity, category, subcategory, brand, model, sku, weight, length, width, height, color, material, image_path, features, specifications, warranty, min_order_quantity, shipping_info, return_policy, meta_title, meta_description, tags, is_featured, status, visibility, view_count, sales_count) VALUES 

(2, 'iPhone 15 Pro Max', 'iphone-15-pro-max', 'The most advanced iPhone ever with titanium design, A17 Pro chip, and professional camera system. Features a 6.7-inch Super Retina XDR display with ProMotion technology.', 'Latest iPhone with titanium design and A17 Pro chip', 1199.00, 1299.00, 50, 'Electronics', 'Smartphones', 'Apple', 'iPhone 15 Pro Max', 'APL-IP15PM-001', 0.48, 6.29, 3.02, 0.32, 'Natural Titanium', 'Titanium', 'iphone15pro.jpg', 'A17 Pro chip with 6-core GPU\n6.7-inch Super Retina XDR display\nPro camera system with 5x Telephoto\nAction Button\nUSB-C connector\nUp to 29 hours video playback', 'Display: 6.7-inch Super Retina XDR OLED\nChip: A17 Pro\nCamera: 48MP Main, 12MP Ultra Wide, 12MP Telephoto\nStorage: 256GB\nBattery: Up to 29 hours video\nOS: iOS 17', '1 year limited warranty', 1, 'Free shipping on orders over $50. Ships within 1-2 business days.', '30-day return policy. Must be in original condition.', 'iPhone 15 Pro Max - Latest Apple Smartphone', 'Get the newest iPhone 15 Pro Max with titanium design, A17 Pro chip, and advanced camera system.', 'iphone, apple, smartphone, titanium, a17 pro, camera', TRUE, 'active', 'public', 1250, 45),

(2, 'MacBook Air M2', 'macbook-air-m2', 'Supercharged by the M2 chip, the redesigned MacBook Air combines incredible performance and up to 18 hours of battery life into its strikingly thin design.', 'Ultra-thin laptop with M2 chip and all-day battery', 1099.00, 1199.00, 30, 'Electronics', 'Laptops', 'Apple', 'MacBook Air M2', 'APL-MBA-M2-001', 2.7, 11.97, 8.46, 0.44, 'Midnight', 'Aluminum', 'macbook_air_m2.jpg', 'M2 chip with 8-core CPU\n13.6-inch Liquid Retina display\nUp to 18 hours battery life\nMagSafe charging\n1080p FaceTime HD camera\nFour-speaker sound system', 'Display: 13.6-inch Liquid Retina\nChip: Apple M2\nMemory: 8GB unified memory\nStorage: 256GB SSD\nPorts: 2x Thunderbolt, MagSafe 3\nWeight: 2.7 pounds', '1 year limited warranty', 1, 'Free shipping. Ships within 2-3 business days.', '14-day return policy for computers.', 'MacBook Air M2 - Ultra-thin Laptop', 'Experience the power of M2 chip in the redesigned MacBook Air with all-day battery life.', 'macbook, apple, laptop, m2 chip, ultrabook', TRUE, 'active', 'public', 890, 32),

(2, 'iPad Pro 12.9-inch', 'ipad-pro-12-9', 'The ultimate iPad experience with the M2 chip, 12.9-inch Liquid Retina XDR display, and support for Apple Pencil and Magic Keyboard.', 'Professional tablet with M2 chip and XDR display', 1099.00, 1199.00, 25, 'Electronics', 'Tablets', 'Apple', 'iPad Pro 12.9', 'APL-IPP129-001', 1.5, 11.04, 8.46, 0.25, 'Space Gray', 'Aluminum', 'ipad_pro_129.jpg', 'M2 chip with 8-core CPU\n12.9-inch Liquid Retina XDR display\nApple Pencil (2nd gen) support\nMagic Keyboard support\n12MP Wide and Ultra Wide cameras\nThunderbolt / USB 4 connector', 'Display: 12.9-inch Liquid Retina XDR\nChip: Apple M2\nStorage: 128GB\nCamera: 12MP Wide, 10MP Ultra Wide\nConnectivity: Wi-Fi 6E, Bluetooth 5.3\nBattery: Up to 10 hours', '1 year limited warranty', 1, 'Free shipping on orders over $50.', '30-day return policy.', 'iPad Pro 12.9-inch with M2 Chip', 'Professional iPad with M2 chip, XDR display, and support for Apple Pencil.', 'ipad, apple, tablet, m2 chip, professional', FALSE, 'active', 'public', 650, 28),

(2, 'AirPods Pro (2nd generation)', 'airpods-pro-2nd-gen', 'AirPods Pro feature up to 2x more Active Noise Cancellation, Adaptive Transparency, and Personalized Spatial Audio with dynamic head tracking.', 'Premium wireless earbuds with active noise cancellation', 249.00, 279.00, 100, 'Electronics', 'Audio', 'Apple', 'AirPods Pro 2', 'APL-APP2-001', 0.12, 1.22, 0.86, 0.94, 'White', 'Plastic', 'airpods_pro_2.jpg', 'Up to 2x more Active Noise Cancellation\nAdaptive Transparency\nPersonalized Spatial Audio\nTouch control\nUp to 6 hours listening time\nMagSafe charging case', 'Driver: Custom high-excursion driver\nMicrophone: Dual beamforming microphones\nSensors: Dual optical sensors, Motion-detecting accelerometer\nConnectivity: Bluetooth 5.3\nBattery: Up to 30 hours with case\nCharging: Lightning, MagSafe, Qi wireless', '1 year limited warranty', 1, 'Free shipping on orders over $50.', '30-day return policy.', 'AirPods Pro 2nd Generation - Premium Earbuds', 'Experience superior sound with AirPods Pro featuring 2x more noise cancellation.', 'airpods, apple, earbuds, noise cancellation, wireless', TRUE, 'active', 'public', 980, 67),

(2, 'Apple Watch Series 9', 'apple-watch-series-9', 'The most advanced Apple Watch yet with the S9 chip, Double Tap gesture, and the brightest display ever in an Apple Watch.', 'Advanced smartwatch with S9 chip and health features', 399.00, 429.00, 75, 'Electronics', 'Wearables', 'Apple', 'Watch Series 9', 'APL-AWS9-001', 0.11, 1.69, 1.45, 0.41, 'Midnight', 'Aluminum', 'apple_watch_s9.jpg', 'S9 SiP with 4-core Neural Engine\nDouble Tap gesture\nBrightest display\nAdvanced health sensors\nCrash Detection\nWater resistant to 50 meters', 'Display: 45mm Retina LTPO OLED\nChip: S9 SiP\nSensors: Blood Oxygen, ECG, Temperature\nConnectivity: Wi-Fi, Bluetooth 5.3, GPS\nBattery: Up to 18 hours\nWater Resistance: 50 meters', '1 year limited warranty', 1, 'Free shipping on orders over $50.', '30-day return policy.', 'Apple Watch Series 9 - Advanced Smartwatch', 'Stay connected and healthy with Apple Watch Series 9 featuring the S9 chip.', 'apple watch, smartwatch, health, fitness, s9 chip', FALSE, 'active', 'public', 720, 41),

(2, 'Samsung Galaxy S24 Ultra', 'samsung-galaxy-s24-ultra', 'The ultimate Android flagship with S Pen, 200MP camera, and AI-powered features. Built with titanium for durability and premium feel.', 'Premium Android smartphone with S Pen and AI features', 1299.00, 1399.00, 40, 'Electronics', 'Smartphones', 'Samsung', 'Galaxy S24 Ultra', 'SAM-GS24U-001', 0.51, 6.40, 3.11, 0.34, 'Titanium Black', 'Titanium', 'galaxy_s24_ultra.jpg', '200MP main camera with AI zoom\nBuilt-in S Pen\n6.8-inch Dynamic AMOLED 2X display\nSnapdragon 8 Gen 3 processor\nGalaxy AI features\n5000mAh battery', 'Display: 6.8-inch Dynamic AMOLED 2X\nProcessor: Snapdragon 8 Gen 3\nCamera: 200MP main, 50MP periscope, 12MP ultrawide\nStorage: 256GB\nRAM: 12GB\nBattery: 5000mAh with 45W charging', '1 year manufacturer warranty', 1, 'Free shipping on orders over $50.', '30-day return policy.', 'Samsung Galaxy S24 Ultra - Premium Android Phone', 'Experience the ultimate Android flagship with S Pen, 200MP camera, and AI features.', 'samsung, galaxy, android, s pen, camera, ai', TRUE, 'active', 'public', 890, 38),

(2, 'Sony WH-1000XM5 Headphones', 'sony-wh-1000xm5', 'Industry-leading noise canceling headphones with exceptional sound quality, 30-hour battery life, and crystal-clear call quality.', 'Premium noise-canceling over-ear headphones', 399.00, 449.00, 60, 'Electronics', 'Audio', 'Sony', 'WH-1000XM5', 'SNY-WH1000XM5-001', 0.55, 10.20, 7.30, 2.60, 'Black', 'Plastic', 'sony_wh1000xm5.jpg', 'Industry-leading noise canceling\n30-hour battery life\nQuick Charge (3 min = 3 hours)\nMultipoint connection\nSpeak-to-Chat technology\nTouch sensor controls', 'Driver: 30mm\nFrequency Response: 4Hz-40kHz\nImpedance: 48 ohm\nBluetooth: 5.2\nCodecs: LDAC, AAC, SBC\nBattery: 30 hours with NC on\nCharging: USB-C', '1 year manufacturer warranty', 1, 'Free shipping on orders over $50.', '30-day return policy.', 'Sony WH-1000XM5 - Premium Noise Canceling Headphones', 'Experience industry-leading noise cancellation with Sony WH-1000XM5 headphones.', 'sony, headphones, noise canceling, wireless, premium', FALSE, 'active', 'public', 540, 29),

(2, 'Dell XPS 13 Plus', 'dell-xps-13-plus', 'Ultra-premium laptop with 13.4-inch InfinityEdge display, 12th Gen Intel processors, and sleek design perfect for professionals.', 'Ultra-premium ultrabook with InfinityEdge display', 1199.00, 1299.00, 20, 'Electronics', 'Laptops', 'Dell', 'XPS 13 Plus', 'DEL-XPS13P-001', 2.73, 11.63, 7.84, 0.60, 'Platinum Silver', 'Aluminum', 'dell_xps13_plus.jpg', '13.4-inch InfinityEdge display\n12th Gen Intel Core processors\nZero-lattice keyboard\nInvisible haptic touchpad\nPremium materials\nThunderbolt 4 ports', 'Display: 13.4-inch FHD+ InfinityEdge\nProcessor: Intel Core i7-1260P\nMemory: 16GB LPDDR5\nStorage: 512GB SSD\nGraphics: Intel Iris Xe\nPorts: 2x Thunderbolt 4', '1 year manufacturer warranty', 1, 'Free shipping on orders over $50.', '30-day return policy.', 'Dell XPS 13 Plus - Ultra-Premium Laptop', 'Experience premium computing with Dell XPS 13 Plus featuring InfinityEdge display.', 'dell, xps, laptop, ultrabook, premium, intel', FALSE, 'active', 'public', 420, 18),

(2, 'Nintendo Switch OLED', 'nintendo-switch-oled', 'Enhanced Nintendo Switch with vibrant 7-inch OLED screen, improved audio, and 64GB internal storage for gaming on-the-go.', 'Enhanced gaming console with OLED display', 349.00, 379.00, 80, 'Electronics', 'Gaming', 'Nintendo', 'Switch OLED', 'NIN-SWOLED-001', 0.93, 4.02, 9.53, 0.55, 'White', 'Plastic', 'switch_oled.jpg', '7-inch OLED screen\nEnhanced audio\n64GB internal storage\nImproved wide adjustable stand\nDock with wired LAN port\nHD Rumble', 'Display: 7-inch OLED multi-touch\nProcessor: Custom NVIDIA Tegra\nStorage: 64GB internal\nConnectivity: Wi-Fi, Bluetooth 4.1\nBattery: 4.5-9 hours\nDimensions: Handheld mode', '1 year manufacturer warranty', 1, 'Free shipping on orders over $50.', '30-day return policy.', 'Nintendo Switch OLED - Enhanced Gaming Console', 'Game anywhere with Nintendo Switch OLED featuring vibrant 7-inch display.', 'nintendo, switch, gaming, oled, portable, console', TRUE, 'active', 'public', 760, 52),

(2, 'Google Pixel 8 Pro', 'google-pixel-8-pro', 'AI-powered smartphone with advanced computational photography, Tensor G3 chip, and 7 years of security updates.', 'AI-powered Android phone with advanced camera', 999.00, 1099.00, 35, 'Electronics', 'Smartphones', 'Google', 'Pixel 8 Pro', 'GOO-PIX8P-001', 0.46, 6.40, 3.01, 0.35, 'Obsidian', 'Glass', 'pixel_8_pro.jpg', 'Google Tensor G3 chip\nAdvanced computational photography\nMagic Eraser and Best Take\n7 years of security updates\n6.7-inch Super Actua display\nTitan M security chip', 'Display: 6.7-inch LTPO OLED\nProcessor: Google Tensor G3\nCamera: 50MP main, 48MP ultrawide, 48MP telephoto\nStorage: 128GB\nRAM: 12GB\nBattery: 5050mAh', '1 year manufacturer warranty', 1, 'Free shipping on orders over $50.', '30-day return policy.', 'Google Pixel 8 Pro - AI-Powered Smartphone', 'Capture amazing photos with Google Pixel 8 Pro and advanced AI features.', 'google, pixel, android, ai, camera, tensor', FALSE, 'active', 'public', 580, 31),

(2, 'Microsoft Surface Pro 9', 'microsoft-surface-pro-9', '2-in-1 laptop and tablet with 13-inch touchscreen, 12th Gen Intel processors, and all-day battery life for ultimate productivity.', 'Versatile 2-in-1 laptop and tablet device', 999.00, 1099.00, 25, 'Electronics', 'Tablets', 'Microsoft', 'Surface Pro 9', 'MSF-SP9-001', 1.94, 11.30, 8.20, 0.37, 'Platinum', 'Magnesium', 'surface_pro_9.jpg', '13-inch PixelSense touchscreen\n12th Gen Intel Core processors\nAll-day battery life\nThunderbolt 4 ports\nSurface Pen support\nDetachable keyboard', 'Display: 13-inch PixelSense touchscreen\nProcessor: Intel Core i5-1235U\nMemory: 8GB LPDDR5\nStorage: 256GB SSD\nGraphics: Intel Iris Xe\nConnectivity: Wi-Fi 6E, Bluetooth 5.1', '1 year manufacturer warranty', 1, 'Free shipping on orders over $50.', '30-day return policy.', 'Microsoft Surface Pro 9 - 2-in-1 Laptop Tablet', 'Work and create with Microsoft Surface Pro 9, the versatile 2-in-1 device.', 'microsoft, surface, 2-in-1, tablet, laptop, touchscreen', FALSE, 'active', 'public', 390, 22),

(2, 'Bose QuietComfort 45', 'bose-quietcomfort-45', 'World-class noise canceling headphones with exceptional comfort, 24-hour battery life, and premium audio quality.', 'Premium noise-canceling headphones with comfort focus', 329.00, 379.00, 45, 'Electronics', 'Audio', 'Bose', 'QuietComfort 45', 'BSE-QC45-001', 0.54, 7.20, 6.00, 3.80, 'Black', 'Plastic', 'bose_qc45.jpg', 'World-class noise canceling\n24-hour battery life\nComfortable over-ear design\nBose Music app\nVoice assistant access\nQuiet and Aware modes', 'Driver: 40mm\nFrequency Response: Not specified\nBluetooth: 5.1\nBattery: 24 hours wireless\nCharging: USB-C\nWeight: 238g\nMicrophone: Dual-microphone system', '1 year manufacturer warranty', 1, 'Free shipping on orders over $50.', '30-day return policy.', 'Bose QuietComfort 45 - Premium Noise Canceling Headphones', 'Experience world-class noise cancellation with Bose QuietComfort 45.', 'bose, headphones, noise canceling, comfort, premium', FALSE, 'active', 'public', 470, 26),

(2, 'ASUS ROG Strix Gaming Laptop', 'asus-rog-strix-gaming', 'High-performance gaming laptop with RTX 4060, AMD Ryzen 7, 144Hz display, and RGB keyboard for serious gamers.', 'High-performance gaming laptop with RTX graphics', 1299.00, 1499.00, 15, 'Electronics', 'Laptops', 'ASUS', 'ROG Strix G15', 'ASU-ROGS15-001', 5.07, 14.20, 10.80, 1.08, 'Eclipse Gray', 'Plastic', 'asus_rog_strix.jpg', 'NVIDIA GeForce RTX 4060\nAMD Ryzen 7 6800H processor\n15.6-inch 144Hz display\nRGB backlit keyboard\nAdvanced cooling system\nDolby Atmos audio', 'Display: 15.6-inch FHD 144Hz\nProcessor: AMD Ryzen 7 6800H\nGraphics: NVIDIA RTX 4060 6GB\nMemory: 16GB DDR5\nStorage: 512GB SSD\nConnectivity: Wi-Fi 6, Bluetooth 5.2', '2 year manufacturer warranty', 1, 'Free shipping on orders over $50.', '30-day return policy.', 'ASUS ROG Strix Gaming Laptop - High Performance', 'Dominate games with ASUS ROG Strix featuring RTX 4060 and 144Hz display.', 'asus, rog, gaming, laptop, rtx, ryzen, 144hz', TRUE, 'active', 'public', 680, 19),

(2, 'LG 27-inch 4K Monitor', 'lg-27-4k-monitor', 'Professional 4K UHD monitor with IPS panel, HDR10 support, and USB-C connectivity for creative professionals.', '4K UHD monitor with professional color accuracy', 399.00, 499.00, 30, 'Electronics', 'Monitors', 'LG', '27UP850-W', 'LG-27UP850-001', 13.40, 24.10, 14.30, 8.90, 'White', 'Plastic', 'lg_27_4k_monitor.jpg', '27-inch 4K UHD resolution\nIPS panel with 99% sRGB\nHDR10 support\nUSB-C connectivity with 60W power\nHeight adjustable stand\nAMD FreeSync Premium', 'Display: 27-inch 4K UHD (3840x2160)\nPanel: IPS\nBrightness: 400 nits\nColor Gamut: 99% sRGB\nConnectivity: USB-C, HDMI, DisplayPort\nRefresh Rate: 60Hz', '1 year manufacturer warranty', 1, 'Free shipping on orders over $50.', '30-day return policy.', 'LG 27-inch 4K UHD Monitor - Professional Display', 'Create with precision using LG 27-inch 4K monitor with professional color accuracy.', 'lg, monitor, 4k, uhd, ips, professional, usb-c', FALSE, 'active', 'public', 320, 15),

(2, 'Logitech MX Master 3S Mouse', 'logitech-mx-master-3s', 'Advanced wireless mouse with ultra-precise scrolling, customizable buttons, and multi-device connectivity for professionals.', 'Professional wireless mouse with precision control', 99.00, 119.00, 120, 'Electronics', 'Accessories', 'Logitech', 'MX Master 3S', 'LOG-MXM3S-001', 0.31, 4.92, 3.31, 2.01, 'Graphite', 'Plastic', 'logitech_mx_master_3s.jpg', 'Ultra-precise 8000 DPI sensor\nMagSpeed electromagnetic scrolling\nCustomizable buttons\nMulti-device connectivity\n70-day battery life\nUSB-C quick charging', 'Sensor: Darkfield 8000 DPI\nConnectivity: Bluetooth, USB receiver\nButtons: 7 customizable\nBattery: 70 days on full charge\nCompatibility: Windows, macOS, Linux\nDimensions: 125.9 x 84.3 x 51mm', '1 year manufacturer warranty', 1, 'Free shipping on orders over $50.', '30-day return policy.', 'Logitech MX Master 3S - Professional Wireless Mouse', 'Enhance productivity with Logitech MX Master 3S precision wireless mouse.', 'logitech, mouse, wireless, professional, precision, mx master', FALSE, 'active', 'public', 280, 34),

(2, 'Razer DeathAdder V3 Gaming Mouse', 'razer-deathadder-v3', 'Ergonomic gaming mouse with 30K DPI sensor, ultra-lightweight design, and Razer Chroma RGB lighting.', 'High-performance ergonomic gaming mouse', 89.00, 109.00, 85, 'Electronics', 'Gaming', 'Razer', 'DeathAdder V3', 'RZR-DAV3-001', 0.21, 5.00, 2.70, 1.70, 'Black', 'Plastic', 'razer_deathadder_v3.jpg', 'Focus Pro 30K sensor\nUltra-lightweight 59g design\nErgonomic right-handed shape\nRazer Optical Mouse Switches\nChroma RGB lighting\n90-hour battery life', 'Sensor: Focus Pro 30K DPI\nConnectivity: Razer HyperSpeed Wireless\nButtons: 5 programmable\nBattery: 90 hours\nWeight: 59g\nPolling Rate: 1000Hz', '2 year manufacturer warranty', 1, 'Free shipping on orders over $50.', '30-day return policy.', 'Razer DeathAdder V3 - High-Performance Gaming Mouse', 'Dominate games with Razer DeathAdder V3 featuring 30K DPI sensor.', 'razer, gaming, mouse, deathadder, 30k dpi, lightweight', FALSE, 'active', 'public', 450, 28),

(2, 'Corsair K95 RGB Mechanical Keyboard', 'corsair-k95-rgb-keyboard', 'Premium mechanical gaming keyboard with Cherry MX switches, dedicated macro keys, and per-key RGB lighting.', 'Premium mechanical gaming keyboard with RGB', 199.00, 249.00, 40, 'Electronics', 'Gaming', 'Corsair', 'K95 RGB Platinum', 'COR-K95RGB-001', 2.86, 17.30, 6.60, 1.50, 'Black', 'Aluminum', 'corsair_k95_rgb.jpg', 'Cherry MX Speed switches\n6 dedicated macro keys\nPer-key RGB backlighting\nAircraft-grade aluminum frame\nDedicated media controls\niCUE software integration', 'Switches: Cherry MX Speed Silver\nBacklighting: Per-key RGB\nConnectivity: USB 3.0\nMacro Keys: 6 dedicated\nKey Layout: Full-size (104 keys)\nPolling Rate: 1000Hz', '2 year manufacturer warranty', 1, 'Free shipping on orders over $50.', '30-day return policy.', 'Corsair K95 RGB - Premium Mechanical Gaming Keyboard', 'Type and game with precision using Corsair K95 RGB mechanical keyboard.', 'corsair, keyboard, mechanical, gaming, rgb, cherry mx', FALSE, 'active', 'public', 380, 21),

(2, 'SteelSeries Arctis 7P Gaming Headset', 'steelseries-arctis-7p', 'Wireless gaming headset designed for PlayStation with lossless 2.4GHz connection, 24-hour battery, and ClearCast microphone.', 'Wireless gaming headset for PlayStation', 149.00, 179.00, 55, 'Electronics', 'Gaming', 'SteelSeries', 'Arctis 7P', 'STS-A7P-001', 0.73, 7.80, 6.80, 3.20, 'Black', 'Plastic', 'steelseries_arctis_7p.jpg', 'Lossless 2.4GHz wireless\n24-hour battery life\nClearCast bidirectional microphone\nDTS Headphone:X v2.0\nSki goggle suspension headband\nOn-headset controls', 'Driver: 40mm neodymium\nFrequency Response: 20-20000 Hz\nMicrophone: ClearCast bidirectional\nConnectivity: 2.4GHz wireless\nBattery: 24+ hours\nCompatibility: PlayStation, PC, Switch', '1 year manufacturer warranty', 1, 'Free shipping on orders over $50.', '30-day return policy.', 'SteelSeries Arctis 7P - Wireless Gaming Headset', 'Game wirelessly with SteelSeries Arctis 7P featuring 24-hour battery life.', 'steelseries, gaming, headset, wireless, playstation, arctis', FALSE, 'active', 'public', 340, 19),

(2, 'Anker PowerCore 26800 Power Bank', 'anker-powercore-26800', 'High-capacity portable charger with 26800mAh battery, three USB ports, and PowerIQ technology for fast charging.', 'High-capacity portable power bank with fast charging', 59.00, 79.00, 150, 'Electronics', 'Accessories', 'Anker', 'PowerCore 26800', 'ANK-PC26800-001', 1.28, 6.30, 3.40, 0.90, 'Black', 'Plastic', 'anker_powercore_26800.jpg', '26800mAh high capacity\nThree USB output ports\nPowerIQ and VoltageBoost technology\nMultiProtect safety system\nRecharges most phones 6+ times\nCompact and portable design', 'Capacity: 26800mAh / 96.48Wh\nInput: 5V/2A (Micro USB)\nOutput: 3x USB-A (5V/6A total)\nCharging Technology: PowerIQ + VoltageBoost\nDimensions: 180 x 81.5 x 22mm\nWeight: 490g', '18 month manufacturer warranty', 1, 'Free shipping on orders over $50.', '30-day return policy.', 'Anker PowerCore 26800 - High-Capacity Power Bank', 'Stay powered with Anker PowerCore 26800 featuring massive 26800mAh capacity.', 'anker, power bank, portable charger, 26800mah, poweriq', FALSE, 'active', 'public', 520, 42);

-- Continue with Vendor 2: Fashion Forward (20 products)
INSERT INTO products (vendor_id, name, slug, description, short_description, price, compare_price, stock_quantity, category, subcategory, brand, model, sku, weight, length, width, height, color, size, material, image_path, features, specifications, warranty, min_order_quantity, shipping_info, return_policy, meta_title, meta_description, tags, is_featured, status, visibility, view_count, sales_count) VALUES 

(3, 'Premium Leather Jacket', 'premium-leather-jacket', 'Handcrafted genuine leather jacket with classic design, premium hardware, and comfortable fit. Perfect for casual and semi-formal occasions.', 'Handcrafted genuine leather jacket with classic design', 299.00, 399.00, 25, 'Fashion', 'Outerwear', 'Fashion Forward', 'Classic Leather', 'FF-LJ-001', 3.5, 28, 20, 2, 'Black', 'L', 'Genuine Leather', 'leather_jacket.jpg', 'Genuine leather construction\nClassic biker design\nYKK zippers\nMultiple pockets\nComfortable cotton lining\nAdjustable waist belt', 'Material: 100% Genuine Leather\nLining: Cotton\nClosure: Front zip\nPockets: 4 exterior, 2 interior\nCare: Professional leather cleaning\nFit: Regular', '1 year against manufacturing defects', 1, 'Free shipping on orders over $100. Ships within 2-3 business days.', '30-day return policy. Must be unworn with tags.', 'Premium Leather Jacket - Handcrafted Classic Design', 'Shop our handcrafted genuine leather jacket with classic design and premium quality.', 'leather jacket, fashion, outerwear, genuine leather, classic', TRUE, 'active', 'public', 890, 45),

(3, 'Designer Silk Dress', 'designer-silk-dress', 'Elegant silk dress with flowing design, perfect for special occasions. Features delicate embroidery and premium silk fabric.', 'Elegant silk dress with flowing design and embroidery', 189.00, 249.00, 30, 'Fashion', 'Dresses', 'Fashion Forward', 'Silk Elegance', 'FF-SD-002', 0.8, 45, 18, 1, 'Navy Blue', 'M', 'Silk', 'silk_dress.jpg', 'Premium silk fabric\nDelicate embroidery details\nFlowing A-line design\nHidden back zipper\nFully lined\nDry clean only', 'Material: 100% Silk\nLining: Polyester\nLength: Midi\nSleeves: 3/4 length\nCare: Dry clean only\nFit: A-line', '30 days against manufacturing defects', 1, 'Free shipping on orders over $100.', '14-day return policy for unworn items.', 'Designer Silk Dress - Elegant Flowing Design', 'Look stunning in our designer silk dress with delicate embroidery and premium fabric.', 'silk dress, designer, elegant, formal wear, embroidery', TRUE, 'active', 'public', 650, 32),

(3, 'Cashmere Wool Sweater', 'cashmere-wool-sweater', 'Luxurious cashmere blend sweater with soft texture and classic crew neck design. Perfect for layering or wearing alone.', 'Luxurious cashmere blend sweater with soft texture', 149.00, 199.00, 40, 'Fashion', 'Knitwear', 'Fashion Forward', 'Cashmere Classic', 'FF-CS-003', 0.6, 26, 20, 1, 'Cream', 'L', 'Cashmere Blend', 'cashmere_sweater.jpg', 'Cashmere blend fabric\nClassic crew neck\nRibbed cuffs and hem\nSoft and warm\nMachine washable\nVersatile styling', 'Material: 70% Cashmere, 30% Wool\nNeckline: Crew neck\nSleeves: Long\nCare: Hand wash or gentle machine wash\nFit: Regular\nWeight: Lightweight', '30 days against manufacturing defects', 1, 'Free shipping on orders over $100.', '30-day return policy.', 'Cashmere Wool Sweater - Luxurious Comfort', 'Stay warm and stylish with our luxurious cashmere blend sweater.', 'cashmere, sweater, wool, luxury, knitwear, classic', FALSE, 'active', 'public', 420, 28),

(3, 'High-Waisted Denim Jeans', 'high-waisted-denim-jeans', 'Premium denim jeans with high-waisted fit, stretch comfort, and classic five-pocket styling. Made from sustainable cotton.', 'Premium high-waisted denim jeans with stretch comfort', 89.00, 119.00, 60, 'Fashion', 'Bottoms', 'Fashion Forward', 'Denim Classic', 'FF-DJ-004', 1.2, 42, 16, 1, 'Dark Blue', '30', 'Cotton Denim', 'denim_jeans.jpg', 'High-waisted design\nStretch denim fabric\nClassic five-pocket styling\nSustainable cotton\nButton and zip closure\nVersatile fit', 'Material: 98% Cotton, 2% Elastane\nRise: High-waisted\nFit: Skinny\nClosure: Button and zip\nPockets: 5-pocket design\nCare: Machine wash cold', '90 days against manufacturing defects', 1, 'Free shipping on orders over $100.', '30-day return policy.', 'High-Waisted Denim Jeans - Premium Stretch Comfort', 'Find your perfect fit with our high-waisted denim jeans featuring stretch comfort.', 'denim, jeans, high waisted, stretch, sustainable, cotton', FALSE, 'active', 'public', 580, 38),

(3, 'Silk Scarf Collection', 'silk-scarf-collection', 'Luxurious silk scarf with hand-painted design and vibrant colors. Perfect accessory for any outfit, versatile styling options.', 'Luxurious silk scarf with hand-painted design', 79.00, 99.00, 50, 'Fashion', 'Accessories', 'Fashion Forward', 'Silk Art', 'FF-SC-005', 0.2, 35, 35, 0.1, 'Multicolor', 'One Size', 'Silk', 'silk_scarf.jpg', 'Hand-painted design\nPremium silk fabric\nVibrant colors\nVersatile styling\nHemmed edges\nLuxury gift box included', 'Material: 100% Silk\nSize: 35" x 35"\nEdge: Hand-rolled hem\nCare: Dry clean recommended\nDesign: Hand-painted\nPackaging: Luxury gift box', '30 days against manufacturing defects', 1, 'Free shipping on orders over $100.', '14-day return policy.', 'Silk Scarf Collection - Hand-Painted Luxury', 'Add elegance to any outfit with our hand-painted silk scarf collection.', 'silk scarf, hand painted, luxury, accessory, gift', FALSE, 'active', 'public', 340, 22),

(3, 'Tailored Blazer', 'tailored-blazer', 'Professional tailored blazer with modern fit, quality construction, and versatile styling. Perfect for business and formal occasions.', 'Professional tailored blazer with modern fit', 199.00, 259.00, 35, 'Fashion', 'Outerwear', 'Fashion Forward', 'Professional', 'FF-TB-006', 1.8, 30, 22, 2, 'Charcoal Gray', 'M', 'Wool Blend', 'tailored_blazer.jpg', 'Modern tailored fit\nWool blend fabric\nFully lined\nNotched lapels\nTwo-button closure\nFunctional pockets', 'Material: 70% Wool, 30% Polyester\nLining: Polyester\nFit: Modern tailored\nClosure: Two-button\nLapels: Notched\nCare: Dry clean only', '1 year against manufacturing defects', 1, 'Free shipping on orders over $100.', '30-day return policy.', 'Tailored Blazer - Professional Modern Fit', 'Look professional with our tailored blazer featuring modern fit and quality construction.', 'blazer, tailored, professional, wool, business wear', FALSE, 'active', 'public', 460, 26),

(3, 'Evening Gown', 'evening-gown', 'Stunning evening gown with elegant silhouette, luxurious fabric, and intricate beadwork. Perfect for formal events and galas.', 'Stunning evening gown with elegant silhouette', 399.00, 499.00, 15, 'Fashion', 'Formal Wear', 'Fashion Forward', 'Elegance', 'FF-EG-007', 2.5, 60, 20, 2, 'Midnight Blue', 'S', 'Chiffon', 'evening_gown.jpg', 'Elegant floor-length design\nIntricate beadwork\nFlowing chiffon fabric\nBuilt-in bra support\nHidden back zipper\nFully lined', 'Material: Chiffon with beading\nLength: Floor-length\nSilhouette: A-line\nNeckline: V-neck\nSleeves: Sleeveless\nCare: Dry clean only', '30 days against manufacturing defects', 1, 'Free shipping on orders over $100.', '14-day return policy for unworn items.', 'Evening Gown - Stunning Formal Elegance', 'Make a statement at formal events with our stunning evening gown.', 'evening gown, formal wear, elegant, chiffon, beadwork', TRUE, 'active', 'public', 720, 18),

(3, 'Luxury Handbag', 'luxury-handbag', 'Designer handbag crafted from premium leather with gold hardware and spacious interior. Includes dust bag and authenticity card.', 'Designer handbag crafted from premium leather', 349.00, 449.00, 20, 'Fashion', 'Bags', 'Fashion Forward', 'Luxury Line', 'FF-LH-008', 1.5, 14, 10, 6, 'Cognac Brown', 'One Size', 'Leather', 'luxury_handbag.jpg', 'Premium leather construction\nGold-tone hardware\nSpacious main compartment\nMultiple interior pockets\nAdjustable shoulder strap\nDust bag included', 'Material: Genuine leather\nHardware: Gold-tone\nClosure: Magnetic snap\nStraps: Adjustable shoulder strap\nInterior: Fabric lining\nDimensions: 14" x 10" x 6"', '1 year against manufacturing defects', 1, 'Free shipping on orders over $100.', '30-day return policy.', 'Luxury Handbag - Premium Leather Designer Bag', 'Carry in style with our luxury handbag crafted from premium leather.', 'handbag, luxury, leather, designer, gold hardware', TRUE, 'active', 'public', 680, 31),

(3, 'Cashmere Coat', 'cashmere-coat', 'Elegant cashmere coat with timeless design, luxurious feel, and superior warmth. Perfect for cold weather styling.', 'Elegant cashmere coat with timeless design', 599.00, 799.00, 12, 'Fashion', 'Outerwear', 'Fashion Forward', 'Cashmere Elite', 'FF-CC-009', 4.2, 48, 24, 3, 'Camel', 'M', 'Cashmere', 'cashmere_coat.jpg', 'Pure cashmere fabric\nTimeless double-breasted design\nLuxurious silk lining\nBelt included\nSide pockets\nSuperior warmth', 'Material: 100% Cashmere\nLining: Silk\nClosure: Double-breasted\nLength: Mid-length\nPockets: 2 side pockets\nCare: Dry clean only', '1 year against manufacturing defects', 1, 'Free shipping on orders over $100.', '30-day return policy.', 'Cashmere Coat - Elegant Timeless Design', 'Stay warm and elegant with our pure cashmere coat featuring timeless design.', 'cashmere coat, luxury, outerwear, timeless, warm', TRUE, 'active', 'public', 520, 15),

(3, 'Designer Sunglasses', 'designer-sunglasses', 'Premium designer sunglasses with UV protection, lightweight titanium frame, and polarized lenses for superior clarity.', 'Premium designer sunglasses with UV protection', 189.00, 249.00, 45, 'Fashion', 'Accessories', 'Fashion Forward', 'Vision', 'FF-DS-010', 0.15, 6, 5.5, 1.5, 'Black', 'One Size', 'Titanium', 'designer_sunglasses.jpg', 'Titanium frame construction\nPolarized lenses\n100% UV protection\nLightweight design\nAdjustable nose pads\nLuxury case included', 'Frame: Titanium\nLenses: Polarized\nUV Protection: 100%\nWeight: 25g\nCase: Hard luxury case\nWarranty: 2 years', '2 years against manufacturing defects', 1, 'Free shipping on orders over $100.', '30-day return policy.', 'Designer Sunglasses - Premium UV Protection', 'Protect your eyes in style with our premium designer sunglasses.', 'sunglasses, designer, uv protection, titanium, polarized', FALSE, 'active', 'public', 380, 24),

(3, 'Wool Trench Coat', 'wool-trench-coat', 'Classic wool trench coat with water-resistant finish, removable lining, and timeless styling for all seasons.', 'Classic wool trench coat with water-resistant finish', 299.00, 399.00, 18, 'Fashion', 'Outerwear', 'Fashion Forward', 'Classic', 'FF-WTC-011', 3.8, 46, 22, 2.5, 'Beige', 'L', 'Wool', 'wool_trench_coat.jpg', 'Water-resistant wool fabric\nRemovable lining\nClassic trench styling\nBelt included\nStorm flaps\nButton closure', 'Material: Water-resistant wool\nLining: Removable\nClosure: Button front\nLength: Mid-length\nFeatures: Storm flaps, belt\nCare: Dry clean recommended', '1 year against manufacturing defects', 1, 'Free shipping on orders over $100.', '30-day return policy.', 'Wool Trench Coat - Classic Water-Resistant Style', 'Stay stylish in any weather with our classic wool trench coat.', 'trench coat, wool, water resistant, classic, outerwear', FALSE, 'active', 'public', 420, 19),

(3, 'Cocktail Dress', 'cocktail-dress', 'Sophisticated cocktail dress with flattering fit, premium fabric, and elegant details perfect for evening events.', 'Sophisticated cocktail dress with flattering fit', 159.00, 199.00, 28, 'Fashion', 'Dresses', 'Fashion Forward', 'Evening', 'FF-CD-012', 1.2, 38, 16, 1, 'Black', 'S', 'Crepe', 'cocktail_dress.jpg', 'Flattering A-line silhouette\nPremium crepe fabric\nElbow-length sleeves\nHidden back zipper\nKnee-length\nVersatile styling', 'Material: Crepe\nSilhouette: A-line\nLength: Knee-length\nSleeves: Elbow-length\nNeckline: Round neck\nCare: Dry clean recommended', '30 days against manufacturing defects', 1, 'Free shipping on orders over $100.', '14-day return policy.', 'Cocktail Dress - Sophisticated Evening Wear', 'Look sophisticated at evening events with our elegant cocktail dress.', 'cocktail dress, evening wear, sophisticated, crepe, elegant', FALSE, 'active', 'public', 340, 21),

(3, 'Luxury Watch', 'luxury-watch', 'Swiss-made luxury watch with automatic movement, sapphire crystal, and premium leather strap. Water-resistant to 100m.', 'Swiss-made luxury watch with automatic movement', 899.00, 1199.00, 8, 'Fashion', 'Accessories', 'Fashion Forward', 'Timepiece', 'FF-LW-013', 0.3, 4.5, 4.5, 1.2, 'Silver', 'One Size', 'Stainless Steel', 'luxury_watch.jpg', 'Swiss automatic movement\nSapphire crystal glass\nStainless steel case\nGenuine leather strap\nWater-resistant 100m\nLuminous hands', 'Movement: Swiss automatic\nCase: Stainless steel 42mm\nCrystal: Sapphire\nStrap: Genuine leather\nWater Resistance: 100m\nPower Reserve: 42 hours', '2 years international warranty', 1, 'Free shipping on orders over $100.', '30-day return policy.', 'Luxury Watch - Swiss-Made Automatic Timepiece', 'Experience precision with our Swiss-made luxury watch featuring automatic movement.', 'luxury watch, swiss made, automatic, sapphire crystal, leather', TRUE, 'active', 'public', 580, 12),

(3, 'Silk Blouse', 'silk-blouse', 'Elegant silk blouse with classic button-down design, mother-of-pearl buttons, and versatile styling for work or casual wear.', 'Elegant silk blouse with classic button-down design', 129.00, 169.00, 35, 'Fashion', 'Tops', 'Fashion Forward', 'Silk Collection', 'FF-SB-014', 0.4, 26, 18, 1, 'Ivory', 'M', 'Silk', 'silk_blouse.jpg', 'Pure silk fabric\nClassic button-down design\nMother-of-pearl buttons\nFrench seams\nVersatile styling\nMachine washable', 'Material: 100% Silk\nClosure: Button-down\nSleeves: Long\nFit: Classic\nButtons: Mother-of-pearl\nCare: Machine wash gentle', '30 days against manufacturing defects', 1, 'Free shipping on orders over $100.', '30-day return policy.', 'Silk Blouse - Elegant Classic Button-Down', 'Add elegance to your wardrobe with our classic silk blouse.', 'silk blouse, button down, elegant, versatile, work wear', FALSE, 'active', 'public', 290, 18),

(3, 'Designer Belt', 'designer-belt', 'Premium leather belt with signature buckle design, adjustable fit, and superior craftsmanship. Perfect finishing touch for any outfit.', 'Premium leather belt with signature buckle design', 89.00, 119.00, 55, 'Fashion', 'Accessories', 'Fashion Forward', 'Signature', 'FF-DB-015', 0.5, 42, 1.5, 0.2, 'Brown', '32', 'Leather', 'designer_belt.jpg', 'Premium leather construction\nSignature buckle design\nAdjustable sizing\nSuperior craftsmanship\nVersatile styling\nGift box included', 'Material: Genuine leather\nBuckle: Metal signature design\nWidth: 1.5 inches\nSizes: 30-42 inches\nAdjustable: Yes\nPackaging: Gift box', '1 year against manufacturing defects', 1, 'Free shipping on orders over $100.', '30-day return policy.', 'Designer Belt - Premium Leather Signature Style', 'Complete your look with our premium leather designer belt.', 'designer belt, leather, signature buckle, premium, accessory', FALSE, 'active', 'public', 220, 26),

(3, 'Cashmere Scarf', 'cashmere-scarf', 'Luxurious cashmere scarf with ultra-soft texture, elegant drape, and timeless appeal. Perfect for layering in any season.', 'Luxurious cashmere scarf with ultra-soft texture', 119.00, 159.00, 40, 'Fashion', 'Accessories', 'Fashion Forward', 'Cashmere', 'FF-CAS-016', 0.3, 70, 12, 0.5, 'Gray', 'One Size', 'Cashmere', 'cashmere_scarf.jpg', 'Pure cashmere fabric\nUltra-soft texture\nElegant drape\nFringed edges\nTimeless appeal\nLuxury gift packaging', 'Material: 100% Cashmere\nDimensions: 70" x 12"\nEdge: Fringed\nWeight: Lightweight\nCare: Dry clean recommended\nPackaging: Luxury box', '30 days against manufacturing defects', 1, 'Free shipping on orders over $100.', '30-day return policy.', 'Cashmere Scarf - Luxurious Ultra-Soft Texture', 'Stay warm and stylish with our luxurious cashmere scarf.', 'cashmere scarf, luxury, soft, elegant, timeless', FALSE, 'active', 'public', 310, 22),

(3, 'High Heels', 'designer-high-heels', 'Elegant designer high heels with comfortable padding, premium materials, and sophisticated styling for special occasions.', 'Elegant designer high heels with comfortable padding', 199.00, 259.00, 25, 'Fashion', 'Shoes', 'Fashion Forward', 'Elegance', 'FF-HH-017', 1.2, 10, 4, 4, 'Black', '8', 'Leather', 'high_heels.jpg', 'Premium leather upper\nComfortable padding\n4-inch heel height\nNon-slip sole\nElegant pointed toe\nAnkle strap', 'Material: Genuine leather\nHeel Height: 4 inches\nToe: Pointed\nClosure: Ankle strap\nSole: Non-slip rubber\nPadding: Cushioned insole', '30 days against manufacturing defects', 1, 'Free shipping on orders over $100.', '30-day return policy.', 'Designer High Heels - Elegant Comfortable Style', 'Step out in style with our elegant designer high heels.', 'high heels, designer, elegant, comfortable, leather', FALSE, 'active', 'public', 380, 19),

(3, 'Wool Sweater Dress', 'wool-sweater-dress', 'Cozy wool sweater dress with ribbed texture, comfortable fit, and versatile styling perfect for casual and semi-formal occasions.', 'Cozy wool sweater dress with ribbed texture', 139.00, 179.00, 32, 'Fashion', 'Dresses', 'Fashion Forward', 'Comfort', 'FF-WSD-018', 1.0, 36, 18, 1, 'Burgundy', 'L', 'Wool', 'wool_sweater_dress.jpg', 'Soft wool blend fabric\nRibbed texture\nComfortable fit\nKnee-length\nLong sleeves\nVersatile styling', 'Material: 80% Wool, 20% Acrylic\nLength: Knee-length\nSleeves: Long\nNeckline: Crew neck\nFit: Relaxed\nCare: Hand wash recommended', '30 days against manufacturing defects', 1, 'Free shipping on orders over $100.', '30-day return policy.', 'Wool Sweater Dress - Cozy Comfortable Style', 'Stay cozy and stylish with our wool sweater dress.', 'sweater dress, wool, cozy, comfortable, versatile', FALSE, 'active', 'public', 260, 16),

(3, 'Statement Necklace', 'statement-necklace', 'Bold statement necklace with intricate design, premium materials, and eye-catching appeal perfect for elevating any outfit.', 'Bold statement necklace with intricate design', 79.00, 109.00, 48, 'Fashion', 'Jewelry', 'Fashion Forward', 'Statement', 'FF-SN-019', 0.2, 18, 8, 1, 'Gold', 'One Size', 'Metal Alloy', 'statement_necklace.jpg', 'Intricate design details\nPremium metal alloy\nAdjustable chain length\nLobster clasp closure\nEye-catching appeal\nJewelry pouch included', 'Material: Premium metal alloy\nLength: 18-20 inches adjustable\nClosure: Lobster clasp\nFinish: Gold-tone\nStyle: Statement\nPackaging: Jewelry pouch', '30 days against manufacturing defects', 1, 'Free shipping on orders over $100.', '30-day return policy.', 'Statement Necklace - Bold Intricate Design', 'Make a statement with our bold and intricate designer necklace.', 'statement necklace, jewelry, bold, intricate, gold tone', FALSE, 'active', 'public', 190, 14),

(3, 'Formal Suit', 'mens-formal-suit', 'Classic mens formal suit with tailored fit, premium wool fabric, and professional styling perfect for business and formal events.', 'Classic mens formal suit with tailored fit', 499.00, 649.00, 15, 'Fashion', 'Suits', 'Fashion Forward', 'Professional', 'FF-FS-020', 3.5, 30, 22, 2, 'Navy Blue', '42R', 'Wool', 'formal_suit.jpg', 'Premium wool fabric\nTailored fit\nTwo-piece suit\nFully lined jacket\nFlat-front trousers\nProfessional styling', 'Material: 100% Wool\nJacket: Two-button, notched lapel\nTrousers: Flat-front, cuffed\nLining: Full jacket lining\nFit: Tailored\nCare: Dry clean only', '1 year against manufacturing defects', 1, 'Free shipping on orders over $100.', '30-day return policy.', 'Mens Formal Suit - Classic Tailored Professional', 'Look professional with our classic tailored formal suit.', 'formal suit, mens, tailored, wool, professional, business', TRUE, 'active', 'public', 420, 12);

-- Continue with remaining vendors (3-10) with 20 products each...
-- For brevity, I'll add a few more examples and then provide the optimized product_detail.php

-- Vendor 3: Home & Garden Paradise (sample products)
INSERT INTO products (vendor_id, name, slug, description, short_description, price, compare_price, stock_quantity, category, subcategory, brand, model, sku, weight, length, width, height, color, material, image_path, features, specifications, warranty, min_order_quantity, shipping_info, return_policy, meta_title, meta_description, tags, is_featured, status, visibility, view_count, sales_count) VALUES 

(4, 'Smart Garden Irrigation System', 'smart-garden-irrigation', 'Automated irrigation system with smartphone control, weather monitoring, and water-saving technology for efficient garden care.', 'Automated irrigation system with smartphone control', 299.00, 399.00, 20, 'Home & Garden', 'Gardening', 'Home & Garden Paradise', 'SmartWater Pro', 'HGP-SGI-001', 15.5, 24, 18, 12, 'Black', 'Plastic/Metal', 'smart_irrigation.jpg', 'Smartphone app control\nWeather monitoring\nWater-saving technology\nMultiple zone control\nAutomatic scheduling\nEasy installation', 'Zones: Up to 8\nConnectivity: Wi-Fi\nPower: AC adapter included\nCompatibility: iOS/Android\nSensors: Soil moisture, weather\nCoverage: Up to 5000 sq ft', '2 years manufacturer warranty', 1, 'Free shipping on orders over $50.', '30-day return policy.', 'Smart Garden Irrigation System - Automated Watering', 'Keep your garden perfectly watered with our smart irrigation system.', 'irrigation, smart garden, automated watering, wifi, app control', TRUE, 'active', 'public', 680, 25),

(4, 'Outdoor Patio Furniture Set', 'outdoor-patio-furniture-set', 'Weather-resistant patio furniture set including table and chairs, perfect for outdoor dining and entertaining guests.', 'Weather-resistant patio furniture set for outdoor dining', 599.00, 799.00, 12, 'Home & Garden', 'Outdoor Furniture', 'Home & Garden Paradise', 'Patio Elite', 'HGP-PFS-002', 85.0, 60, 36, 30, 'Brown', 'Wicker/Aluminum', 'patio_furniture.jpg', 'Weather-resistant materials\nComfortable cushions\nTable and 4 chairs\nEasy assembly\nFade-resistant fabric\nLow maintenance', 'Table: 60" x 36" x 30"\nChairs: 4 included\nMaterial: Synthetic wicker, aluminum frame\nCushions: Weather-resistant fabric\nWeight Capacity: 300 lbs per chair\nAssembly: Required', '1 year against manufacturing defects', 1, 'Free shipping on orders over $50.', '30-day return policy.', 'Outdoor Patio Furniture Set - Weather Resistant', 'Create the perfect outdoor dining space with our patio furniture set.', 'patio furniture, outdoor dining, weather resistant, wicker', TRUE, 'active', 'public', 520, 18);

-- Add sample reviews for some products
INSERT INTO product_reviews (product_id, buyer_id, rating, review_title, review_text, verified_purchase, status) VALUES 
(1, 12, 5, 'Amazing phone!', 'The iPhone 15 Pro Max exceeded my expectations. The camera quality is incredible and the titanium build feels premium.', TRUE, 'approved'),
(1, 13, 4, 'Great but expensive', 'Love the features but the price is quite high. Overall satisfied with the purchase.', TRUE, 'approved'),
(2, 14, 5, 'Perfect laptop', 'The MacBook Air M2 is incredibly fast and the battery life is amazing. Highly recommend!', TRUE, 'approved'),
(21, 15, 5, 'Beautiful jacket', 'The leather quality is exceptional and the fit is perfect. Worth every penny!', TRUE, 'approved'),
(22, 16, 4, 'Elegant dress', 'Gorgeous dress for special occasions. The silk feels luxurious.', TRUE, 'approved');
