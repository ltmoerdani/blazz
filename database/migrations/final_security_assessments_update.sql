-- ============================================================
-- Final Migration: Update security_assessments table
-- Database: blazz
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- Update security_assessments table
ALTER TABLE `security_assessments` 
    CHANGE COLUMN `organization_id` `workspace_id` BIGINT UNSIGNED NULL;

-- Update foreign key constraint (check first if exists)
-- Get existing constraints first
SET @constraint_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
    WHERE TABLE_SCHEMA = 'blazz' 
    AND TABLE_NAME = 'security_assessments' 
    AND CONSTRAINT_NAME = 'security_assessments_organization_id_foreign'
);

-- Drop if exists
SET @drop_fk = IF(@constraint_exists > 0, 
    'ALTER TABLE `security_assessments` DROP FOREIGN KEY `security_assessments_organization_id_foreign`',
    'SELECT "Constraint does not exist"');
PREPARE stmt FROM @drop_fk;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add new constraint
ALTER TABLE `security_assessments` 
    ADD CONSTRAINT `security_assessments_workspace_id_foreign` 
    FOREIGN KEY (`workspace_id`) REFERENCES `workspaces`(`id`) ON DELETE CASCADE;

-- Update index (check first if exists)
SET @index_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = 'blazz' 
    AND TABLE_NAME = 'security_assessments' 
    AND INDEX_NAME = 'security_assessments_organization_id_index'
);

-- Drop if exists
SET @drop_idx = IF(@index_exists > 0, 
    'ALTER TABLE `security_assessments` DROP INDEX `security_assessments_organization_id_index`',
    'SELECT "Index does not exist"');
PREPARE stmt2 FROM @drop_idx;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- Add new index
ALTER TABLE `security_assessments` ADD INDEX `security_assessments_workspace_id_index` (`workspace_id`);

-- Update migrations table
UPDATE `migrations` 
SET `migration` = REPLACE(`migration`, 'organization', 'workspace')
WHERE `migration` LIKE '%organization%';

SET FOREIGN_KEY_CHECKS = 1;

-- Verification
SELECT '=== Migration Complete ===' as '';
SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'blazz' 
AND COLUMN_NAME LIKE '%organization%';
