<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    public const TYPE_STOCK_IN = 'stock_in';
    public const TYPE_STOCK_OUT = 'stock_out';
    public const TYPE_BORROWING = 'borrowing';
    public const TYPE_RETURNING = 'returning';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_TRANSFER = 'transfer';
    public const TYPE_DAMAGED = 'damaged';
    public const TYPE_MISSING = 'missing';
    public const TYPE_DISPOSAL = 'disposal';

    public const TYPES = [
        self::TYPE_STOCK_IN => 'Stock In',
        self::TYPE_STOCK_OUT => 'Stock Out',
        self::TYPE_BORROWING => 'Borrowing',
        self::TYPE_RETURNING => 'Returning',
        self::TYPE_ADJUSTMENT => 'Adjustment',
        self::TYPE_TRANSFER => 'Transfer',
        self::TYPE_DAMAGED => 'Damaged Stock',
        self::TYPE_MISSING => 'Missing Stock',
        self::TYPE_DISPOSAL => 'Disposal',
    ];

    protected $fillable = [
        'transaction_reference',
        'inventory_item_id',
        'movement_type',
        'quantity',
        'previous_quantity',
        'new_quantity',
        'previous_available_quantity',
        'new_available_quantity',
        'from_location_id',
        'to_location_id',
        'reference_type',
        'reference_id',
        'processed_by',
        'transaction_date',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'previous_quantity' => 'integer',
            'new_quantity' => 'integer',
            'previous_available_quantity' => 'integer',
            'new_available_quantity' => 'integer',
            'transaction_date' => 'datetime',
        ];
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id')->withTrashed();
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'to_location_id');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->movement_type] ?? ucfirst(str_replace('_', ' ', $this->movement_type));
    }
}
