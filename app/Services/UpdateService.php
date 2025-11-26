<?php

namespace App\Services;

use App\Models\Chat;
use App\Models\ChatLog;
use App\Models\ChatNote;
use App\Models\ChatTicketLog;
use App\Models\SeederHistory;
use App\Services\ModuleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class UpdateService
{
    private $workspaceId;

    public function __construct($workspaceId = null)
    {
        // Backward compatible: fallback to session if not provided
        $this->workspaceId = $workspaceId ?? session('current_workspace');
    }

    public function migrate(Request $request, $version){
        Artisan::call('db:seed');

        return true;
    }
}