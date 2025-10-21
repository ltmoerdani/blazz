<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\WorkspaceApiKey;
use Illuminate\Http\Request;

class AuthenticateBearerToken
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['error' => __('Unauthorized. Bearer Token is missing.')], 401);
        }

        $WorkspaceApiKey = WorkspaceApiKey::where('token', $token)->whereNull('deleted_at')->first();

        if (!$WorkspaceApiKey) {
            return response()->json(['error' => __('Unauthorized. Invalid Bearer Token.')], 401);
        }

        // Attach workspace to the request
        $request->merge(['workspace' => $WorkspaceApiKey->Workspace_id]);

        return $next($request);
    }
}
