<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncidentReport extends Model
{
    public const CATEGORY_EQUIPMENT = 'equipment';
    public const CATEGORY_SECURITY = 'security';

    public const TYPE_DAMAGED = 'damaged';
    public const TYPE_MISSING = 'missing';
    public const TYPE_PHISHING = 'phishing';
    public const TYPE_SUSPICIOUS_LOGIN = 'suspicious_login';
    public const TYPE_UNAUTHORIZED_ACCESS = 'unauthorized_access';

    public const TYPES = [
        self::TYPE_DAMAGED => 'Damaged Equipment',
        self::TYPE_MISSING => 'Missing Equipment',
        self::TYPE_PHISHING => 'Phishing / Scam Link',
        self::TYPE_SUSPICIOUS_LOGIN => 'Suspicious Login / Account Alert',
        self::TYPE_UNAUTHORIZED_ACCESS => 'Unauthorized Access Attempt',
    ];

    public const STATUS_REPORTED = 'reported';
    public const STATUS_INVESTIGATING = 'investigating';
    public const STATUS_CONTAINED = 'contained';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_LESSONS_LEARNED = 'lessons_learned';

    public const STATUSES = [
        self::STATUS_REPORTED => 'Reported',
        self::STATUS_INVESTIGATING => 'Investigating',
        self::STATUS_CONTAINED => 'Contained',
        self::STATUS_RESOLVED => 'Resolved',
        self::STATUS_LESSONS_LEARNED => 'Lessons Learned',
    ];

    protected $fillable = [
        'report_code',
        'inventory_item_id',
        'reported_by',
        'category',
        'incident_type',
        'affected_quantity',
        'description',
        'status',
        'resolved_by',
        'resolved_at',
        'resolution_notes',
        'lessons_learned',
    ];

    protected function casts(): array
    {
        return [
            'affected_quantity' => 'integer',
            'resolved_at' => 'datetime',
        ];
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id')->withTrashed();
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
