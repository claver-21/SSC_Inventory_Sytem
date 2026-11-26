<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserMfaBackupCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TotpService
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(int $length = 32): string
    {
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::BASE32_ALPHABET[random_int(0, 31)];
        }

        return $secret;
    }

    public function getProvisioningUri(User $user, string $secret, string $issuer = 'PCB-SSC SecureLocal'): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&digits=6&period=30',
            rawurlencode($issuer),
            rawurlencode($user->email),
            $secret,
            rawurlencode($issuer)
        );
    }

    public function getCode(string $secret, ?int $timeSlice = null): string
    {
        $timeSlice ??= (int) floor(time() / 30);
        $secretKey = $this->base32Decode($secret);
        $timeBytes = pack('N*', 0) . pack('N*', $timeSlice);
        $hash = hash_hmac('sha1', $timeBytes, $secretKey, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $truncated = substr($hash, $offset, 4);
        $value = unpack('N', $truncated)[1] & 0x7FFFFFFF;

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    public function verifyCode(string $secret, string $code, int $window = 1): bool
    {
        $normalized = preg_replace('/\s+/', '', trim($code));
        if (! preg_match('/^\d{6}$/', (string) $normalized)) {
            return false;
        }

        $currentSlice = (int) floor(time() / 30);
        for ($offset = -$window; $offset <= $window; $offset++) {
            $expected = $this->getCode($secret, $currentSlice + $offset);
            if (hash_equals($expected, $normalized)) {
                return true;
            }
        }

        return false;
    }

    public function generateAndStoreBackupCodes(User $user, int $count = 8): array
    {
        $user->backupCodes()->delete();

        $plaintextCodes = [];
        for ($i = 0; $i < $count; $i++) {
            $raw = strtoupper(Str::random(4) . '-' . Str::random(4));
            $plaintextCodes[] = $raw;

            UserMfaBackupCode::create([
                'user_id' => $user->id,
                'code_hash' => Hash::make($raw),
            ]);
        }

        return $plaintextCodes;
    }

    public function verifyAndConsumeBackupCode(User $user, string $inputCode): bool
    {
        $normalized = strtoupper(trim($inputCode));
        if ($normalized === '') {
            return false;
        }

        $codes = $user->backupCodes()->whereNull('used_at')->get();
        foreach ($codes as $codeRecord) {
            if (Hash::check($normalized, $codeRecord->code_hash)) {
                $codeRecord->update(['used_at' => now()]);
                return true;
            }
        }

        return false;
    }

    private function base32Decode(string $secret): string
    {
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret) ?? '');
        $binary = '';
        $output = '';

        foreach (str_split($secret) as $char) {
            $index = strpos(self::BASE32_ALPHABET, $char);
            if ($index === false) {
                continue;
            }
            $binary .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }

        foreach (str_split($binary, 8) as $byte) {
            if (strlen($byte) === 8) {
                $output .= chr((int) bindec($byte));
            }
        }

        return $output;
    }
}
