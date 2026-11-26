<?php

namespace App\Services;

use App\Models\BorrowingItem;
use App\Models\BorrowingRequest;
use App\Models\InventoryItem;
use App\Models\User;

class ReportService
{
    public const REPORTS = [
        'complete-inventory' => [
            'title' => 'Complete SSC Inventory List',
            'description' => 'Full register of all active PCB Supreme Student Council equipment, supplies, quantities, and conditions.',
            'officer_allowed' => true,
        ],
        'available-inventory' => [
            'title' => 'Available Equipment & Supplies',
            'description' => 'Items currently available with borrowable quantities and storage locations.',
            'officer_allowed' => true,
        ],
        'borrowed-overdue' => [
            'title' => 'Borrowed & Overdue Equipment Report',
            'description' => 'Active, overdue, and returned council equipment loans with borrower details and return tracking.',
            'officer_allowed' => false,
        ],
        'damaged-missing' => [
            'title' => 'Damaged & Missing Items Report',
            'description' => 'Items flagged as damaged, under maintenance, missing, or requiring inspection.',
            'officer_allowed' => false,
        ],
        'accountability' => [
            'title' => 'Inventory Accountability Report',
            'description' => 'Property custodianship and active borrowing accountability (scoped to own records for SSC members).',
            'officer_allowed' => true,
        ],
    ];

    public function __construct(protected SettingsService $settings)
    {
    }

    public function availableReportsFor(User $user): array
    {
        if ($user->hasPermission('reports.view_all')) {
            return self::REPORTS;
        }

        return array_filter(self::REPORTS, fn (array $meta) => $meta['officer_allowed'] === true);
    }

    public function canAccess(User $user, string $type): bool
    {
        if (! isset(self::REPORTS[$type])) {
            return false;
        }

        if ($user->hasPermission('reports.view_all')) {
            return true;
        }

        return $user->hasPermission('reports.view_basic') && self::REPORTS[$type]['officer_allowed'] === true;
    }

    public function build(string $type, array $filters, User $user): array
    {
        if (! isset(self::REPORTS[$type])) {
            abort(404, 'Unknown report type.');
        }

        $meta = self::REPORTS[$type];

        return match ($type) {
            'complete-inventory' => $this->buildInventoryListReport($meta['title'], $filters, false),
            'available-inventory' => $this->buildInventoryListReport($meta['title'], $filters, true),
            'borrowed-overdue' => $this->buildBorrowedOverdueReport($meta['title'], $filters),
            'damaged-missing' => $this->buildDamagedMissingReport($meta['title'], $filters),
            'accountability' => $this->buildAccountabilityReport($meta['title'], $filters, $user),
        };
    }

