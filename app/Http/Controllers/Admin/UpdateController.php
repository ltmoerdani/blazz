<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\StoreConfig;
use App\Http\Resources\AddonResource;
use App\Models\Addon;
use App\Models\Setting;
use App\Services\SettingService;
use App\Services\UpdateService;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Hash;
use Helper;
use Session;
use Validator;
use ZipArchive;

class UpdateController extends BaseController
{
    private $settingService;

    public function __construct(SettingService $settingService)
    {
        $this->settingService = $settingService;
    }

    public function index(Request $request){
        $data['config'] = Setting::get();
        $addons = Addon::where('update_available', 1)->paginate(6);
        $data['rows'] = AddonResource::collection($addons);

        return Inertia::render('Admin/Setting/Updates', $data);
    }

    public function checkUpdate(Request $request)
    {
        // External update checking is disabled for security
        return Redirect::back()->with('info', 'External update checking has been disabled for security reasons.');
    }

    public function update(Request $request)
    {
        try {
            // External updates are disabled for production security
            // Manual updates should be performed through official channels
            
            return Redirect::back()->with('error', 'External updates have been disabled for security. Please update manually through official channels.');
            
        } catch (\Exception $exception) {
            Log::error('Update attempt blocked: ' . $exception->getMessage());
            return Redirect::back()->with('error', 'Update functionality is disabled for security reasons.');
        }
    }

    protected function removeDirectory($directory)
    {
        if (is_dir($directory)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            
            foreach ($iterator as $file) {
                $action = ($file->isDir() ? 'rmdir' : 'unlink');
                $action($file->getRealPath());
            }
            rmdir($directory);
        }
    }
}
