<?php

namespace App\Console\Commands;

use App\Exceptions\SecurityDisabledException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
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

    /**
     * Fetch API response - Disabled for security
     *
     * @param string $url
     * @return array|null
     */
    private function fetchApiResponse(string $url): ?array
    {
        // External API calls disabled for security
        throw new SecurityDisabledException('External API calls have been disabled for security. Please check updates manually.');
    }

    /**
     * Check Blazz updates - Disabled for security
     *
     * @param array|null $blazz
     * @return void
     */
    private function checkBlazzUpdate(?array $blazz): void
    {
        // External update checking disabled for security
        throw new SecurityDisabledException('External update checking has been disabled for security. Please check updates manually.');
    }

    /**
     * Check addon updates - Disabled for security
     *
     * @param array $addons
     * @return void
     */
    private function checkAddonUpdates(array $addons): void
    {
        // External addon update checking disabled for security
        throw new SecurityDisabledException('External addon update checking has been disabled for security. Please check addon updates manually.');
    }
}