    public function sanitizeSpreadsheetCell(mixed $value): string
    {
        $str = (string) ($value ?? '');
        if ($str !== '' && in_array($str[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $str;
        }

        return $str;
    }

    public function institutionalMeta(User $user): array
    {
        return [
            'institution_name' => $this->settings->get('institution_name', 'POLYTECHNIC COLLEGE OF BOTOLAN'),
            'organization_name' => $this->settings->get('organization_name', 'STUDENT SUPREME COUNCIL'),
            'campus_address' => $this->settings->get('campus_address', 'Botolan, Zambales, Philippines'),
            'generated_at' => now()->format('F d, Y h:i A'),
            'generated_by' => $user->name . ' (' . ($user->role?->name ?? 'User') . ')',
            'prepared_by_name' => $user->name,
            'prepared_by_title' => $this->settings->get('report_prepared_by_title', 'SSC Secretary'),
            'noted_by_name' => $this->settings->get('report_noted_by_name', 'SSC President'),
            'noted_by_title' => $this->settings->get('report_noted_by_title', 'Supreme Student Council President'),
            'approved_by_name' => $this->settings->get('report_approved_by_name', 'SSC Adviser / OSAS'),
            'approved_by_title' => $this->settings->get('report_approved_by_title', 'Office of Student Affairs & Services'),
        ];
    }

    protected function applyInventoryFilters($query, array $filters): void
    {
        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }
        if (! empty($filters['location_id'])) {
            $query->where('location_id', $filters['location_id']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['condition_id'])) {
            $query->where('condition_id', $filters['condition_id']);
        }
        if (! empty($filters['responsible_user_id'])) {
            $query->where('responsible_user_id', $filters['responsible_user_id']);
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('acquisition_date', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('acquisition_date', '<=', $filters['date_to']);
        }
    }

    protected function buildInventoryListReport(string $title, array $filters, bool $onlyAvailable): array
    {
        $query = InventoryItem::with(['category', 'condition', 'location', 'responsibleUser'])
            ->orderBy('property_code');

        if ($onlyAvailable) {
            $query->where('status', InventoryItem::STATUS_AVAILABLE)->where('available_quantity', '>', 0);
        }

        $this->applyInventoryFilters($query, $filters);
        $items = $query->get();

        $rows = $items->map(fn (InventoryItem $i) => [
            $i->property_code,
            $i->name,
            $i->category?->name ?? '—',
            $i->brand_model ?? '—',
            $i->quantity,
            $i->available_quantity,
            $i->unit_of_measurement,
            $i->status_label,
            $i->condition?->name ?? '—',
            $i->location?->name ?? 'Unassigned',
            $i->responsibleUser?->name ?? 'Unassigned',
        ])->all();

        return [
            'title' => $title,
            'subtitle' => $onlyAvailable ? 'Available stock ready for SSC events and borrowing' : 'All active organizational inventory records',
            'columns' => ['Property Code', 'Item Name', 'Category', 'Brand / Model', 'Total Qty', 'Available', 'Unit', 'Status', 'Condition', 'Location', 'Responsible Officer'],
            'rows' => $rows,
            'summary' => [
                'Total Item Records' => $items->count(),
                'Total Units' => $items->sum('quantity'),
                'Total Available Units' => $items->sum('available_quantity'),
            ],
        ];
    }

    protected function buildBorrowedOverdueReport(string $title, array $filters): array
    {
        $query = BorrowingRequest::with(['items.inventoryItem', 'requester'])
            ->whereIn('status', [
                BorrowingRequest::STATUS_APPROVED,
                BorrowingRequest::STATUS_PARTIALLY_RETURNED,
                BorrowingRequest::STATUS_OVERDUE,
                BorrowingRequest::STATUS_RETURNED,
            ]);

        if (! empty($filters['date_from'])) {
            $query->whereDate('date_borrowed', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('date_borrowed', '<=', $filters['date_to']);
        }
        if (! empty($filters['responsible_user_id'])) {
            $query->where('requester_user_id', $filters['responsible_user_id']);
        }

        $requests = $query->latest('date_borrowed')->get();

        $rows = [];
        $totalLoanedUnits = 0;
        $overdueCount = 0;

        foreach ($requests as $req) {
            if ($req->isOverdue()) {
                $overdueCount++;
            }
            foreach ($req->items as $bi) {
                $item = $bi->inventoryItem;
                if (! empty($filters['category_id']) && $item?->category_id != $filters['category_id']) {
                    continue;
                }
                if (! empty($filters['location_id']) && $item?->location_id != $filters['location_id']) {
                    continue;
                }
                $totalLoanedUnits += $bi->quantity_approved;
                $rows[] = [
                    $req->transaction_code,
                    $req->borrower_name,
                    $req->school_id,
                    $req->department_organization,
                    ($item?->property_code ?? '—') . ' — ' . ($item?->name ?? 'Item'),
                    $bi->quantity_approved,
                    $bi->quantity_returned,
                    $bi->remainingQuantity(),
                    $req->date_borrowed->format('Y-m-d'),
                    $req->expected_return_date->format('Y-m-d'),
                    $req->isOverdue() ? $req->overdue_days . ' day(s)' : '0',
                    $req->status_label,
                ];
            }
        }

        return [
            'title' => $title,
            'subtitle' => 'Approved, overdue, and returned equipment borrowing transactions',
            'columns' => ['Transaction Code', 'Borrower', 'School ID', 'Committee / Org', 'Item', 'Approved', 'Returned', 'Remaining', 'Borrowed Date', 'Due Date', 'Overdue Days', 'Status'],
            'rows' => $rows,
            'summary' => [
                'Line Items Listed' => count($rows),
                'Total Units Borrowed' => $totalLoanedUnits,
                'Overdue Transactions' => $overdueCount,
            ],
        ];
    }

    protected function buildDamagedMissingReport(string $title, array $filters): array
    {
        $query = InventoryItem::with(['category', 'condition', 'location', 'responsibleUser'])
            ->where(function ($q): void {
                $q->whereIn('status', [
                    InventoryItem::STATUS_DAMAGED,
                    InventoryItem::STATUS_MISSING,
                    InventoryItem::STATUS_UNDER_MAINTENANCE,
                ])->orWhereHas('condition', fn ($c) => $c->whereIn('slug', ['poor', 'non_functional']));
            });

        $this->applyInventoryFilters($query, $filters);
        $items = $query->orderBy('property_code')->get();

        $rows = $items->map(fn (InventoryItem $i) => [
            $i->property_code,
            $i->name,
            $i->category?->name ?? '—',
            $i->quantity,
            $i->available_quantity,
            $i->status_label,
            $i->condition?->name ?? '—',
            $i->location?->name ?? 'Unassigned',
            $i->responsibleUser?->name ?? 'Unassigned',
            $i->remarks ?? '—',
        ])->all();

        return [
            'title' => $title,
            'subtitle' => 'Items requiring inspection, repair, recovery, or replacement',
            'columns' => ['Property Code', 'Item Name', 'Category', 'Total Qty', 'Available', 'Status', 'Condition', 'Location', 'Custodian', 'Remarks'],
            'rows' => $rows,
            'summary' => [
                'Flagged Records' => $items->count(),
                'Damaged Status Count' => $items->where('status', InventoryItem::STATUS_DAMAGED)->count(),
                'Missing Status Count' => $items->where('status', InventoryItem::STATUS_MISSING)->count(),
            ],
        ];
    }

    protected function buildAccountabilityReport(string $title, array $filters, User $user): array
    {
        $isScopedMember = ! $user->hasPermission('reports.view_all');

        $itemsQuery = InventoryItem::with(['category', 'condition', 'location', 'responsibleUser'])
            ->when($isScopedMember, fn ($q) => $q->where('responsible_user_id', $user->id))
            ->when(! $isScopedMember && ! empty($filters['responsible_user_id']), fn ($q) => $q->where('responsible_user_id', $filters['responsible_user_id']))
            ->when(! empty($filters['category_id']), fn ($q) => $q->where('category_id', $filters['category_id']))
            ->when(! empty($filters['location_id']), fn ($q) => $q->where('location_id', $filters['location_id']))
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(! empty($filters['condition_id']), fn ($q) => $q->where('condition_id', $filters['condition_id']))
            ->orderBy('property_code');

        $assignedItems = $itemsQuery->get();

        $activeBorrowings = BorrowingItem::with(['borrowingRequest', 'inventoryItem.condition'])
            ->whereRaw('quantity_approved > quantity_returned')
            ->whereHas('borrowingRequest', function ($q) use ($isScopedMember, $user, $filters): void {
                $q->whereIn('status', [
                    BorrowingRequest::STATUS_APPROVED,
                    BorrowingRequest::STATUS_PARTIALLY_RETURNED,
                    BorrowingRequest::STATUS_OVERDUE,
                ]);
                if ($isScopedMember) {
                    $q->where('requester_user_id', $user->id);
                } elseif (! empty($filters['responsible_user_id'])) {
                    $q->where('requester_user_id', $filters['responsible_user_id']);
                }
            })
            ->get();

        $rows = [];
        foreach ($assignedItems as $item) {
            $rows[] = [
                'Custodian Assignment',
                $item->responsibleUser?->name ?? 'Unassigned',
                $item->property_code,
                $item->name,
                $item->quantity,
                $item->status_label,
                $item->condition?->name ?? '—',
                $item->location?->name ?? 'Unassigned',
                'Official Property Custody',
            ];
        }

        foreach ($activeBorrowings as $bi) {
            $req = $bi->borrowingRequest;
            $item = $bi->inventoryItem;
            $rows[] = [
                'Borrowed Equipment',
                $req->borrower_name . ' (' . $req->school_id . ')',
                $item?->property_code ?? '—',
                $item?->name ?? 'Item',
                $bi->remainingQuantity(),
                $req->status_label,
                $item?->condition?->name ?? '—',
                $req->department_organization,
                "Loan {$req->transaction_code} · Due " . $req->expected_return_date->format('Y-m-d'),
            ];
        }

        return [
            'title' => $title,
            'subtitle' => $isScopedMember ? "Personal property and borrowing accountability for {$user->name}" : 'Officer custodianship and outstanding borrowed equipment accountability',
            'columns' => ['Assignment Type', 'Accountable Officer / Borrower', 'Property Code', 'Item Name', 'Qty Held', 'Status', 'Condition', 'Location / Committee', 'Reference'],
            'rows' => $rows,
            'summary' => [
                'Custodian-Assigned Items' => $assignedItems->count(),
                'Active Borrowed Line Items' => $activeBorrowings->count(),
            ],
        ];
    }
}
