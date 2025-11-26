<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            if (!Schema::hasColumn('campaigns', 'campaign_type')) {
                $table->string('campaign_type')->default('template')->after('name');
            }
            if (!Schema::hasColumn('campaigns', 'preferred_provider')) {
                $table->string('preferred_provider')->default('webjs')->after('campaign_type');
            }
            if (!Schema::hasColumn('campaigns', 'whatsapp_account_id')) {
                $table->integer('whatsapp_account_id')->nullable()->after('contact_group_id');
            }
            
            // Direct message fields
            if (!Schema::hasColumn('campaigns', 'message_content')) {
                $table->text('message_content')->nullable()->after('template_id');
            }
            if (!Schema::hasColumn('campaigns', 'header_type')) {
                $table->string('header_type')->nullable()->after('message_content');
            }
            if (!Schema::hasColumn('campaigns', 'header_text')) {
                $table->string('header_text')->nullable()->after('header_type');
            }
            if (!Schema::hasColumn('campaigns', 'header_media')) {
                $table->string('header_media')->nullable()->after('header_text');
            }
            if (!Schema::hasColumn('campaigns', 'body_text')) {
                $table->text('body_text')->nullable()->after('header_media');
            }
            if (!Schema::hasColumn('campaigns', 'footer_text')) {
                $table->string('footer_text')->nullable()->after('body_text');
            }
            if (!Schema::hasColumn('campaigns', 'buttons_data')) {
                $table->json('buttons_data')->nullable()->after('footer_text');
            }
            
            // Performance stats
            if (!Schema::hasColumn('campaigns', 'messages_sent')) {
                $table->integer('messages_sent')->default(0)->after('status');
            }
            if (!Schema::hasColumn('campaigns', 'messages_delivered')) {
                $table->integer('messages_delivered')->default(0)->after('messages_sent');
            }
            if (!Schema::hasColumn('campaigns', 'messages_read')) {
                $table->integer('messages_read')->default(0)->after('messages_delivered');
            }
            if (!Schema::hasColumn('campaigns', 'messages_failed')) {
                $table->integer('messages_failed')->default(0)->after('messages_read');
            }
            
            // Timestamps and logs
            if (!Schema::hasColumn('campaigns', 'started_at')) {
                $table->dateTime('started_at')->nullable()->after('scheduled_at');
            }
            if (!Schema::hasColumn('campaigns', 'completed_at')) {
                $table->dateTime('completed_at')->nullable()->after('started_at');
            }
            if (!Schema::hasColumn('campaigns', 'error_message')) {
                $table->text('error_message')->nullable()->after('completed_at');
            }
            
            // Soft deletes
            if (!Schema::hasColumn('campaigns', 'deleted_at')) {
                $table->softDeletes();
            }
            if (!Schema::hasColumn('campaigns', 'deleted_by')) {
                $table->integer('deleted_by')->nullable();
            }
            
            // Make template_id nullable since direct messages don't use it
            $table->integer('template_id')->nullable()->change();
            $table->integer('contact_group_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn([
                'campaign_type',
                'preferred_provider',
                'whatsapp_account_id',
                'message_content',
                'header_type',
                'header_text',
                'header_media',
                'body_text',
                'footer_text',
                'buttons_data',
                'messages_sent',
                'messages_delivered',
                'messages_read',
                'messages_failed',
                'started_at',
                'completed_at',
                'error_message',
                'deleted_by'
            ]);
            $table->dropSoftDeletes();
            
            // Revert nullable changes (careful with data loss if reverting)
            // $table->integer('template_id')->nullable(false)->change();
            // $table->integer('contact_group_id')->nullable(false)->change();
        });
    }
};
