-- Add referral system columns to users table
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS referral_code VARCHAR(4) UNIQUE NULL COMMENT 'Unique 4-digit referral code',
ADD COLUMN IF NOT EXISTS referred_by INT(11) NULL COMMENT 'User ID who referred this user',
ADD INDEX idx_referral_code (referral_code),
ADD INDEX idx_referred_by (referred_by),
ADD CONSTRAINT fk_referred_by FOREIGN KEY (referred_by) REFERENCES users(id) ON DELETE SET NULL;

-- Create referral_earnings table
CREATE TABLE IF NOT EXISTS referral_earnings (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    referrer_id INT(11) NOT NULL COMMENT 'User who referred',
    referred_user_id INT(11) NOT NULL COMMENT 'User who was referred',
    purchase_amount DECIMAL(10,2) NOT NULL COMMENT 'Amount of credits purchased',
    referral_credits INT(11) NOT NULL COMMENT 'Credits earned (10% of purchase)',
    status VARCHAR(20) DEFAULT 'pending' COMMENT 'pending, credited',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    credited_at TIMESTAMP NULL,
    INDEX idx_referrer_id (referrer_id),
    INDEX idx_referred_user_id (referred_user_id),
    INDEX idx_status (status),
    FOREIGN KEY (referrer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (referred_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
