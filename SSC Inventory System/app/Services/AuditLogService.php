<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLogService
{
    public function log(
        string $action,
        string $module,
        ?Model $auditable = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $description = null,
        ?string $recordLabel = null,
        ?User $actor = null
    ): AuditLog {
        $user = $actor ?? auth()->user();
        $request = app()->bound('request') ? request() : null;

        $resolvedLabel = $recordLabel;
        if ($resolvedLabel === null && $auditable !== null) {
            $resolvedLabel = $auditable->property_code
                ?? $auditable->transaction_code
                ?? $auditable->transaction_reference
                ?? $auditable->purchase_reference
                ?? $auditable->audit_code
                ?? $auditable->report_code
                ?? $auditable->name
                ?? $auditable->title
                ?? ('#' . $auditable->getKey());
        }

        return AuditLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'System',
            'action' => $action,
            'module' => $module,
            'auditable_type' => $auditable ? $auditable->getMorphClass() : null,
            'auditable_id' => $auditable?->getKey(),
            'record_label' => $resolvedLabel,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'description' => $description,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'created_at' => now(),
        ]);
    }
}
