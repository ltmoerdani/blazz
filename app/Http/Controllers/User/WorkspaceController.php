<?php

namespace App\Http\Controllers\User;

use DB;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\StoreUserWorkspace;
use App\Models\Workspace;
use App\Models\Team;
use App\Services\WorkspaceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class WorkspaceController extends BaseController
{
    public function __construct(
        private WorkspaceService $workspaceService
    ) {
        // Constructor injection - no manual instantiation
    }
    
    public function index(){
        $data['workspaces'] = Team::with('workspace')->where('user_id', Auth::id())->get();
        
        return Inertia::render('User/WorkspaceSelect', $data);
    }

    public function selectWorkspace(Request $request){
        $workspace = Workspace::where('uuid', $request->uuid)
            ->select(['id', 'uuid', 'name']) // Only select needed columns
            ->first();

        if($workspace){
            session()->put('current_workspace', $workspace->id);
        }

        return to_route('dashboard');
    }

    public function store(StoreUserWorkspace $request)
    {
        $workspace = $this->workspaceService->store($request);

        if($workspace){
            session()->put('current_workspace', $workspace->id);

            return to_route('dashboard');
        }
    }
}
