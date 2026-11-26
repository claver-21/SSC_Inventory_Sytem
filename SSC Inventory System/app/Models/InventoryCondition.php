<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryCondition extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'severity_rank',
        'badge_color',
        'description',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(InventoryItem::class, 'condition_id');
    }
}
