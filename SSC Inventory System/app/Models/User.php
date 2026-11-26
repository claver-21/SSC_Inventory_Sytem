<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'role_id',
        'name',
        'email',
        'password',
        'school_id',
        'sex',
        'position',
        'department',
        'phone',
        'avatar_path',
        'is_active',
        'mfa_enabled',
        'mfa_secret',
        'mfa_confirmed_at',
        'failed_login_attempts',
        'locked_until',
        'privacy_consent_at',
        'privacy_consent_version',
        'last_login_at',
        'last_seen_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'mfa_secret',
    ];

    protected ?array $cachedPermissionSlugs = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'mfa_enabled' => 'boolean',
            'mfa_secret' => 'encrypted',
            'mfa_confirmed_at' => 'datetime',
            'failed_login_attempts' => 'integer',
            'locked_until' => 'datetime',
            'privacy_consent_at' => 'datetime',
            'last_login_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function backupCodes(): HasMany
    {
        return $this->hasMany(UserMfaBackupCode::class);
    }

    public function responsibleItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class, 'responsible_user_id');
    }

    public function borrowingRequests(): HasMany
    {
        return $this->hasMany(BorrowingRequest::class, 'requester_user_id');
    }

    public function incidentReports(): HasMany
    {
        return $this->hasMany(IncidentReport::class, 'reported_by');
    }

    public function appNotifications(): HasMany
    {
        return $this->hasMany(AppNotification::class, 'user_id');
    }

    public function hasRole(string $slug): bool
    {
        return $this->role?->slug === $slug;
    }

    public function hasAnyRole(array $slugs): bool
    {
        return $this->role !== null && in_array($this->role->slug, $slugs, true);
    }

    public function permissionSlugs(): array
    {
        if ($this->cachedPermissionSlugs !== null) {
            return $this->cachedPermissionSlugs;
        }

        if (! $this->role) {
            return $this->cachedPermissionSlugs = [];
        }

        $this->role->loadMissing('permissions:id,slug');

        return $this->cachedPermissionSlugs = $this->role->permissions
            ->pluck('slug')
            ->all();
    }

    public function hasPermission(string $permissionSlug): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return in_array($permissionSlug, $this->permissionSlugs(), true);
    }

    public function hasAnyPermission(array $permissionSlugs): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return count(array_intersect($permissionSlugs, $this->permissionSlugs())) > 0;
    }

    public function clearPermissionCache(): void
    {
        $this->cachedPermissionSlugs = null;
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at !== null && $this->last_seen_at->gt(now()->subMinutes(5));
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(Role::SLUG_ADMIN);
    }

    public function isMember(): bool
    {
        return $this->hasRole(Role::SLUG_MEMBER);
    }
}
