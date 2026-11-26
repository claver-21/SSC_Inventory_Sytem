<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryItem extends Model
{
    use SoftDeletes;

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_BORROWED = 'borrowed';
    public const STATUS_RESERVED = 'reserved';
    public const STATUS_UNDER_MAINTENANCE = 'under_maintenance';
    public const STATUS_DAMAGED = 'damaged';
    public const STATUS_MISSING = 'missing';
    public const STATUS_DISPOSED = 'disposed';

    public const STATUSES = [
        self::STATUS_AVAILABLE => 'Available',
        self::STATUS_BORROWED => 'Borrowed',
        self::STATUS_RESERVED => 'Reserved',
        self::STATUS_UNDER_MAINTENANCE => 'Under Maintenance',
        self::STATUS_DAMAGED => 'Damaged',
        self::STATUS_MISSING => 'Missing',
        self::STATUS_DISPOSED => 'Disposed',
    ];

    protected $fillable = [
        'property_code',
        'name',
        'description',
        'category_id',
        'brand_model',
        'serial_number',
        'sensitive_notes',
        'quantity',
        'available_quantity',
        'minimum_stock_level',
        'unit_of_measurement',
        'acquisition_date',
        'acquisition_cost',
        'supplier_id',
        'supplier_name',
        'status',
        'condition_id',
        'location_id',
        'responsible_user_id',
        'remarks',
        'image_path',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'available_quantity' => 'integer',
            'minimum_stock_level' => 'integer',
            'acquisition_date' => 'date',
            'acquisition_cost' => 'decimal:2',
            'serial_number' => 'encrypted',
            'sensitive_notes' => 'encrypted',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'category_id');
    }

    public function condition(): BelongsTo
    {
        return $this->belongsTo(InventoryCondition::class, 'condition_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'inventory_item_id');
    }

    public function borrowingItems(): HasMany
    {
        return $this->hasMany(BorrowingItem::class, 'inventory_item_id');
    }

    public function returnTransactions(): HasMany
    {
        return $this->hasMany(ReturnTransaction::class, 'inventory_item_id');
    }

    public function incidentReports(): HasMany
    {
        return $this->hasMany(IncidentReport::class, 'inventory_item_id');
    }

    public function acquisitions(): HasMany
    {
        return $this->hasMany(Acquisition::class, 'inventory_item_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst(str_replace('_', ' ', $this->status));
    }

    public function getBorrowedQuantityAttribute(): int
    {
        return max(0, $this->quantity - $this->available_quantity);
    }

    public function isLowStock(): bool
    {
        return $this->status !== self::STATUS_DISPOSED
            && $this->available_quantity <= $this->minimum_stock_level;
    }

    public function imageUrl(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        return route('media.items', ['filename' => basename($this->image_path)]);
    }
}
