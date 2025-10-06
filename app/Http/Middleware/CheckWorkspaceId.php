<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use App\Helpers\SubscriptionHelper;
use Illuminate\Support\Facades\Auth;

class CheckWorkspaceId
{
    public function handle($request, Closure $next)
    {
        if (!session()->has('current_workspace')) {
            return redirect()->route('user.workspace.index');
        }

        return $next($request);
    }
}
