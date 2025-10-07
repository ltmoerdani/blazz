<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CheckModuleUpdates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'modules:check-updates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for module updates - DISABLED for security';

    /**
     * Execute the console command - Disabled for security
     *
     * @return int
     */
    public function handle()
    {
        $this->error('External module update checking has been disabled for security.');
        $this->info('To check for updates, please visit the official project repository manually.');
        
        // Update last check timestamp to prevent automated calls
        DB::table('settings')->updateOrInsert(
            ['key' => 'last_update_check'],
            ['value' => Carbon::now()]
        );
        
        // Disable update availability flags
        DB::table('settings')->updateOrInsert(
            ['key' => 'is_update_available'],
            ['value' => 0]
        );

        return 1; // Return error code to indicate feature disabled
    }
}
