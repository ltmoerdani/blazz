-- =====================================================================
-- DATABASE CHECKPOINT RESTORE SCRIPT
-- =====================================================================
-- Purpose: Mengembalikan database blazz ke struktur original sebelum 
--          perubahan staging-whatsapp sambil mempertahankan data rebranding
-- Date: September 29, 2025
-- Status: EXECUTE ONLY IF NEEDED - Current database sudah dalam kondisi baik
-- =====================================================================

-- =====================================================================
-- PART 1: FIX MISSING SETTINGS (IMMEDIATE PRIORITY)
-- =====================================================================
-- These 2 settings are missing from blazz database and need to be restored

USE blazz;

-- Insert missing settings that exist in blazz but missing in blazz
INSERT IGNORE INTO settings (`key`, `value`, `created_at`, `updated_at`) VALUES
('display_frontend', '1', NOW(), NOW()),
('enable_ai_billing', '0', NOW(), NOW());

-- Verify the settings were inserted
SELECT 'VERIFICATION: Missing Settings Added' as status;
SELECT `key`, `value` FROM settings WHERE `key` IN ('display_frontend', 'enable_ai_billing');

-- =====================================================================
-- PART 2: CLEANUP STAGING-WHATSAPP TABLES (IF THEY EXIST)
-- =====================================================================
-- Execute this section ONLY if staging-whatsapp migrations were accidentally applied

-- Check if staging-whatsapp tables exist
SELECT 'CHECKING FOR STAGING-WHATSAPP TABLES' as status;
SELECT TABLE_NAME as existing_staging_tables
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = 'blazz' 
AND TABLE_NAME IN (
    'organization_channels', 
    'whatsapp_sessions', 
    'device_activities'
);

-- DROP staging-whatsapp tables if they exist (UNCOMMENT IF NEEDED)
-- WARNING: This will delete all data in these tables!
-- SET FOREIGN_KEY_CHECKS = 0;
-- DROP TABLE IF EXISTS device_activities;
-- DROP TABLE IF EXISTS whatsapp_sessions;
-- DROP TABLE IF EXISTS organization_channels;
-- SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- PART 3: VERIFY DATABASE CHECKPOINT STATUS
-- =====================================================================

-- Verify table count matches original structure
SELECT 'DATABASE STRUCTURE VERIFICATION' as status;
SELECT COUNT(*) as total_tables FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = 'blazz';

-- Verify key data integrity
SELECT 'DATA REBRANDING VERIFICATION' as status;
SELECT `key`, `value` FROM settings WHERE `key` = 'company_name';

-- Verify critical tables exist and have expected structure
SELECT 'CRITICAL TABLES VERIFICATION' as status;
SELECT 
    TABLE_NAME,
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'blazz' AND TABLE_NAME = t.TABLE_NAME) as column_count
FROM INFORMATION_SCHEMA.TABLES t
WHERE TABLE_SCHEMA = 'blazz' 
AND TABLE_NAME IN ('users', 'organizations', 'contacts', 'chats', 'addons', 'settings')
ORDER BY TABLE_NAME;

-- =====================================================================
-- PART 4: CREATE BACKUP CHECKPOINT (RECOMMENDED)
-- =====================================================================

-- Create a backup of current clean state for future rollbacks
-- Execute this from command line:
/*
BACKUP COMMANDS (Execute in terminal):

# Create backup directory
mkdir -p docs/database-checkpoints/

# Backup current clean state
mysqldump -u root -p blazz > docs/database-checkpoints/blazz_clean_checkpoint_$(date +%Y%m%d_%H%M%S).sql

# Create compressed backup
mysqldump -u root -p blazz | gzip > docs/database-checkpoints/blazz_clean_checkpoint_$(date +%Y%m%d_%H%M%S).sql.gz
*/

-- =====================================================================
-- PART 5: ROLLBACK TO PRE-REBRANDING (EMERGENCY ONLY)
-- =====================================================================
-- Use this ONLY if you need to completely rollback to pre-rebranding state

/*
EMERGENCY ROLLBACK COMMANDS (Execute in terminal if needed):

# BACKUP current state first
mysqldump -u root -p blazz > docs/database-checkpoints/blazz_backup_before_rollback_$(date +%Y%m%d_%H%M%S).sql

# Restore pre-rebranding state
mysql -u root -p -e "DROP DATABASE IF EXISTS blazz;"
mysql -u root -p -e "CREATE DATABASE blazz;"
mysql -u root -p blazz < docs/rebranding-implementation/backups/database/blazz_backup_pre_rebrand_20250919_091400.sql

# Update database references in restored data
mysql -u root -p blazz -e "UPDATE settings SET value = 'blazz' WHERE \`key\` = 'app_name';"
*/

-- =====================================================================
-- PART 6: FINAL STATUS CHECK
-- =====================================================================

SELECT 'FINAL CHECKPOINT STATUS' as status;
SELECT 
    'Database Name' as metric, 'blazz' as value
UNION ALL SELECT 
    'Total Tables', CAST(COUNT(*) as CHAR) 
FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = 'blazz'
UNION ALL SELECT 
    'Total Settings', CAST(COUNT(*) as CHAR)
FROM settings
UNION ALL SELECT 
    'Company Name', value
FROM settings WHERE `key` = 'company_name'
UNION ALL SELECT 
    'Missing Settings Fixed', 
    CASE 
        WHEN EXISTS (SELECT 1 FROM settings WHERE `key` = 'display_frontend')
        AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'enable_ai_billing')
        THEN 'YES' 
        ELSE 'NO' 
    END;

SELECT 'CHECKPOINT RESTORE SCRIPT COMPLETED' as final_status;