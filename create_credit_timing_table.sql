-- Create credit_timing table for managing add/claim credits timing
CREATE TABLE IF NOT EXISTS credit_timing (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    timing_type ENUM('add_credits', 'claim_credits') NOT NULL UNIQUE,
    date_from DATE NULL COMMENT 'Start date for credit availability',
    time_from TIME NULL COMMENT 'Start time for credit availability',
    date_to DATE NULL COMMENT 'End date for credit availability',
    time_to TIME NULL COMMENT 'End time for credit availability',
    is_enabled TINYINT(1) DEFAULT 1 COMMENT 'Whether this timing is enabled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_timing_type (timing_type),
    INDEX idx_is_enabled (is_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
