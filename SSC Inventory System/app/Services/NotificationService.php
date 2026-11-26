<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class NotificationService
{
    public function sendToUser(
        User|int $user,
        string $type,
        string $title,
        string $message,
        string $severity = 'info',
        ?string $actionUrl = null,
        ?Model $reference = null
    ): AppNotification {
        $userId = $user instanceof User ? $user->id : $user;

        return AppNotification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'severity' => $severity,
            'action_url' => $actionUrl,
            'reference_type' => $reference ? $reference->getMorphClass() : null,
            'reference_id' => $reference?->getKey(),
        ]);
    }

    public function sendToRoles(
        array $roleSlugs,
        string $type,
        string $title,
        string $message,
        string $severity = 'info',
        ?string $actionUrl = null,
        ?Model $reference = null,
        ?int $excludeUserId = null
    ): void {
        $users = User::query()
            ->where('is_active', true)
            ->whereHas('role', fn ($q) => $q->whereIn('slug', $roleSlugs))
            ->when($excludeUserId !== null, fn ($q) => $q->where('id', '!=', $excludeUserId))
            ->get(['id']);

        foreach ($users as $user) {
            $this->sendToUser($user->id, $type, $title, $message, $severity, $actionUrl, $reference);
        }
    }
}
