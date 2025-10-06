-- ============================================================
-- Migration Script: organization -> workspace
-- Database: blazz
-- Date: 2025-10-06
-- ============================================================

-- Disable foreign key checks temporarily
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- Step 1: Rename Tables
-- ============================================================

-- Rename organizations table to workspaces
ALTER TABLE `organizations` RENAME TO `workspaces`;

-- Rename organization_api_keys table to workspace_api_keys
ALTER TABLE `organization_api_keys` RENAME TO `workspace_api_keys`;

-- ============================================================
-- Step 2: Rename Foreign Key Columns in Other Tables
-- ============================================================

-- Update teams table
ALTER TABLE `teams` 
    CHANGE COLUMN `organization_id` `workspace_id` BIGINT UNSIGNED NULL;

-- Update subscriptions table
ALTER TABLE `subscriptions` 
    CHANGE COLUMN `organization_id` `workspace_id` BIGINT UNSIGNED NULL;

-- Update billing_transactions table
ALTER TABLE `billing_transactions` 
    CHANGE COLUMN `organization_id` `workspace_id` BIGINT UNSIGNED NULL;

-- Update billing_invoices table
ALTER TABLE `billing_invoices` 
    CHANGE COLUMN `organization_id` `workspace_id` BIGINT UNSIGNED NULL;

-- Update billing_payments table
ALTER TABLE `billing_payments` 
    CHANGE COLUMN `organization_id` `workspace_id` BIGINT UNSIGNED NULL;

-- Update billing_credits table
ALTER TABLE `billing_credits` 
    CHANGE COLUMN `organization_id` `workspace_id` BIGINT UNSIGNED NULL;

-- Update billing_debits table
ALTER TABLE `billing_debits` 
    CHANGE COLUMN `organization_id` `workspace_id` BIGINT UNSIGNED NULL;

-- Update contacts table
ALTER TABLE `contacts` 
    CHANGE COLUMN `organization_id` `workspace_id` BIGINT UNSIGNED NULL;

-- Update contact_groups table
ALTER TABLE `contact_groups` 
    CHANGE COLUMN `organization_id` `workspace_id` BIGINT UNSIGNED NULL;

-- Update templates table
ALTER TABLE `templates` 
    CHANGE COLUMN `organization_id` `workspace_id` BIGINT UNSIGNED NULL;

-- Update campaigns table
ALTER TABLE `campaigns` 
    CHANGE COLUMN `organization_id` `workspace_id` BIGINT UNSIGNED NULL;

-- Update campaign_logs table
ALTER TABLE `campaign_logs` 
    CHANGE COLUMN `organization_id` `workspace_id` BIGINT UNSIGNED NULL;

-- Update chats table
ALTER TABLE `chats` 
    CHANGE COLUMN `organization_id` `workspace_id` BIGINT UNSIGNED NULL;

-- Update tickets table
ALTER TABLE `tickets` 
    CHANGE COLUMN `organization_id` `workspace_id` BIGINT UNSIGNED NULL;

-- Update canned_replies table
ALTER TABLE `canned_replies` 
    CHANGE COLUMN `organization_id` `workspace_id` BIGINT UNSIGNED NULL;

-- Update auto_replies table
ALTER TABLE `auto_replies` 
    CHANGE COLUMN `organization_id` `workspace_id` BIGINT UNSIGNED NULL;

-- Update webhooks table
ALTER TABLE `webhooks` 
    CHANGE COLUMN `organization_id` `workspace_id` BIGINT UNSIGNED NULL;

-- Update plugins table
ALTER TABLE `plugins` 
    CHANGE COLUMN `organization_id` `workspace_id` BIGINT UNSIGNED NULL;

-- Update contact_fields table
ALTER TABLE `contact_fields` 
    CHANGE COLUMN `organization_id` `workspace_id` BIGINT UNSIGNED NULL;

-- Update flows table (if exists)
ALTER TABLE `flows` 
    CHANGE COLUMN `organization_id` `workspace_id` BIGINT UNSIGNED NULL;

-- Update flow_templates table (if exists)
ALTER TABLE `flow_templates` 
    CHANGE COLUMN `organization_id` `workspace_id` BIGINT UNSIGNED NULL;

-- Update basic_automations table (if exists)
ALTER TABLE `basic_automations` 
    CHANGE COLUMN `organization_id` `workspace_id` BIGINT UNSIGNED NULL;

-- ============================================================
-- Step 3: Rename Foreign Key Constraints (Drop and Recreate)
-- ============================================================

-- Teams table
ALTER TABLE `teams` DROP FOREIGN KEY IF EXISTS `teams_organization_id_foreign`;
ALTER TABLE `teams` 
    ADD CONSTRAINT `teams_workspace_id_foreign` 
    FOREIGN KEY (`workspace_id`) REFERENCES `workspaces`(`id`) ON DELETE CASCADE;

-- Subscriptions table
ALTER TABLE `subscriptions` DROP FOREIGN KEY IF EXISTS `subscriptions_organization_id_foreign`;
ALTER TABLE `subscriptions` 
    ADD CONSTRAINT `subscriptions_workspace_id_foreign` 
    FOREIGN KEY (`workspace_id`) REFERENCES `workspaces`(`id`) ON DELETE CASCADE;

-- Contacts table
ALTER TABLE `contacts` DROP FOREIGN KEY IF EXISTS `contacts_organization_id_foreign`;
ALTER TABLE `contacts` 
    ADD CONSTRAINT `contacts_workspace_id_foreign` 
    FOREIGN KEY (`workspace_id`) REFERENCES `workspaces`(`id`) ON DELETE CASCADE;

