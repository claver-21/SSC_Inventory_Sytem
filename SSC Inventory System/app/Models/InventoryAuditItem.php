<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryAuditItem extends Model
{
    protected $fillable = [
        'inventory_audit_id',
        'inventory_item_id',
        'system_quantity',
        'physical_quantity',
        'discrepancy',
        'damaged_quantity',
        'missing_quantity',
        'observed_condition_id',
        'adjustment_applied',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'system_quantity' => 'integer',
            'physical_quantity' => 'integer',
            'discrepancy' => 'integer',
            'damaged_quantity' => 'integer',
            'missing_quantity' => 'integer',
            'adjustment_applied' => 'boolean',
        ];
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(InventoryAudit::class, 'inventory_audit_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id')->withTrashed();
    }

    public function observedCondition(): BelongsTo
    {
        return $this->belongsTo(InventoryCondition::class, 'observed_condition_id');
    }
}
