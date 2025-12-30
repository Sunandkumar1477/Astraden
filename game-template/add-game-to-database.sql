-- Add Cosmos Captain game to the database
-- Game name: cosmos-captain
-- Display name: Cosmos Captain

-- Add game to games table
INSERT INTO `games` (
    `game_name`, 
    `display_name`, 
    `credits_per_chance`, 
    `is_active`,
    `is_contest_active`,
    `is_claim_active`,
    `game_mode`,
    `contest_first_prize`,
    `contest_second_prize`,
    `contest_third_prize`
) VALUES (
    'cosmos-captain',           -- Game identifier
    'Cosmos Captain',            -- Display name shown to users
    30,                         -- Credits required per play
    1,                          -- 1 = active, 0 = inactive
    0,                          -- Contest active (0 = no, 1 = yes)
    0,                          -- Claim active (0 = no, 1 = yes)
    'money',                    -- Game mode: 'money' or 'credits'
    0,                          -- First prize amount
    0,                          -- Second prize amount
    0                           -- Third prize amount
)
ON DUPLICATE KEY UPDATE 
    `display_name` = VALUES(`display_name`),
    `credits_per_chance` = VALUES(`credits_per_chance`),
    `is_active` = VALUES(`is_active`);

-- Create an "always available" game session
-- This allows the game to be played anytime (not time-restricted)
INSERT INTO `game_sessions` (
    `game_name`,
    `session_date`,
    `session_time`,
    `duration_minutes`,
    `is_active`,
    `always_available`
) VALUES (
    'cosmos-captain',           -- Match game_name above
    CURDATE(),                   -- Today's date
    '00:00:00',                 -- Start time (not used for always_available)
    1440,                       -- 24 hours (not used for always_available)
    1,                          -- Active
    1                           -- Always available (1 = yes, 0 = no)
)
ON DUPLICATE KEY UPDATE 
    `is_active` = VALUES(`is_active`),
    `always_available` = VALUES(`always_available`);

-- Add conversion rate for shop
-- This allows users to convert scores from this game into credits in the shop
INSERT INTO `score_shop_settings` (
    `game_name`,
    `score_per_credit`,
    `claim_credits_score`,
    `is_active`
) VALUES (
    'cosmos-captain',           -- Match game_name above
    100,                        -- Score required per credit (adjust as needed)
    0,                          -- Minimum score to claim 1 credit (0 = disabled)
    1                           -- Active
)
ON DUPLICATE KEY UPDATE 
    `score_per_credit` = VALUES(`score_per_credit`),
    `is_active` = VALUES(`is_active`);

-- Verify the game was added
SELECT * FROM `games` WHERE `game_name` = 'cosmos-captain';

