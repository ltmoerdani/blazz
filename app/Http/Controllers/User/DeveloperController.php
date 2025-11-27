<?php

namespace App\Http\Controllers\User;

use DB;
use App\Http\Controllers\Controller as BaseController;
use App\Helpers\CustomHelper;
use App\Http\Resources\DeveloperResource;
use App\Models\Addon;
use App\Models\WorkspaceApiKey;
use App\Models\Setting;
use App\Services\WorkspaceApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;

class DeveloperController extends BaseController
{
    private $WorkspaceApiService;

    public function __construct(WorkspaceApiService $WorkspaceApiService)
    {
        $this->WorkspaceApiService = $WorkspaceApiService;
    }

    public function index(){
        $rows = WorkspaceApiKey::where('workspace_id', $this->getWorkspaceId())
            ->where('deleted_at', null)
            ->paginate(9);
        $data['rows'] = DeveloperResource::collection($rows);
        $data['title'] = __('API keys');
        $data['url'] = url('/');
        $data['apirequests'] = config('apiguide');
        $data['webhookModule'] = Addon::where('name', 'Webhooks')->where('status', 1)->where('is_active', 1)->exists();
        $webhookAddon = Addon::where('name', 'Webhooks')->where('status', 1)->where('is_active', 1)->exists();
        $data['webhookModule'] = $webhookAddon && CustomHelper::isModuleEnabled('Webhooks');

        return Inertia::render('User/Developer/Index', $data);
    }

    public function store(Request $request){
        $this->WorkspaceApiService->generate($request);

        return Redirect::back()->with(
            'status', [
                'type' => 'success',
                'message' => __('Your API token has been generated successfully')
            ]
        );
    }

    public function delete($uuid)
    {
        $this->WorkspaceApiService->destroy($uuid);
    }
}
