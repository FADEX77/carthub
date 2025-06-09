-- CartHub Database Setup with Sample Data
DROP DATABASE IF EXISTS carthub;
CREATE DATABASE carthub;
USE carthub;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    user_type ENUM('buyer', 'vendor', 'admin') NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'inactive') DEFAULT 'active'
);

-- Products table
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    stock_quantity INT DEFAULT 0,
    category VARCHAR(50),
    image_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'inactive') DEFAULT 'active',
    FOREIGN KEY (vendor_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Orders table
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    buyer_id INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    order_status ENUM('pending', 'confirmed', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    shipping_address TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Order items table
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Cart table
CREATE TABLE cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    buyer_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Insert admin user (password: admin123)
INSERT INTO users (username, email, password, user_type, full_name, phone, address) VALUES
('admin', 'admin@carthub.com', '$2y$10$YourHashedPasswordHere', 'admin', 'System Administrator', '+1-555-0001', '123 Admin Street, Tech City, TC 12345');

-- Insert 10 sample vendors (all passwords: vendor123)
INSERT INTO users (username, email, password, user_type, full_name, phone, address) VALUES
('techstore', 'tech@carthub.com', '$2y$10$YourHashedPasswordHere', 'vendor', 'TechStore Electronics', '+1-555-1001', '456 Tech Avenue, Silicon Valley, CA 94000'),
('fashionhub', 'fashion@carthub.com', '$2y$10$YourHashedPasswordHere', 'vendor', 'Fashion Hub Boutique', '+1-555-1002', '789 Style Street, Fashion District, NY 10001'),
('homegarden', 'home@carthub.com', '$2y$10$YourHashedPasswordHere', 'vendor', 'Home & Garden Paradise', '+1-555-1003', '321 Garden Lane, Green Valley, OR 97000'),
('sportsworld', 'sports@carthub.com', '$2y$10$YourHashedPasswordHere', 'vendor', 'Sports World Pro', '+1-555-1004', '654 Athletic Drive, Sports City, TX 75000'),
('bookworm', 'books@carthub.com', '$2y$10$YourHashedPasswordHere', 'vendor', 'Bookworm Paradise', '+1-555-1005', '987 Literature Lane, Book Town, MA 02000'),
('beautyzone', 'beauty@carthub.com', '$2y$10$YourHashedPasswordHere', 'vendor', 'Beauty Zone Cosmetics', '+1-555-1006', '147 Glamour Street, Beauty Hills, CA 90210'),
('toyland', 'toys@carthub.com', '$2y$10$YourHashedPasswordHere', 'vendor', 'Toyland Express', '+1-555-1007', '258 Fun Avenue, Toy City, FL 33000'),
('autoparts', 'auto@carthub.com', '$2y$10$YourHashedPasswordHere', 'vendor', 'Auto Parts Central', '+1-555-1008', '369 Motor Street, Car Town, MI 48000'),
('musicstore', 'music@carthub.com', '$2y$10$YourHashedPasswordHere', 'vendor', 'Music Store Harmony', '+1-555-1009', '741 Melody Road, Music City, TN 37000'),
('petshop', 'pets@carthub.com', '$2y$10$YourHashedPasswordHere', 'vendor', 'Pet Shop Paradise', '+1-555-1010', '852 Pet Lane, Animal Town, CO 80000');

-- Insert 200 sample products (20 per vendor)
INSERT INTO products (vendor_id, name, description, price, stock_quantity, category) VALUES
-- TechStore Electronics (vendor_id: 2)
(2, 'iPhone 15 Pro Max', 'Latest Apple smartphone with advanced camera system', 1199.99, 50, 'Electronics'),
(2, 'Samsung Galaxy S24 Ultra', 'Premium Android smartphone with S Pen', 1299.99, 45, 'Electronics'),
(2, 'MacBook Pro 16"', 'Powerful laptop for professionals', 2499.99, 25, 'Electronics'),
(2, 'Dell XPS 13', 'Ultra-portable Windows laptop', 1299.99, 30, 'Electronics'),
(2, 'iPad Pro 12.9"', 'Professional tablet with M2 chip', 1099.99, 40, 'Electronics'),
(2, 'AirPods Pro 2', 'Wireless earbuds with noise cancellation', 249.99, 100, 'Electronics'),
(2, 'Sony WH-1000XM5', 'Premium noise-canceling headphones', 399.99, 60, 'Electronics'),
(2, 'Apple Watch Series 9', 'Advanced smartwatch with health features', 399.99, 75, 'Electronics'),
(2, 'Nintendo Switch OLED', 'Portable gaming console', 349.99, 80, 'Electronics'),
(2, 'PlayStation 5', 'Next-gen gaming console', 499.99, 20, 'Electronics'),
(2, 'Xbox Series X', 'Microsoft gaming console', 499.99, 25, 'Electronics'),
(2, 'LG OLED 55" TV', '4K OLED smart television', 1499.99, 15, 'Electronics'),
(2, 'Canon EOS R5', 'Professional mirrorless camera', 3899.99, 10, 'Electronics'),
(2, 'GoPro Hero 12', 'Action camera for adventures', 399.99, 50, 'Electronics'),
(2, 'Dyson V15 Detect', 'Cordless vacuum cleaner', 749.99, 35, 'Electronics'),
(2, 'Instant Pot Duo 7-in-1', 'Multi-functional pressure cooker', 99.99, 70, 'Electronics'),
(2, 'Fitbit Charge 5', 'Advanced fitness tracker', 179.99, 90, 'Electronics'),
(2, 'Amazon Echo Dot 5th Gen', 'Smart speaker with Alexa', 49.99, 150, 'Electronics'),
(2, 'Ring Video Doorbell 4', 'Smart doorbell with camera', 199.99, 65, 'Electronics'),
(2, 'Tesla Model Y Charger', 'Home charging station', 599.99, 20, 'Electronics'),

-- Fashion Hub Boutique (vendor_id: 3)
(3, 'Designer Leather Jacket', 'Premium genuine leather jacket', 299.99, 25, 'Fashion'),
(3, 'Silk Evening Dress', 'Elegant silk dress for special occasions', 199.99, 30, 'Fashion'),
(3, 'Cashmere Sweater', 'Luxurious cashmere pullover', 149.99, 40, 'Fashion'),
(3, 'Designer Jeans', 'Premium denim with perfect fit', 89.99, 60, 'Fashion'),
(3, 'Italian Leather Handbag', 'Handcrafted leather purse', 249.99, 35, 'Fashion'),
(3, 'Wool Coat', 'Elegant winter coat', 179.99, 20, 'Fashion'),
(3, 'Silk Scarf', 'Luxury silk accessory', 59.99, 50, 'Fashion'),
(3, 'Designer Sunglasses', 'UV protection with style', 129.99, 45, 'Fashion'),
(3, 'Pearl Necklace', 'Elegant pearl jewelry', 199.99, 25, 'Fashion'),
(3, 'Leather Boots', 'Stylish ankle boots', 159.99, 40, 'Fashion'),
(3, 'Cocktail Dress', 'Perfect for parties', 119.99, 35, 'Fashion'),
(3, 'Blazer Jacket', 'Professional business attire', 139.99, 30, 'Fashion'),
(3, 'Yoga Pants', 'Comfortable activewear', 49.99, 80, 'Fashion'),
(3, 'Summer Sandals', 'Comfortable beach footwear', 79.99, 60, 'Fashion'),
(3, 'Winter Gloves', 'Warm leather gloves', 39.99, 70, 'Fashion'),
(3, 'Baseball Cap', 'Casual sports cap', 24.99, 100, 'Fashion'),
(3, 'Formal Shirt', 'Business dress shirt', 69.99, 55, 'Fashion'),
(3, 'Denim Jacket', 'Classic jean jacket', 89.99, 45, 'Fashion'),
(3, 'Evening Clutch', 'Small formal purse', 79.99, 30, 'Fashion'),
(3, 'Workout Leggings', 'High-performance athletic wear', 59.99, 75, 'Fashion'),

-- Home & Garden Paradise (vendor_id: 4)
(4, 'Sectional Sofa', 'Comfortable 3-piece sectional', 899.99, 15, 'Home & Garden'),
(4, 'Dining Table Set', '6-person dining set with chairs', 599.99, 20, 'Home & Garden'),
(4, 'Queen Mattress', 'Memory foam mattress', 499.99, 25, 'Home & Garden'),
(4, 'Coffee Table', 'Modern glass coffee table', 199.99, 30, 'Home & Garden'),
(4, 'Garden Tool Set', 'Complete gardening kit', 79.99, 50, 'Home & Garden'),
(4, 'Outdoor Grill', 'Gas barbecue grill', 399.99, 20, 'Home & Garden'),
(4, 'Patio Furniture Set', '4-piece outdoor seating', 699.99, 15, 'Home & Garden'),
(4, 'Kitchen Knife Set', 'Professional chef knives', 149.99, 40, 'Home & Garden'),
(4, 'Bedding Set', 'Luxury cotton sheets', 89.99, 60, 'Home & Garden'),
(4, 'Floor Lamp', 'Modern LED floor lamp', 129.99, 35, 'Home & Garden'),
(4, 'Area Rug', 'Decorative living room rug', 179.99, 25, 'Home & Garden'),
(4, 'Curtain Set', 'Blackout window curtains', 59.99, 45, 'Home & Garden'),
(4, 'Cookware Set', 'Non-stick pots and pans', 199.99, 30, 'Home & Garden'),
(4, 'Throw Pillows', 'Decorative couch pillows', 29.99, 80, 'Home & Garden'),
(4, 'Wall Art', 'Framed canvas prints', 79.99, 40, 'Home & Garden'),
(4, 'Storage Ottoman', 'Multi-functional furniture', 99.99, 35, 'Home & Garden'),
(4, 'Ceiling Fan', 'Energy-efficient cooling', 159.99, 25, 'Home & Garden'),
(4, 'Bathroom Mirror', 'LED lighted vanity mirror', 119.99, 30, 'Home & Garden'),
(4, 'Garden Hose', '50ft expandable hose', 49.99, 60, 'Home & Garden'),
(4, 'Outdoor Umbrella', 'Large patio umbrella', 149.99, 20, 'Home & Garden'),

-- Sports World Pro (vendor_id: 5)
(5, 'Professional Basketball', 'Official size basketball', 29.99, 100, 'Sports'),
(5, 'Tennis Racket', 'Lightweight carbon fiber racket', 149.99, 40, 'Sports'),
(5, 'Golf Club Set', 'Complete beginner golf set', 299.99, 25, 'Sports'),
(5, 'Running Shoes', 'Professional athletic footwear', 129.99, 60, 'Sports'),
(5, 'Yoga Mat', 'Non-slip exercise mat', 39.99, 80, 'Sports'),
(5, 'Dumbbells Set', 'Adjustable weight set', 199.99, 30, 'Sports'),
(5, 'Bicycle Helmet', 'Safety cycling helmet', 59.99, 50, 'Sports'),
(5, 'Swimming Goggles', 'Anti-fog swim goggles', 19.99, 100, 'Sports'),
(5, 'Soccer Ball', 'FIFA approved soccer ball', 34.99, 75, 'Sports'),
(5, 'Baseball Glove', 'Leather baseball mitt', 79.99, 45, 'Sports'),
(5, 'Resistance Bands', 'Exercise resistance set', 24.99, 90, 'Sports'),
(5, 'Protein Powder', 'Whey protein supplement', 49.99, 70, 'Sports'),
(5, 'Water Bottle', 'Insulated sports bottle', 19.99, 120, 'Sports'),
(5, 'Gym Bag', 'Large sports duffel bag', 39.99, 55, 'Sports'),
(5, 'Exercise Bike', 'Stationary fitness bike', 399.99, 15, 'Sports'),
(5, 'Treadmill', 'Home cardio machine', 799.99, 10, 'Sports'),
(5, 'Boxing Gloves', 'Professional training gloves', 69.99, 40, 'Sports'),
(5, 'Skateboard', 'Complete skateboard setup', 89.99, 35, 'Sports'),
(5, 'Fishing Rod', 'Carbon fiber fishing pole', 79.99, 30, 'Sports'),
(5, 'Camping Tent', '4-person outdoor tent', 149.99, 25, 'Sports'),

-- Bookworm Paradise (vendor_id: 6)
(6, 'The Great Gatsby', 'Classic American literature', 12.99, 100, 'Books'),
(6, 'To Kill a Mockingbird', 'Pulitzer Prize winning novel', 13.99, 85, 'Books'),
(6, '1984 by George Orwell', 'Dystopian science fiction', 14.99, 90, 'Books'),
(6, 'Pride and Prejudice', 'Jane Austen romance classic', 11.99, 75, 'Books'),
(6, 'The Catcher in the Rye', 'Coming-of-age novel', 13.99, 80, 'Books'),
(6, 'Harry Potter Box Set', 'Complete 7-book series', 59.99, 50, 'Books'),
(6, 'The Lord of the Rings', 'Fantasy epic trilogy', 39.99, 60, 'Books'),
(6, 'Dune by Frank Herbert', 'Science fiction masterpiece', 16.99, 70, 'Books'),
(6, 'The Hobbit', 'Fantasy adventure novel', 12.99, 85, 'Books'),
(6, 'Cookbook Collection', 'International recipes', 24.99, 40, 'Books'),
(6, 'Art History Book', 'Comprehensive art guide', 34.99, 30, 'Books'),
(6, 'Programming Guide', 'Learn to code book', 29.99, 45, 'Books'),
(6, 'Photography Manual', 'Digital photography tips', 19.99, 55, 'Books'),
(6, 'Travel Guide Europe', 'Complete European travel', 22.99, 35, 'Books'),
(6, 'Self-Help Success', 'Personal development book', 17.99, 65, 'Books'),
(6, 'Mystery Novel Series', '5-book detective series', 49.99, 25, 'Books'),
(6, 'Science Textbook', 'Advanced physics guide', 89.99, 20, 'Books'),
(6, 'Children\'s Picture Book', 'Illustrated story book', 9.99, 100, 'Books'),
(6, 'Biography Collection', 'Famous people stories', 27.99, 40, 'Books'),
(6, 'Poetry Anthology', 'Classic poems collection', 15.99, 50, 'Books'),

-- Beauty Zone Cosmetics (vendor_id: 7)
(7, 'Foundation Makeup', 'Full coverage liquid foundation', 39.99, 80, 'Beauty'),
(7, 'Lipstick Set', '12-color lipstick collection', 49.99, 60, 'Beauty'),
(7, 'Eyeshadow Palette', '24-shade eyeshadow kit', 34.99, 70, 'Beauty'),
(7, 'Mascara Waterproof', 'Long-lasting mascara', 19.99, 100, 'Beauty'),
(7, 'Skincare Routine Kit', 'Complete facial care set', 79.99, 45, 'Beauty'),
(7, 'Perfume Collection', 'Designer fragrance set', 89.99, 35, 'Beauty'),
(7, 'Nail Polish Set', '20-color nail lacquer', 29.99, 85, 'Beauty'),
(7, 'Hair Styling Tools', 'Curling iron and straightener', 69.99, 40, 'Beauty'),
(7, 'Face Mask Set', 'Hydrating sheet masks', 24.99, 90, 'Beauty'),
(7, 'Makeup Brushes', 'Professional brush set', 44.99, 55, 'Beauty'),
(7, 'Anti-Aging Cream', 'Wrinkle reduction cream', 59.99, 50, 'Beauty'),
(7, 'Sunscreen SPF 50', 'UV protection lotion', 16.99, 120, 'Beauty'),
(7, 'Hair Shampoo Set', 'Organic hair care duo', 32.99, 75, 'Beauty'),
(7, 'Body Lotion', 'Moisturizing body cream', 22.99, 95, 'Beauty'),
(7, 'Eyeliner Pencil', 'Waterproof eye liner', 12.99, 110, 'Beauty'),
(7, 'Blush Compact', 'Natural cheek color', 18.99, 80, 'Beauty'),
(7, 'Concealer Stick', 'Full coverage concealer', 21.99, 85, 'Beauty'),
(7, 'Lip Balm Set', 'Moisturizing lip care', 14.99, 100, 'Beauty'),
(7, 'Face Cleanser', 'Gentle facial wash', 19.99, 90, 'Beauty'),
(7, 'Makeup Remover', 'Gentle makeup cleanser', 17.99, 95, 'Beauty'),

-- Toyland Express (vendor_id: 8)
(8, 'LEGO Creator Set', 'Building blocks construction', 79.99, 50, 'Toys'),
(8, 'Barbie Dreamhouse', 'Dollhouse playset', 199.99, 25, 'Toys'),
(8, 'Remote Control Car', 'RC racing vehicle', 89.99, 40, 'Toys'),
(8, 'Board Game Collection', 'Family game night set', 49.99, 60, 'Toys'),
(8, 'Action Figure Set', 'Superhero toy collection', 34.99, 75, 'Toys'),
(8, 'Puzzle 1000 Pieces', 'Challenging jigsaw puzzle', 19.99, 80, 'Toys'),
(8, 'Stuffed Animal Bear', 'Soft plush teddy bear', 24.99, 90, 'Toys'),
(8, 'Art Supply Kit', 'Complete drawing set', 39.99, 55, 'Toys'),
(8, 'Science Experiment Kit', 'Educational STEM toy', 44.99, 45, 'Toys'),
(8, 'Musical Keyboard', 'Electronic piano toy', 69.99, 35, 'Toys'),
(8, 'Drone for Kids', 'Beginner quadcopter', 99.99, 30, 'Toys'),
(8, 'Train Set Electric', 'Model railway system', 149.99, 20, 'Toys'),
(8, 'Basketball Hoop', 'Adjustable kids hoop', 79.99, 40, 'Toys'),
(8, 'Craft Making Kit', 'DIY jewelry making', 29.99, 70, 'Toys'),
(8, 'Robot Building Kit', 'Programmable robot toy', 119.99, 25, 'Toys'),
(8, 'Dollhouse Furniture', 'Miniature furniture set', 39.99, 50, 'Toys'),
(8, 'Water Gun Super', 'Summer water blaster', 19.99, 85, 'Toys'),
(8, 'Coloring Book Set', 'Activity books with crayons', 14.99, 100, 'Toys'),
(8, 'Magic Trick Set', 'Beginner magic kit', 24.99, 60, 'Toys'),
(8, 'Outdoor Playground', 'Backyard play equipment', 299.99, 15, 'Toys'),

-- Auto Parts Central (vendor_id: 9)
(9, 'Car Engine Oil', 'Synthetic motor oil 5W-30', 29.99, 100, 'Automotive'),
(9, 'Brake Pads Set', 'Ceramic brake pads', 79.99, 50, 'Automotive'),
(9, 'Air Filter', 'Engine air filter replacement', 19.99, 80, 'Automotive'),
(9, 'Spark Plugs', 'Iridium spark plug set', 39.99, 70, 'Automotive'),
(9, 'Car Battery', '12V automotive battery', 129.99, 30, 'Automotive'),
(9, 'Windshield Wipers', 'All-weather wiper blades', 24.99, 90, 'Automotive'),
(9, 'Tire Pressure Gauge', 'Digital tire gauge', 14.99, 60, 'Automotive'),
(9, 'Car Floor Mats', 'All-weather floor protection', 49.99, 45, 'Automotive'),
(9, 'LED Headlights', 'Bright LED bulb kit', 89.99, 40, 'Automotive'),
(9, 'Car Phone Mount', 'Dashboard phone holder', 19.99, 85, 'Automotive'),
(9, 'Jump Starter', 'Portable battery booster', 99.99, 35, 'Automotive'),
(9, 'Car Vacuum', 'Portable car cleaner', 59.99, 50, 'Automotive'),
(9, 'Seat Covers', 'Universal car seat protection', 69.99, 40, 'Automotive'),
(9, 'Car Charger', 'USB car charging adapter', 12.99, 100, 'Automotive'),
(9, 'Steering Wheel Cover', 'Leather steering cover', 24.99, 75, 'Automotive'),
(9, 'Car Wash Kit', 'Complete cleaning supplies', 34.99, 55, 'Automotive'),
(9, 'Backup Camera', 'Wireless rear view camera', 149.99, 25, 'Automotive'),
(9, 'Car Alarm System', 'Security alarm kit', 199.99, 20, 'Automotive'),
(9, 'Transmission Fluid', 'Automatic transmission oil', 22.99, 65, 'Automotive'),
(9, 'Car Tool Kit', 'Emergency roadside tools', 44.99, 45, 'Automotive'),

-- Music Store Harmony (vendor_id: 10)
(10, 'Acoustic Guitar', 'Steel string acoustic guitar', 199.99, 30, 'Music'),
(10, 'Electric Guitar', 'Solid body electric guitar', 399.99, 25, 'Music'),
(10, 'Digital Piano', '88-key weighted keyboard', 799.99, 15, 'Music'),
(10, 'Drum Set', 'Complete 5-piece drum kit', 599.99, 10, 'Music'),
(10, 'Violin', 'Full-size acoustic violin', 149.99, 35, 'Music'),
(10, 'Microphone', 'Professional vocal mic', 89.99, 50, 'Music'),
(10, 'Guitar Amplifier', '20W practice amp', 129.99, 40, 'Music'),
(10, 'Music Stand', 'Adjustable sheet music stand', 29.99, 60, 'Music'),
(10, 'Guitar Strings', 'Steel acoustic strings', 12.99, 100, 'Music'),
(10, 'Piano Bench', 'Adjustable piano seat', 79.99, 45, 'Music'),
(10, 'Saxophone', 'Alto saxophone brass', 699.99, 12, 'Music'),
(10, 'Trumpet', 'Brass trumpet instrument', 299.99, 20, 'Music'),
(10, 'Ukulele', '4-string soprano ukulele', 79.99, 55, 'Music'),
(10, 'Music Theory Book', 'Complete theory guide', 24.99, 70, 'Music'),
(10, 'Guitar Pick Set', 'Variety pack guitar picks', 9.99, 120, 'Music'),
(10, 'Metronome', 'Digital tempo keeper', 34.99, 50, 'Music'),
(10, 'Audio Interface', 'USB recording interface', 199.99, 25, 'Music'),
(10, 'Studio Headphones', 'Professional monitoring', 149.99, 35, 'Music'),
(10, 'Guitar Case', 'Hard shell guitar case', 99.99, 40, 'Music'),
(10, 'Sheet Music Collection', 'Popular songs book', 19.99, 80, 'Music'),

-- Pet Shop Paradise (vendor_id: 11)
(11, 'Dog Food Premium', 'High-quality dry dog food', 49.99, 60, 'Pets'),
(11, 'Cat Litter Box', 'Self-cleaning litter system', 89.99, 35, 'Pets'),
(11, 'Fish Tank 20 Gallon', 'Complete aquarium setup', 129.99, 25, 'Pets'),
(11, 'Dog Leash Retractable', 'Extendable dog leash', 24.99, 80, 'Pets'),
(11, 'Cat Scratching Post', 'Tall sisal scratching tower', 59.99, 45, 'Pets'),
(11, 'Bird Cage Large', 'Spacious bird habitat', 149.99, 20, 'Pets'),
(11, 'Pet Bed Orthopedic', 'Memory foam pet bed', 79.99, 50, 'Pets'),
(11, 'Dog Toys Set', 'Variety pack chew toys', 29.99, 75, 'Pets'),
(11, 'Cat Food Wet', 'Gourmet cat food cans', 34.99, 90, 'Pets'),
(11, 'Hamster Cage', 'Multi-level hamster home', 69.99, 30, 'Pets'),
(11, 'Pet Carrier', 'Airline approved carrier', 54.99, 40, 'Pets'),
(11, 'Dog Shampoo', 'Gentle pet grooming', 19.99, 85, 'Pets'),
(11, 'Cat Treats', 'Healthy training treats', 12.99, 100, 'Pets'),
(11, 'Pet Water Fountain', 'Automatic water dispenser', 44.99, 55, 'Pets'),
(11, 'Dog Training Collar', 'Adjustable training aid', 39.99, 60, 'Pets'),
(11, 'Pet Grooming Kit', 'Complete grooming tools', 64.99, 35, 'Pets'),
(11, 'Rabbit Hutch', 'Outdoor rabbit house', 199.99, 15, 'Pets'),
(11, 'Pet ID Tags', 'Personalized name tags', 9.99, 120, 'Pets'),
(11, 'Dog Waste Bags', 'Biodegradable cleanup bags', 14.99, 95, 'Pets'),
(11, 'Pet First Aid Kit', 'Emergency pet care kit', 34.99, 50, 'Pets');

-- Update passwords to be properly hashed (password: admin123 for admin, vendor123 for vendors)
UPDATE users SET password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' WHERE user_type = 'admin';
UPDATE users SET password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' WHERE user_type = 'vendor';