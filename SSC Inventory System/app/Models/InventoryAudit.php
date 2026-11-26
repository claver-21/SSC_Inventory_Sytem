<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryAudit extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_IN_PROGRESS => 'In Progress',
        self::STATUS_PENDING_APPROVAL => 'Pending Approval',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_REJECTED => 'Rejected',
    ];

    protected $fillable = [
        'audit_code',
        'title',
        'audit_date',
        'location_id',
        'category_id',
        'status',
        'conducted_by',
        'approved_by',
        'approved_at',
        'remarks',
        'approval_remarks',
    ];

    protected function casts(): array
    {
        return [
            'audit_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'category_id');
    }

    public function conductor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conducted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryAuditItem::class, 'inventory_audit_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst(str_replace('_', ' ', $this->status));
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_IN_PROGRESS], true);
    }
}
