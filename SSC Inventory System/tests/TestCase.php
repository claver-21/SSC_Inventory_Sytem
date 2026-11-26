<?php

namespace Tests;

use App\Models\User;
use App\Services\TotpService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function actingAsVerified(User $user, bool $stepUp = false): static
    {
        if (! $user->mfa_enabled || ! $user->mfa_secret) {
            $user->forceFill([
                'mfa_enabled' => true,
                'mfa_secret' => app(TotpService::class)->generateSecret(),
                'mfa_confirmed_at' => now(),
            ])->save();
        }

        $session = [
            'mfa_verified' => true,
            'last_activity_at' => now()->timestamp,
        ];

        if ($stepUp) {
            $session['step_up_verified_at'] = now()->timestamp;
        }

        return $this->actingAs($user)->withSession($session);
    }
}
