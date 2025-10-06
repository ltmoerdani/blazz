<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\StoreWorkspace;
use App\Services\WorkspaceService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkspaceController extends BaseController
{
    private $WorkspaceService;
    private $role;

    /**
     * WorkspaceController constructor.
     *
     * @param UserService $WorkspaceService
     */
    public function __construct()
    {
        $this->WorkspaceService = new WorkspaceService();
    }

    /**
     * Display a listing of workspaces.
     *
     * @param Request $request
     * @return \Inertia\Response
     */
    public function index(Request $request)
    {
        return Inertia::render('Admin/workspace/Index', [
            'title' => __('Workspaces'),
            'allowCreate' => true,
            'rows' => $this->WorkspaceService->get($request), 
            'filters' => $request->all()
        ]);
    }

    /**
     * Display the specified workspace.
     *
     * @param string $uuid
     * @return \Inertia\Response
     */
    public function show(Request $request, $uuid = null, $mode = null)
    {
        $res = $this->WorkspaceService->getByUuid($request, $uuid);
        return Inertia::render('Admin/workspace/Show', [
            'title' => __('workspace'),
            'workspace' => $res['workspace'], 
            'users' => $res['users'],
            'plans' => $res['plans'], 
            'invoices' => $res['billing'],
            'mode' => $mode,
            'filters' => $request->all()
        ]);
    }

    /**
     * Display Form
     *
     * @param $request
     */
    public function create(Request $request)
    {
        $res = $this->WorkspaceService->getByUuid($request);
        return Inertia::render('Admin/workspace/Show', [
            'title' => __('Create Org.'),
            'workspace' => $res['workspace'], 
            'users' => $res['users'],
            'plans' => $res['plans'], 
            'invoices' => $res['billing'],
            'filters' => $request->all()
        ]);
    }

    /**
     * Store a newly created workspace.
     *
     * @param Request $request
     */
    public function store(StoreWorkspace $request)
    {
        $this->WorkspaceService->store($request);

        return redirect('/admin/workspaces')->with(
            'status', [
                'type' => 'success', 
                'message' => __('workspace created successfully!')
            ]
        );
    }

    /**
     * Update the specified workspace.
     *
     * @param Request $request
     */
    public function update(StoreWorkspace $request, $uuid)
    {
        $this->WorkspaceService->update($request, $uuid);

        return redirect('/admin/workspaces/'.$uuid)->with(
            'status', [
                'type' => 'success', 
                'message' => __('workspace updated successfully!')
            ]
        );
    }

    /**
     * Remove the specified workspace.
     *
     * @param String $uuid
     */
    public function destroy($uuid)
    {
        $query = $this->WorkspaceService->destroy($uuid);

        return back()->with(
            'status', [
                'type' => $query ? 'success' : 'error', 
                'message' => $query ? __('workspace deleted successfully!') : __('This workspace does not exist!')
            ]
        );
    }
}