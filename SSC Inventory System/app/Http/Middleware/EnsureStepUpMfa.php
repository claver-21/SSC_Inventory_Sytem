<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStepUpMfa
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

        $verifiedAt = (int) $request->session()->get('step_up_verified_at', 0);
        if ($verifiedAt > 0 && (now()->timestamp - $verifiedAt) <= 300) {
            return $next($request);
        }

        $intended = $request->isMethod('GET')
            ? $request->fullUrl()
            : url()->previous();

        $request->session()->put('step_up_intended_url', $intended);

        return redirect()
            ->route('mfa.step-up')
            ->with('error', 'Step-up MFA verification is required before performing this sensitive action. Please enter your 6-digit code and retry.');
    }
}
