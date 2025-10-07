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
    private $WorkspaceService;

    /**
     * WorkspaceController constructor.
     *
     * @param UserService $WorkspaceService
     */
    public function __construct()
    {
        $this->WorkspaceService = new WorkspaceService();
    }
    
    public function index(){
        $data['workspaces'] = Team::with('workspace')->where('user_id', Auth::id())->get();
        
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
        $workspace = $this->WorkspaceService->store($request);

        if($workspace){
            session()->put('current_workspace', $workspace->id);

            return to_route('dashboard');
        }
    }
}
