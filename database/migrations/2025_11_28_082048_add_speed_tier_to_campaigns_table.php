<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add speed_tier column to campaigns table
 * 
 * User-selectable speed tier for campaign message sending.
 * Tiers: 1=Paranoid, 2=Safe(default), 3=Balanced, 4=Fast, 5=Aggressive
 * 
 * @see docs/broadcast/relay/02-anti-ban-system-design.md
 * @see docs/broadcast/relay/03-implementation-guide.md
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            // Speed tier selection (1-5)
            // Default: 2 (Safe/Recommended)
            $table->unsignedTinyInteger('speed_tier')
                  ->default(2)
                  ->after('status')
                  ->comment('User-selected speed tier: 1=Paranoid, 2=Safe, 3=Balanced, 4=Fast, 5=Aggressive');
            
            // Index for filtering/reporting
            $table->index('speed_tier');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropIndex(['speed_tier']);
            $table->dropColumn('speed_tier');
        });
    }
};
