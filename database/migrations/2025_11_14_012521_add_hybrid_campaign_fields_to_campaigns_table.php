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
            // Campaign type: template-based or direct message
            $table->enum('campaign_type', ['template', 'direct'])->default('template')->after('name');

            // Make template_id nullable (only required for template campaigns)
            $table->integer('template_id')->nullable()->change();

            // Direct message fields
            $table->text('message_content')->nullable()->after('template_id');
            $table->string('header_type', 50)->nullable()->after('message_content'); // text, image, document, video
            $table->text('header_text')->nullable()->after('header_type');
            $table->string('header_media')->nullable()->after('header_text'); // media file path
            $table->text('body_text')->nullable()->after('header_media');
            $table->text('footer_text')->nullable()->after('body_text');
            $table->json('buttons_data')->nullable()->after('footer_text');

            // Provider selection fields
            $table->enum('preferred_provider', ['webjs', 'meta_api'])->default('webjs')->after('contact_group_id');
            $table->integer('whatsapp_session_id')->nullable()->after('preferred_provider');

            // Campaign performance counters
            $table->integer('messages_sent')->default(0)->after('status');
            $table->integer('messages_delivered')->default(0)->after('messages_sent');
            $table->integer('messages_read')->default(0)->after('messages_delivered');
            $table->integer('messages_failed')->default(0)->after('messages_read');

            // Processing fields
            $table->timestamp('started_at')->nullable()->after('scheduled_at');
            $table->timestamp('completed_at')->nullable()->after('started_at');
            $table->text('error_message')->nullable()->after('completed_at');

            // Add indexes for performance
            $table->index(['campaign_type', 'status']);
            $table->index(['workspace_id', 'status']);
            $table->index(['preferred_provider', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            // Drop new columns
            $table->dropColumn([
                'campaign_type',
                'message_content',
                'header_type',
                'header_text',
                'header_media',
                'body_text',
                'footer_text',
                'buttons_data',
                'preferred_provider',
                'whatsapp_session_id',
                'messages_sent',
                'messages_delivered',
                'messages_read',
                'messages_failed',
                'started_at',
                'completed_at',
                'error_message'
            ]);

            // Make template_id not nullable again
            $table->integer('template_id')->nullable(false)->change();
        });
    }
};
