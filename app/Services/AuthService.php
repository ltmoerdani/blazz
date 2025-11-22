<?php

namespace App\Services;

use App\Http\Resources\UserResource;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthService
{
    private $user;
    private $workspaceId;

    public function __construct($user, $workspaceId = null)
    {
        $this->user = $user;
        // Backward compatible: fallback to session if not provided
        $this->workspaceId = $workspaceId ?? session('current_workspace');
    }

    public function authenticateSession($request)
    {
        if($this->user->role != 'user'){
            Auth::guard('admin')->attempt(['email' => $request->email, 'password' => $request->password]);
        } else {
            Auth::guard('user')->attempt(['email' => $request->email, 'password' => $request->password]);
            $team = Team::where('user_id', $this->user->id)->first();

            session()->put('current_workspace', $team->Workspace_id);
        }
    }
}
