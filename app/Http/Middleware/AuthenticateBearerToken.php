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

        $organizationApiKey = WorkspaceApiKey::where('token', $token)->whereNull('deleted_at')->first();

        if (!$organizationApiKey) {
            return response()->json(['error' => __('Unauthorized. Invalid Bearer Token.')], 401);
        }

        // Attach workspace to the request
        $request->merge(['workspace' => $organizationApiKey->organization_id]);

        return $next($request);
    }
}