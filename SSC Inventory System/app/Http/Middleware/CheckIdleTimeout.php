<?php

namespace App\Http\Middleware;

use App\Services\AuditLogService;
use App\Services\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckIdleTimeout
{
    public function __construct(
        protected SettingsService $settings,
        protected AuditLogService $auditLog
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $isPrivileged = $user->isAdmin();
            $minutes = $isPrivileged
                ? (int) $this->settings->get('admin_idle_timeout_minutes', 10)
                : (int) $this->settings->get('idle_timeout_minutes', 15);
            $timeoutSeconds = max(60, $minutes * 60);

            $lastActivity = (int) $request->session()->get('last_activity_at', 0);
            $now = now()->timestamp;

            if ($lastActivity > 0 && ($now - $lastActivity) > $timeoutSeconds) {
                $this->auditLog->log(
                    action: 'SESSION_IDLE_TIMEOUT',
                    module: 'security',
                    auditable: $user,
                    description: "Session timed out after {$minutes} minutes of inactivity.",
                    recordLabel: $user->email,
                    actor: $user
                );

                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors([
                    'email' => 'Your session expired due to inactivity. Please sign in again.',
                ]);
            }

            $request->session()->put('last_activity_at', $now);

            if ($user->last_seen_at === null || $user->last_seen_at->lt(now()->subMinute())) {
                $user->forceFill(['last_seen_at' => now()])->saveQuietly();
            }
        }

        return $next($request);
    }
}
