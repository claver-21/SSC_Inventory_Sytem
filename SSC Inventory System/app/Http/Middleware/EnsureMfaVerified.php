<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMfaVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! config('auth.mfa_enabled', true)) {
            return $next($request);
        }

        if ($request->session()->get('mfa_verified') === true && $user->mfa_enabled) {
            return $next($request);
        }

        $request->session()->put('mfa_pending_user_id', $user->id);

        if (! $user->mfa_enabled) {
            return redirect()->route('mfa.setup');
        }

        return redirect()->route('mfa.verify');
    }
}
