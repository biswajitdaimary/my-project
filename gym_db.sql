CREATE DATABASE IF NOT EXISTS gym_db;
USE gym_db;

-- 1. users
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other'),
    profile_photo VARCHAR(255),
    role ENUM('user', 'admin') DEFAULT 'user',
    is_verified TINYINT(1) DEFAULT 0,
    verification_token VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 2. membership_plans
CREATE TABLE IF NOT EXISTS membership_plans (
    plan_id INT AUTO_INCREMENT PRIMARY KEY,
    plan_name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    duration_days INT NOT NULL,
    trainer_sessions INT DEFAULT 0,
    features_json JSON,
    is_popular TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. user_memberships
CREATE TABLE IF NOT EXISTS user_memberships (
    membership_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plan_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('active', 'expired', 'cancelled', 'pending') DEFAULT 'pending',
    payment_id INT DEFAULT NULL,
    sessions_remaining INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES membership_plans(plan_id) ON DELETE CASCADE
);

-- 4. trainers
CREATE TABLE IF NOT EXISTS trainers (
    trainer_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20),
    specialization VARCHAR(100),
    experience_years INT,
    bio TEXT,
    photo VARCHAR(255),
    hourly_rate DECIMAL(8,2),
    rating DECIMAL(3,2) DEFAULT 5.00,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 5. trainer_availability
CREATE TABLE IF NOT EXISTS trainer_availability (
    availability_id INT AUTO_INCREMENT PRIMARY KEY,
    trainer_id INT NOT NULL,
    day_of_week ENUM('Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_booked TINYINT(1) DEFAULT 0,
    FOREIGN KEY (trainer_id) REFERENCES trainers(trainer_id) ON DELETE CASCADE
);

-- 6. trainer_bookings
CREATE TABLE IF NOT EXISTS trainer_bookings (
    booking_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    trainer_id INT NOT NULL,
    session_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    payment_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (trainer_id) REFERENCES trainers(trainer_id) ON DELETE CASCADE
);

-- 7. payments
CREATE TABLE IF NOT EXISTS payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plan_id INT DEFAULT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'INR',
    gateway ENUM('razorpay', 'stripe', 'cash', 'other') NOT NULL,
    gateway_order_id VARCHAR(100),
    gateway_payment_id VARCHAR(100),
    gateway_signature VARCHAR(255),
    status ENUM('pending', 'success', 'failed', 'refunded') DEFAULT 'pending',
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    receipt_path VARCHAR(255),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES membership_plans(plan_id) ON DELETE SET NULL
);

-- 8. password_resets
CREATE TABLE IF NOT EXISTS password_resets (
    reset_id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(100) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 9. remember_tokens
CREATE TABLE IF NOT EXISTS remember_tokens (
    token_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(100) UNIQUE NOT NULL,
    expires_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- 10. gallery
CREATE TABLE IF NOT EXISTS gallery (
    image_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    thumbnail_path VARCHAR(255),
    category VARCHAR(50),
    alt_text VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 11. blog_categories
CREATE TABLE IF NOT EXISTS blog_categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 12. blog_posts
CREATE TABLE IF NOT EXISTS blog_posts (
    post_id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    content LONGTEXT,
    excerpt TEXT,
    featured_image VARCHAR(255),
    author_id INT,
    status ENUM('draft', 'published') DEFAULT 'draft',
    views INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES blog_categories(category_id) ON DELETE SET NULL,
    FOREIGN KEY (author_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- 13. contact_messages
CREATE TABLE IF NOT EXISTS contact_messages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    subject VARCHAR(255),
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    admin_reply TEXT NULL,
    replied_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 14. bmi_records
CREATE TABLE IF NOT EXISTS bmi_records (
    bmi_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    age INT DEFAULT NULL,
    gender ENUM('male', 'female', 'other') DEFAULT NULL,
    weight_kg DECIMAL(5,2) NOT NULL,
    height_cm DECIMAL(5,2) NOT NULL,
    bmi_value DECIMAL(4,2) NOT NULL,
    category ENUM('Underweight', 'Normal', 'Overweight', 'Obese') NOT NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- 15. notifications
CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT,
    type ENUM('info', 'success', 'warning', 'danger') DEFAULT 'info',
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- 16. site_settings
CREATE TABLE IF NOT EXISTS site_settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_group VARCHAR(50),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- SEED DATA
-- 1 admin user: password is Admin@1234
INSERT INTO users (full_name, email, password_hash, role, is_verified) VALUES 
('Admin User', 'admin@gym.com', '$2y$12$lWQ4tlgNCHYwvtzLmwdX0en/ULJWi/JVrRBVdh2.fkBcbSKtca5d6', 'admin', 1);

-- 3 membership plans
INSERT INTO membership_plans (plan_name, description, price, duration_days, trainer_sessions, features_json, is_popular) VALUES 
('Basic', 'Perfect for beginners.', 999.00, 30, 0, '["Full Gym Access", "Locker Facility", "Free WiFi"]', 0),
('Premium', 'Most popular choice.', 1999.00, 90, 3, '["All Basic Features", "3 Personal Trainer Sessions", "Diet Plan Included", "Access to Group Classes"]', 1),
('Elite', 'Ultimate fitness experience.', 4999.00, 365, 12, '["All Premium Features", "12 Personal Trainer Sessions", "Monthly Body Composition Analysis", "Guest Passes", "Free Gym Merchandise"]', 0);

-- 4 trainers
INSERT INTO trainers (full_name, email, phone, specialization, experience_years, bio, photo, hourly_rate, rating) VALUES 
('Mike Johnson', 'mike@gym.com', '9876543210', 'Bodybuilding', 8, 'Expert in muscle building and strength conditioning.', 'https://placehold.co/400x400/FF6B35/FFF?text=Mike', 500.00, 4.9),
('Sarah Lee', 'sarah@gym.com', '9876543211', 'Yoga & Pilates', 5, 'Specializes in flexibility, core strength, and mental wellness.', 'https://placehold.co/400x400/1A1A2E/FFF?text=Sarah', 400.00, 4.8),
('David Chen', 'david@gym.com', '9876543212', 'CrossFit', 6, 'High-intensity interval training specialist.', 'https://placehold.co/400x400/333/FFF?text=David', 600.00, 4.7),
('Elena Rossi', 'elena@gym.com', '9876543213', 'Weight Loss', 7, 'Dedicated to helping clients achieve sustainable fat loss.', 'https://placehold.co/400x400/555/FFF?text=Elena', 450.00, 4.9);

-- Trainer availability (Mon-Sat, basic slots for Mike Johnson)
INSERT INTO trainer_availability (trainer_id, day_of_week, start_time, end_time) VALUES
(1, 'Mon', '07:00:00', '08:00:00'),
(1, 'Mon', '08:00:00', '09:00:00'),
(1, 'Tue', '07:00:00', '08:00:00'),
(2, 'Wed', '17:00:00', '18:00:00'),
(3, 'Thu', '18:00:00', '19:00:00'),
(4, 'Fri', '09:00:00', '10:00:00');

-- 3 gallery categories/images
INSERT INTO gallery (title, file_path, category) VALUES
('Free Weight Area', 'https://placehold.co/800x600/FF6B35/FFF?text=Weights', 'gym'),
('Cardio Section', 'https://placehold.co/800x600/1A1A2E/FFF?text=Cardio', 'equipment'),
('Yoga Class', 'https://placehold.co/800x600/555/FFF?text=Yoga', 'classes');

-- Blog categories
INSERT INTO blog_categories (name, slug, description) VALUES
('Nutrition', 'nutrition', 'Diet plans and healthy eating tips.'),
('Workouts', 'workouts', 'Guides and workout routines.'),
('Lifestyle', 'lifestyle', 'General wellness and fitness lifestyle.');

-- Blog posts
INSERT INTO blog_posts (category_id, title, slug, content, excerpt, author_id, status) VALUES
(1, 'Top 10 High Protein Foods', 'top-10-protein-foods', '<p>Full content goes here...</p>', 'Discover the best protein sources for muscle growth.', 1, 'published'),
(2, 'Beginners Guide to Deadlifts', 'beginners-guide-deadlifts', '<p>Full content goes here...</p>', 'Learn the proper form and technique.', 1, 'published');

-- Site settings
INSERT INTO site_settings (setting_key, setting_value, setting_group) VALUES
('gym_name', 'FITNESS DESTINATION', 'general'),
('phone', '+1 234 567 890', 'contact'),
('email', 'info@powerhousegym.com', 'contact'),
('address', '123 Fitness Street, Gym City', 'contact'),
('facebook_url', 'https://facebook.com', 'social'),
('instagram_url', 'https://instagram.com', 'social'),
('twitter_url', 'https://twitter.com', 'social'),
('linkedin_url', 'https://linkedin.com', 'social'),
('razorpay_key_id', 'rzp_test_YourKeyIdHere', 'payment'),
('razorpay_key_secret', 'YourTestSecretHere', 'payment');
