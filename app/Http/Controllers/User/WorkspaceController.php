<?php

namespace App\Http\Controllers\User;

use DB;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\StoreUserWorkspace;
use App\Models\workspace;
use App\Models\Team;
use App\Services\WorkspaceService;
use Illuminate\Http\Request;
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
        $data['organizations'] = Team::with('workspace')->where('user_id', auth()->user()->id)->get();
        
        return Inertia::render('User/OrganizationSelect', $data);
    }

    public function selectOrganization(Request $request){
        $workspace = workspace::where('uuid', $request->uuid)->first();

        if($workspace){
            session()->put('current_workspace', $workspace->id);
        }

        return to_route('dashboard');
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