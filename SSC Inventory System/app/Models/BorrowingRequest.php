<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BorrowingRequest extends Model
{
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_PARTIALLY_RETURNED = 'partially_returned';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_OVERDUE = 'overdue';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING => 'Pending Approval',
        self::STATUS_APPROVED => 'Borrowed / Active',
        self::STATUS_REJECTED => 'Rejected',
        self::STATUS_PARTIALLY_RETURNED => 'Partially Returned',
        self::STATUS_RETURNED => 'Returned',
        self::STATUS_OVERDUE => 'Overdue',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    protected $fillable = [
        'transaction_code',
        'requester_user_id',
        'borrower_name',
        'school_id',
        'department_organization',
        'borrower_contact',
        'purpose',
        'request_date',
        'date_borrowed',
        'expected_return_date',
        'actual_return_date',
        'return_requested_at',
        'return_request_notes',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'remarks',
        'count_verified',
        'overdue_notified_at',
        'upcoming_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'request_date' => 'date',
            'date_borrowed' => 'date',
            'expected_return_date' => 'date',
            'actual_return_date' => 'date',
            'return_requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'overdue_notified_at' => 'datetime',
            'upcoming_notified_at' => 'datetime',
            'borrower_contact' => 'encrypted',
            'count_verified' => 'boolean',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BorrowingItem::class, 'borrowing_request_id');
    }

    public function returnTransactions(): HasMany
    {
        return $this->hasMany(ReturnTransaction::class, 'borrowing_request_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst(str_replace('_', ' ', $this->status));
    }

    public function isActiveLoan(): bool
    {
        return in_array($this->status, [
            self::STATUS_APPROVED,
            self::STATUS_PARTIALLY_RETURNED,
            self::STATUS_OVERDUE,
        ], true);
    }

    public function isOverdue(): bool
    {
        if (! $this->isActiveLoan() || ! $this->expected_return_date) {
            return false;
        }

        return $this->expected_return_date->copy()->endOfDay()->isPast();
    }

    public function getOverdueDaysAttribute(): int
    {
        if (! $this->isOverdue()) {
            return 0;
        }

        return (int) $this->expected_return_date->copy()->startOfDay()->diffInDays(Carbon::today());
    }
}
