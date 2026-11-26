<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryLocation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'building',
        'room_number',
        'description',
        'custodian_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function custodian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'custodian_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryItem::class, 'location_id');
    }
}
