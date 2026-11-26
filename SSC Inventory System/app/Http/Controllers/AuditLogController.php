<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = AuditLog::query()->with('user');

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search): void {
                $q->where('action', 'like', "%{$search}%")
                    ->orWhere('record_label', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('user_name', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        if ($userId = $request->input('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($module = $request->input('module')) {
            $query->where('module', $module);
        }

        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $since = now()->subHours(24);
        $failedLogins24h = AuditLog::whereIn('action', ['LOGIN_FAILED', 'ACCOUNT_LOCKED'])->where('created_at', '>=', $since)->count();
        $lockedAccounts = User::where('locked_until', '>', now())->count();
        $accessDenied24h = AuditLog::where('action', 'ACCESS_DENIED')->where('created_at', '>=', $since)->count();
        $stepUp24h = AuditLog::whereIn('action', ['STEP_UP_VERIFIED', 'STEP_UP_FAILED'])->where('created_at', '>=', $since)->count();

        return view('audit-logs.index', [
            'logs' => $query->latest('created_at')->latest('id')->paginate(25)->withQueryString(),
            'users' => User::orderBy('name')->get(['id', 'name']),
            'modules' => AuditLog::distinct()->orderBy('module')->pluck('module'),
            'securityMetrics' => [
                'failed_logins_24h' => $failedLogins24h,
                'locked_accounts' => $lockedAccounts,
                'access_denied_24h' => $accessDenied24h,
                'step_up_24h' => $stepUp24h,
                'spike_detected' => $failedLogins24h >= 5,
            ],
        ]);
    }
}
