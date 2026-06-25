<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSetupComplete
{
    /**
     * Until the instance is set up, send web traffic to the setup wizard.
     * The admin panel (own auth), the setup route itself, Livewire and the
     * health check stay reachable so the wizard can run.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('admin', 'admin/*', 'setup', 'livewire/*', 'up') || $request->expectsJson()) {
            return $next($request);
        }

        if (Setting::get('setupComplete') !== 'true') {
            return redirect('/setup');
        }

        return $next($request);
    }
}
