-- Add prize columns to games table
ALTER TABLE `games` 
ADD COLUMN IF NOT EXISTS `first_prize` DECIMAL(10, 2) DEFAULT 0.00 COMMENT '1st prize amount in rupees',
ADD COLUMN IF NOT EXISTS `second_prize` DECIMAL(10, 2) DEFAULT 0.00 COMMENT '2nd prize amount in rupees',
ADD COLUMN IF NOT EXISTS `third_prize` DECIMAL(10, 2) DEFAULT 0.00 COMMENT '3rd prize amount in rupees';

-- Update existing games with default prizes if needed
UPDATE `games` 
SET `first_prize` = 0.00, `second_prize` = 0.00, `third_prize` = 0.00 
WHERE `first_prize` IS NULL OR `second_prize` IS NULL OR `third_prize` IS NULL;
