<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnTransaction extends Model
{
    public const INSPECTION_PENDING = 'pending_inspection';
    public const INSPECTION_CLEARED = 'cleared_available';
    public const INSPECTION_DAMAGED = 'marked_damaged';
    public const INSPECTION_MAINTENANCE = 'sent_maintenance';
    public const INSPECTION_DISPOSED = 'disposed';

    protected $fillable = [
        'return_code',
        'borrowing_request_id',
        'borrowing_item_id',
        'inventory_item_id',
        'quantity_returned',
        'actual_return_date',
        'condition_on_return_id',
        'is_damaged',
        'is_missing',
        'flagged_for_inspection',
        'inspection_status',
        'inspected_by',
        'inspected_at',
        'inspection_remarks',
        'received_by',
        'remarks',
        'count_verified',
    ];

    protected function casts(): array
    {
        return [
            'quantity_returned' => 'integer',
            'actual_return_date' => 'date',
            'is_damaged' => 'boolean',
            'is_missing' => 'boolean',
            'flagged_for_inspection' => 'boolean',
            'inspected_at' => 'datetime',
            'count_verified' => 'boolean',
        ];
    }

    public function borrowingRequest(): BelongsTo
    {
        return $this->belongsTo(BorrowingRequest::class, 'borrowing_request_id');
    }

    public function borrowingItem(): BelongsTo
    {
        return $this->belongsTo(BorrowingItem::class, 'borrowing_item_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id')->withTrashed();
    }

    public function conditionOnReturn(): BelongsTo
    {
        return $this->belongsTo(InventoryCondition::class, 'condition_on_return_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }
}
