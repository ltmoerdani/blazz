<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if constraints already exist
        $uniqueConstraintExists = !empty(DB::select("SHOW INDEX FROM whatsapp_accounts WHERE Key_name = 'unique_active_phone_workspace'"));
        $sessionIndexExists = !empty(DB::select("SHOW INDEX FROM whatsapp_accounts WHERE Key_name = 'idx_session_id'"));

        if ($uniqueConstraintExists && $sessionIndexExists) {
            echo "✅ All constraints already exist, skipping...\n";
            return;
        }

        // Step 1: Mark orphaned sessions as disconnected
        DB::statement("
            CREATE TEMPORARY TABLE temp_keep_ids AS
            SELECT MIN(id) as id
            FROM whatsapp_accounts 
            WHERE phone_number IS NOT NULL
            GROUP BY phone_number, workspace_id
        ");
        
        DB::statement("
            UPDATE whatsapp_accounts 
            SET status = 'disconnected',
                updated_at = NOW()
            WHERE status IN ('connected', 'qr_scanning')
            AND id NOT IN (SELECT id FROM temp_keep_ids)
        ");
        
        DB::statement("DROP TEMPORARY TABLE temp_keep_ids");

        // Step 2: Clean up empty phone number duplicates
        DB::statement("
            CREATE TEMPORARY TABLE temp_keep_empty AS
            SELECT MAX(id) as id
            FROM whatsapp_accounts 
            WHERE phone_number IS NULL OR phone_number = ''
            GROUP BY workspace_id, status
        ");
        
        DB::statement("
            DELETE FROM whatsapp_accounts
            WHERE (phone_number IS NULL OR phone_number = '')
            AND id NOT IN (SELECT id FROM temp_keep_empty)
        ");
        
        DB::statement("DROP TEMPORARY TABLE temp_keep_empty");

        // Step 3: Add unique constraint if not exists
        if (!$uniqueConstraintExists && Schema::hasColumn('whatsapp_accounts', 'phone_number')) {
            // Drop old phone_number index if exists
            try {
                Schema::table('whatsapp_accounts', function (Blueprint $table) {
                    $table->dropIndex(['phone_number']);
                });
            } catch (\Exception $e) {
                // Index might not exist
            }

            Schema::table('whatsapp_accounts', function (Blueprint $table) {
                $table->unique(['phone_number', 'workspace_id', 'status'], 'unique_active_phone_workspace');
            });
            
            echo "✅ Added unique constraint\n";
        }

        // Step 4: Add session_id index if not exists  
        if (!$sessionIndexExists && Schema::hasColumn('whatsapp_accounts', 'session_id')) {
            Schema::table('whatsapp_accounts', function (Blueprint $table) {
                $table->index('session_id', 'idx_session_id');
            });
            
            echo "✅ Added session_id index\n";
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            $table->dropUnique('unique_active_phone_workspace');
            $table->dropIndex('idx_session_id');
        });
    }
};
