<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Acquisition extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'purchase_reference',
        'inventory_item_id',
        'item_name',
        'category_id',
        'quantity_acquired',
        'unit_cost',
        'total_cost',
        'supplier_id',
        'supplier_name',
        'purchase_date',
        'funding_source',
        'receipt_path',
        'remarks',
        'recorded_by',
        'synced_to_inventory',
    ];

    protected function casts(): array
    {
        return [
            'quantity_acquired' => 'integer',
            'unit_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'purchase_date' => 'date',
            'synced_to_inventory' => 'boolean',
        ];
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id')->withTrashed();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'category_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
