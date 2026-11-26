<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\BorrowingItem;
use App\Models\BorrowingRequest;
use App\Models\IncidentReport;
use App\Models\InventoryCategory;
use App\Models\InventoryCondition;
use App\Models\InventoryItem;
use App\Models\ReturnTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $isScopedMember = ! $user->hasPermission('borrowings.view_all');

        $totalItems = InventoryItem::count();
        $totalUnits = (int) InventoryItem::sum('quantity');

        $availableItems = InventoryItem::where('status', InventoryItem::STATUS_AVAILABLE)
            ->where('available_quantity', '>', 0)
            ->count();
        $availableUnits = (int) InventoryItem::sum('available_quantity');

        $borrowedUnits = (int) BorrowingItem::query()
            ->whereHas('borrowingRequest', function ($q) use ($isScopedMember, $user): void {
                $q->whereIn('status', [
                    BorrowingRequest::STATUS_APPROVED,
                    BorrowingRequest::STATUS_PARTIALLY_RETURNED,
                    BorrowingRequest::STATUS_OVERDUE,
                ]);
                if ($isScopedMember) {
                    $q->where('requester_user_id', $user->id);
                }
            })
            ->selectRaw('COALESCE(SUM(quantity_approved - quantity_returned), 0) as outstanding')
            ->value('outstanding');

        $activeLoanCount = BorrowingRequest::whereIn('status', [
            BorrowingRequest::STATUS_APPROVED,
            BorrowingRequest::STATUS_PARTIALLY_RETURNED,
            BorrowingRequest::STATUS_OVERDUE,
        ])
            ->when($isScopedMember, fn ($q) => $q->where('requester_user_id', $user->id))
            ->count();

        $lowStockItems = InventoryItem::where('status', '!=', InventoryItem::STATUS_DISPOSED)
            ->whereColumn('available_quantity', '<=', 'minimum_stock_level')
            ->count();

        $damagedOrMissingItems = InventoryItem::whereIn('status', [
            InventoryItem::STATUS_DAMAGED,
            InventoryItem::STATUS_MISSING,
            InventoryItem::STATUS_UNDER_MAINTENANCE,
        ])->count();

        $pendingInspections = ReturnTransaction::where('flagged_for_inspection', true)
            ->where('inspection_status', ReturnTransaction::INSPECTION_PENDING)
            ->count();

        $overdueReturns = BorrowingRequest::where(function ($q): void {
            $q->where('status', BorrowingRequest::STATUS_OVERDUE)
                ->orWhere(function ($sub): void {
                    $sub->whereIn('status', [
                        BorrowingRequest::STATUS_APPROVED,
                        BorrowingRequest::STATUS_PARTIALLY_RETURNED,
                    ])->whereDate('expected_return_date', '<', Carbon::today());
                });
        })
            ->when($isScopedMember, fn ($q) => $q->where('requester_user_id', $user->id))
            ->count();

        $categoryStats = InventoryCategory::query()
            ->where('is_active', true)
            ->withCount('items')
            ->withSum('items as total_units', 'quantity')
            ->orderBy('name')
            ->get();

        $conditionStats = InventoryCondition::query()
            ->withCount('items')
            ->orderBy('severity_rank')
            ->get();

        $recentActivities = AuditLog::query()
            ->when($isScopedMember, fn ($q) => $q->where('user_id', $user->id))
            ->latest('created_at')
            ->limit(8)
            ->get();

        $lowStockList = InventoryItem::with(['category', 'location'])
            ->where('status', '!=', InventoryItem::STATUS_DISPOSED)
            ->whereColumn('available_quantity', '<=', 'minimum_stock_level')
            ->orderBy('available_quantity')
            ->limit(5)
            ->get();

        $overdueList = BorrowingRequest::with(['items.inventoryItem'])
            ->whereIn('status', [
                BorrowingRequest::STATUS_OVERDUE,
                BorrowingRequest::STATUS_APPROVED,
                BorrowingRequest::STATUS_PARTIALLY_RETURNED,
            ])
            ->whereDate('expected_return_date', '<', Carbon::today())
            ->when($isScopedMember, fn ($q) => $q->where('requester_user_id', $user->id))
            ->orderBy('expected_return_date')
            ->limit(5)
            ->get();

        $securitySummary = null;
        if ($user->hasPermission('audit_logs.view')) {
            $since = now()->subHours(24);
            $securitySummary = [
                'failed_logins_24h' => AuditLog::whereIn('action', ['LOGIN_FAILED', 'ACCOUNT_LOCKED'])->where('created_at', '>=', $since)->count(),
                'locked_accounts' => User::where('locked_until', '>', now())->count(),
                'access_denied_24h' => AuditLog::where('action', 'ACCESS_DENIED')->where('created_at', '>=', $since)->count(),
                'open_incidents' => IncidentReport::whereNotIn('status', [IncidentReport::STATUS_RESOLVED, IncidentReport::STATUS_LESSONS_LEARNED])->count(),
            ];
        }

        return view('dashboard.index', [
            'kpis' => [
                'total_items' => $totalItems,
                'total_units' => $totalUnits,
                'available_items' => $availableItems,
                'available_units' => $availableUnits,
                'borrowed_units' => $borrowedUnits,
                'active_loans' => $activeLoanCount,
                'low_stock_items' => $lowStockItems,
                'damaged_missing_items' => $damagedOrMissingItems,
                'pending_inspections' => $pendingInspections,
                'overdue_returns' => $overdueReturns,
            ],
            'charts' => [
                'categories' => [
                    'labels' => $categoryStats->pluck('name')->values(),
                    'items' => $categoryStats->pluck('items_count')->map(fn ($v) => (int) $v)->values(),
                    'units' => $categoryStats->pluck('total_units')->map(fn ($v) => (int) ($v ?? 0))->values(),
                ],
                'conditions' => [
                    'labels' => $conditionStats->pluck('name')->values(),
                    'counts' => $conditionStats->pluck('items_count')->map(fn ($v) => (int) $v)->values(),
                ],
            ],
            'recentActivities' => $recentActivities,
            'lowStockList' => $lowStockList,
            'overdueList' => $overdueList,
            'securitySummary' => $securitySummary,
            'isScopedMember' => $isScopedMember,
        ]);
    }
}
