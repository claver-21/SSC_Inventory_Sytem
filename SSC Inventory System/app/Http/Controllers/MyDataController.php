<?php

namespace App\Http\Controllers;

use App\Models\BorrowingRequest;
use App\Models\IncidentReport;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class MyDataController extends Controller
{
    public function __construct(protected AuditLogService $auditLog)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user()->load('role');

        $borrowings = BorrowingRequest::with('items.inventoryItem')
            ->where('requester_user_id', $user->id)
            ->latest()
            ->limit(15)
            ->get();

        $incidents = IncidentReport::with('inventoryItem')
            ->where('reported_by', $user->id)
            ->latest()
            ->limit(15)
            ->get();

        $unusedBackupCodesCount = $user->backupCodes()->whereNull('used_at')->count();

        return view('my-data.index', compact('user', 'borrowings', 'incidents', 'unusedBackupCodesCount'));
    }

    public function exportJson(Request $request): Response
    {
        $user = $request->user()->load('role');

        $payload = [
            'exported_at' => now()->toIso8601String(),
            'privacy_notice' => 'RA 10173 Data Privacy Act of 2012 — Subject Access Export',
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'school_id' => $user->school_id,
                'role' => $user->role?->name,
                'position' => $user->position,
                'department' => $user->department,
                'phone' => $user->phone,
                'mfa_enabled' => $user->mfa_enabled,
                'mfa_confirmed_at' => $user->mfa_confirmed_at?->toIso8601String(),
                'privacy_consent_at' => $user->privacy_consent_at?->toIso8601String(),
                'privacy_consent_version' => $user->privacy_consent_version,
                'last_login_at' => $user->last_login_at?->toIso8601String(),
            ],
            'borrowing_history' => BorrowingRequest::with('items.inventoryItem:id,property_code,name')
                ->where('requester_user_id', $user->id)
                ->latest()
                ->get()
                ->map(fn (BorrowingRequest $b) => [
                    'transaction_code' => $b->transaction_code,
                    'purpose' => $b->purpose,
                    'date_borrowed' => $b->date_borrowed?->format('Y-m-d'),
                    'expected_return_date' => $b->expected_return_date?->format('Y-m-d'),
                    'status' => $b->status,
                    'items' => $b->items->map(fn ($bi) => [
                        'property_code' => $bi->inventoryItem?->property_code,
                        'name' => $bi->inventoryItem?->name,
                        'quantity_requested' => $bi->quantity_requested,
                        'quantity_approved' => $bi->quantity_approved,
                        'quantity_returned' => $bi->quantity_returned,
                    ])->all(),
                ])->all(),
            'incident_reports' => IncidentReport::with('inventoryItem:id,property_code,name')
                ->where('reported_by', $user->id)
                ->latest()
                ->get()
                ->map(fn (IncidentReport $inc) => [
                    'report_code' => $inc->report_code,
                    'category' => $inc->category,
                    'incident_type' => $inc->incident_type,
                    'status' => $inc->status,
                    'description' => $inc->description,
                    'created_at' => $inc->created_at?->toIso8601String(),
                ])->all(),
        ];

        $this->auditLog->log(
            action: 'MY_DATA_EXPORTED',
            module: 'privacy',
            auditable: $user,
            description: "Exported RA 10173 personal data package for {$user->email}.",
            recordLabel: $user->email,
            actor: $user
        );

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return response($json, 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="pcb_ssc_my_data_' . now()->format('Ymd_His') . '.json"',
        ]);
    }
}
