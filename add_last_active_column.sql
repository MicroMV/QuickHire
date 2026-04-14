-- Migration: Add last_active column to users table
-- Run this to add the active status feature to existing databases

ALTER TABLE users 
ADD COLUMN last_active TIMESTAMP NULL DEFAULT NULL AFTER is_profile_complete,
ADD INDEX idx_last_active (last_active);

-- Update existing users to have current timestamp as last_active
UPDATE users SET last_active = NOW();