-- Contact Groups table
ALTER TABLE `contact_groups` DROP FOREIGN KEY IF EXISTS `contact_groups_organization_id_foreign`;
ALTER TABLE `contact_groups` 
    ADD CONSTRAINT `contact_groups_workspace_id_foreign` 
    FOREIGN KEY (`workspace_id`) REFERENCES `workspaces`(`id`) ON DELETE CASCADE;

-- Templates table
ALTER TABLE `templates` DROP FOREIGN KEY IF EXISTS `templates_organization_id_foreign`;
ALTER TABLE `templates` 
    ADD CONSTRAINT `templates_workspace_id_foreign` 
    FOREIGN KEY (`workspace_id`) REFERENCES `workspaces`(`id`) ON DELETE CASCADE;

-- Campaigns table
ALTER TABLE `campaigns` DROP FOREIGN KEY IF EXISTS `campaigns_organization_id_foreign`;
ALTER TABLE `campaigns` 
    ADD CONSTRAINT `campaigns_workspace_id_foreign` 
    FOREIGN KEY (`workspace_id`) REFERENCES `workspaces`(`id`) ON DELETE CASCADE;

-- Chats table
ALTER TABLE `chats` DROP FOREIGN KEY IF EXISTS `chats_organization_id_foreign`;
ALTER TABLE `chats` 
    ADD CONSTRAINT `chats_workspace_id_foreign` 
    FOREIGN KEY (`workspace_id`) REFERENCES `workspaces`(`id`) ON DELETE CASCADE;

-- Tickets table
ALTER TABLE `tickets` DROP FOREIGN KEY IF EXISTS `tickets_organization_id_foreign`;
ALTER TABLE `tickets` 
    ADD CONSTRAINT `tickets_workspace_id_foreign` 
    FOREIGN KEY (`workspace_id`) REFERENCES `workspaces`(`id`) ON DELETE CASCADE;

-- Canned Replies table
ALTER TABLE `canned_replies` DROP FOREIGN KEY IF EXISTS `canned_replies_organization_id_foreign`;
ALTER TABLE `canned_replies` 
    ADD CONSTRAINT `canned_replies_workspace_id_foreign` 
    FOREIGN KEY (`workspace_id`) REFERENCES `workspaces`(`id`) ON DELETE CASCADE;

-- Auto Replies table
ALTER TABLE `auto_replies` DROP FOREIGN KEY IF EXISTS `auto_replies_organization_id_foreign`;
ALTER TABLE `auto_replies` 
    ADD CONSTRAINT `auto_replies_workspace_id_foreign` 
    FOREIGN KEY (`workspace_id`) REFERENCES `workspaces`(`id`) ON DELETE CASCADE;

-- Webhooks table
ALTER TABLE `webhooks` DROP FOREIGN KEY IF EXISTS `webhooks_organization_id_foreign`;
ALTER TABLE `webhooks` 
    ADD CONSTRAINT `webhooks_workspace_id_foreign` 
    FOREIGN KEY (`workspace_id`) REFERENCES `workspaces`(`id`) ON DELETE CASCADE;

-- ============================================================
-- Step 4: Update Indexes
-- ============================================================

-- Recreate indexes with new column names
ALTER TABLE `teams` DROP INDEX IF EXISTS `teams_organization_id_index`;
ALTER TABLE `teams` ADD INDEX `teams_workspace_id_index` (`workspace_id`);

ALTER TABLE `subscriptions` DROP INDEX IF EXISTS `subscriptions_organization_id_index`;
ALTER TABLE `subscriptions` ADD INDEX `subscriptions_workspace_id_index` (`workspace_id`);

ALTER TABLE `contacts` DROP INDEX IF EXISTS `contacts_organization_id_index`;
ALTER TABLE `contacts` ADD INDEX `contacts_workspace_id_index` (`workspace_id`);

ALTER TABLE `contact_groups` DROP INDEX IF EXISTS `contact_groups_organization_id_index`;
ALTER TABLE `contact_groups` ADD INDEX `contact_groups_workspace_id_index` (`workspace_id`);

ALTER TABLE `templates` DROP INDEX IF EXISTS `templates_organization_id_index`;
ALTER TABLE `templates` ADD INDEX `templates_workspace_id_index` (`workspace_id`);

ALTER TABLE `campaigns` DROP INDEX IF EXISTS `campaigns_organization_id_index`;
ALTER TABLE `campaigns` ADD INDEX `campaigns_workspace_id_index` (`workspace_id`);

ALTER TABLE `chats` DROP INDEX IF EXISTS `chats_organization_id_index`;
ALTER TABLE `chats` ADD INDEX `chats_workspace_id_index` (`workspace_id`);

-- ============================================================
-- Step 5: Update migrations table to reflect the changes
-- ============================================================

UPDATE `migrations` 
SET `migration` = REPLACE(`migration`, 'organizations', 'workspaces')
WHERE `migration` LIKE '%organization%';

-- Enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- Verification Queries (Optional - Run After Migration)
-- ============================================================

-- Show all tables with 'workspace' in name
-- SHOW TABLES LIKE '%workspace%';

-- Show all columns with 'workspace' in name
-- SELECT TABLE_NAME, COLUMN_NAME 
-- FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_SCHEMA = 'blazz' 
-- AND COLUMN_NAME LIKE '%workspace%';

-- ============================================================
-- End of Migration Script
-- ============================================================
