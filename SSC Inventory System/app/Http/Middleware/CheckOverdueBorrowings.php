<?php

namespace App\Http\Middleware;

use App\Services\OverdueMonitoringService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CheckOverdueBorrowings
{
    public function __construct(protected OverdueMonitoringService $overdueService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            if (! $user->is_active) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors([
                    'email' => 'Your account has been deactivated. Contact the SSC System Administrator.',
                ]);
            }

            if ($request->isMethod('GET') && ! $request->expectsJson()) {
                $cacheKey = 'pcb_ssc_overdue_checked_' . now()->format('YmdHi');
                if (! Cache::has('pcb_ssc_overdue_last_run')) {
                    Cache::put('pcb_ssc_overdue_last_run', $cacheKey, now()->addMinutes(10));
                    try {
                        $this->overdueService->run();
                    } catch (Throwable) {
                        // Fail safely without blocking page load if tables are not migrated yet
                    }
                }
            }
        }

        return $next($request);
    }
}
