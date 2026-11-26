<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BorrowingItem extends Model
{
    protected $fillable = [
        'borrowing_request_id',
        'inventory_item_id',
        'quantity_requested',
        'quantity_approved',
        'quantity_returned',
        'quantity_damaged_on_return',
        'quantity_missing_on_return',
        'condition_on_borrow_id',
        'status',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'quantity_requested' => 'integer',
            'quantity_approved' => 'integer',
            'quantity_returned' => 'integer',
            'quantity_damaged_on_return' => 'integer',
            'quantity_missing_on_return' => 'integer',
        ];
    }

    public function borrowingRequest(): BelongsTo
    {
        return $this->belongsTo(BorrowingRequest::class, 'borrowing_request_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id')->withTrashed();
    }

    public function conditionOnBorrow(): BelongsTo
    {
        return $this->belongsTo(InventoryCondition::class, 'condition_on_borrow_id');
    }

    public function returnTransactions(): HasMany
    {
        return $this->hasMany(ReturnTransaction::class, 'borrowing_item_id');
    }

    public function remainingQuantity(): int
    {
        return max(0, $this->quantity_approved - $this->quantity_returned);
    }
}
