<?php

namespace App\Services;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    private const CACHE_KEY = 'pcb_ssc_system_settings';

    public function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            return SystemSetting::query()
                ->pluck('value', 'key')
                ->toArray();
        });
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        return $all[$key] ?? $default;
    }

    public function allGrouped(): Collection
    {
        return SystemSetting::query()
            ->orderBy('group')
            ->orderBy('id')
            ->get()
            ->groupBy('group');
    }

    public function setMany(array $values, ?User $actor = null): array
    {
        $changes = [];

        foreach ($values as $key => $value) {
            $setting = SystemSetting::where('key', $key)->first();
            if (! $setting) {
                continue;
            }

            $normalized = is_bool($value) ? ($value ? '1' : '0') : (string) ($value ?? '');
            if ($setting->value !== $normalized) {
                $changes[$key] = [
                    'old' => $setting->value,
                    'new' => $normalized,
                ];
                $setting->update([
                    'value' => $normalized,
                    'updated_by' => $actor?->id,
                ]);
            }
        }

        $this->clearCache();

        return $changes;
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
