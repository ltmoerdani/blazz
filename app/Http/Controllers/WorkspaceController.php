<?php

namespace App\Http\Controllers;

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
    private $organizationService;

    /**
     * WorkspaceController constructor.
     *
     * @param UserService $organizationService
     */
    public function __construct()
    {
        $this->organizationService = new WorkspaceService();
    }
    
    public function index(){
        $data['organizations'] = Team::with('workspace')->where('user_id', Auth::id())->get();
        
        return Inertia::render('User/WorkspaceSelect', $data);
    }

    public function select(Request $request)
    {
        $data = [
            'workspaces' => Workspace::all(),
        ];

        return Inertia::render('User/WorkspaceSelect', $data);
    }

    public function store(StoreUserWorkspace $request)
    {
        $workspace = $this->organizationService->store($request);

        if($workspace){
            session()->put('current_workspace', $workspace->id);

            return to_route('dashboard');
        }
    }
}
