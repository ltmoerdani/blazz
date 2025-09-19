<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CheckAppStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $currentPath = $request->path();

        // Handle installation routes
        if (Str::startsWith($currentPath, 'install')) {
            if ($this->isInstalled()) {
                abort(404);
            }
            return $next($request);
        }

        // Check if application is installed
        if (!$this->isInstalled()) {
            return redirect()->route('install');
        }

        // Allow all other routes to proceed normally
        // Update functionality is now optional and not forced
        return $next($request);
    }

    /**
     * Checks if the application has been installed.
     *
     * @return bool
     */
    public function isInstalled(): bool
    {
        return file_exists(storage_path('installed'));
    }
}
