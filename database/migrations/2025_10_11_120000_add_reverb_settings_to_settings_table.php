<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $defaults = [
            'reverb_app_id' => env('REVERB_APP_ID', 'default-app-id'),
            'reverb_app_key' => env('REVERB_APP_KEY', 'base64:VGVzdEtleUZvckRldmVsb3BtZW50'),
            'reverb_app_secret' => env('REVERB_APP_SECRET', 'base64:VGVzdFNlY3JldEZvckRldmVsb3BtZW50'),
            'reverb_host' => env('REVERB_HOST', '127.0.0.1'),
            'reverb_port' => (string) env('REVERB_PORT', 8080),
            'reverb_scheme' => env('REVERB_SCHEME', 'http'),
        ];

        DB::table('settings')->updateOrInsert(
            ['key' => 'broadcast_driver'],
            ['value' => 'reverb']
        );

        foreach ($defaults as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'reverb_app_id',
            'reverb_app_key',
            'reverb_app_secret',
            'reverb_host',
            'reverb_port',
            'reverb_scheme',
        ])->delete();

        DB::table('settings')->updateOrInsert(
            ['key' => 'broadcast_driver'],
            ['value' => 'pusher']
        );
    }
};
