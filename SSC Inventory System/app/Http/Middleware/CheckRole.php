<?php

namespace App\Http\Middleware;

use App\Services\AuditLogService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function __construct(protected AuditLogService $auditLog)
    {
    }

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Invalid email or password, or account temporarily locked.',
            ]);
        }

        if (! empty($roles) && ! $user->hasAnyRole($roles)) {
            $this->auditLog->log(
                action: 'ACCESS_DENIED',
                module: 'security',
                auditable: $user,
                newValues: [
                    'method' => $request->method(),
                    'path' => '/' . ltrim($request->path(), '/'),
                    'required_roles' => $roles,
                ],
                description: sprintf(
                    'Role access denied for %s (%s) on %s /%s.',
                    $user->email,
                    $user->role?->slug ?? 'none',
                    $request->method(),
                    ltrim($request->path(), '/')
                ),
                recordLabel: $user->email,
                actor: $user
            );

            abort(403, 'Your role is not authorized to perform this action.');
        }

        return $next($request);
    }
}
