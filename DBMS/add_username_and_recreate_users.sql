-- First, add username column to users table
ALTER TABLE users ADD COLUMN username VARCHAR(50) UNIQUE NOT NULL AFTER email;

-- Delete all related data to avoid foreign key constraints
DELETE FROM product_views;
DELETE FROM product_reviews;
DELETE FROM cart;
DELETE FROM order_items;
DELETE FROM orders;
DELETE FROM products;

-- Delete all users except admin
DELETE FROM users WHERE user_type IN ('vendor', 'buyer');

-- Update admin user to have a username
UPDATE users SET username = 'admin' WHERE user_type = 'admin';

-- Reset auto-increment for users table
ALTER TABLE users AUTO_INCREMENT = 2;

-- Insert 10 vendors with usernames
INSERT INTO users (full_name, email, username, password, phone, address, user_type, email_verified) VALUES 
('TechWorld Electronics', 'vendor1@carthub.com', 'techworld', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '555-0101', '123 Tech Street, Silicon Valley, CA 94000', 'vendor', TRUE),
('Fashion Forward', 'vendor2@carthub.com', 'fashionforward', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '555-0102', '456 Fashion Ave, New York, NY 10001', 'vendor', TRUE),
('Home & Garden Paradise', 'vendor3@carthub.com', 'homegarden', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '555-0103', '789 Garden Lane, Portland, OR 97201', 'vendor', TRUE),
('Sports Central', 'vendor4@carthub.com', 'sportscentral', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '555-0104', '321 Sports Blvd, Denver, CO 80202', 'vendor', TRUE),
('BookWorm Haven', 'vendor5@carthub.com', 'bookworm', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '555-0105', '654 Library St, Boston, MA 02101', 'vendor', TRUE),
('Beauty Essentials', 'vendor6@carthub.com', 'beautyessentials', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '555-0106', '987 Beauty Blvd, Los Angeles, CA 90210', 'vendor', TRUE),
('Toy Kingdom', 'vendor7@carthub.com', 'toykingdom', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '555-0107', '147 Play Street, Orlando, FL 32801', 'vendor', TRUE),
('Auto Parts Pro', 'vendor8@carthub.com', 'autoparts', '$2y$10$92IXUNpkjO0rOQ5byMI.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '555-0108', '258 Motor Way, Detroit, MI 48201', 'vendor', TRUE),
('Music & Sound', 'vendor9@carthub.com', 'musicsound', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '555-0109', '369 Melody Lane, Nashville, TN 37201', 'vendor', TRUE),
('Pet Paradise', 'vendor10@carthub.com', 'petparadise', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '555-0110', '741 Pet Avenue, Austin, TX 78701', 'vendor', TRUE);

-- Insert 3 buyers with usernames
INSERT INTO users (full_name, email, username, password, phone, address, user_type, email_verified) VALUES 
('John Smith', 'buyer1@carthub.com', 'johnsmith', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '555-1001', '123 Main St, Anytown, USA', 'buyer', TRUE),
('Sarah Johnson', 'buyer2@carthub.com', 'sarahjohnson', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '555-1002', '456 Oak Ave, Somewhere, USA', 'buyer', TRUE),
('Mike Davis', 'buyer3@carthub.com', 'mikedavis', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '555-1003', '789 Pine St, Elsewhere, USA', 'buyer', TRUE);

-- Verify the data
SELECT id, full_name, email, username, user_type FROM users ORDER BY id;
