<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorWhenEnforced
{
    /**
     * When the instance enforces two-factor, send authenticated users who
     * have not set it up to the security page. The security page, the 2FA
     * management endpoints and logout stay reachable so they can comply.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user
            && ! $user->two_factor_confirmed_at
            && Setting::get('mfaEnforced', 'false') === 'true'
            && ! $request->is('security', 'user/*two-factor*', 'logout', 'livewire/*')) {
            return redirect('/security');
        }

        return $next($request);
    }
}
