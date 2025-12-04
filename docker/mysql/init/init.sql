-- ============================================================================
-- MySQL Initialization Script for Blazz
-- ============================================================================
-- This script runs when the MySQL container starts for the first time
-- ============================================================================

-- Set character set and collation
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- Create database if not exists (backup in case env var fails)
CREATE DATABASE IF NOT EXISTS `blazz` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

-- Grant all privileges to the application user
GRANT ALL PRIVILEGES ON `blazz`.* TO 'blazz'@'%';
FLUSH PRIVILEGES;

-- Optional: Create additional databases for testing
-- CREATE DATABASE IF NOT EXISTS `blazz_testing` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- GRANT ALL PRIVILEGES ON `blazz_testing`.* TO 'blazz'@'%';
