<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

class StrongPassword implements ValidationRule
{
    public const BLOCKLIST = [
        'password1234',
        'password12345',
        '123456789012',
        '1234567890123',
        'qwerty123456',
        'admin1234567',
        'admin@123456',
        'letmein12345',
        'welcome12345',
        'pcb123456789',
        'studentcouncil',
        'botolan12345',
        'changeme1234',
        'iloveyou1234',
        'P@ssw0rd1234',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $password = (string) $value;

        if (mb_strlen($password) < 12) {
            $fail('The :attribute must be at least 12 characters long.');
            return;
        }

        if (! preg_match('/[A-Z]/', $password) || ! preg_match('/[a-z]/', $password) || ! preg_match('/[0-9]/', $password)) {
            $fail('The :attribute must include uppercase letters, lowercase letters, and at least one number.');
            return;
        }

        $lower = Str::lower($password);
        foreach (self::BLOCKLIST as $common) {
            if ($lower === Str::lower($common)) {
                $fail('That password is too common or on the security blocklist. Please choose a unique passphrase.');
                return;
            }
        }
    }
}
