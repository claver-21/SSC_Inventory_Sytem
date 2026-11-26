<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogService;
use App\Services\TotpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class MfaController extends Controller
{
    public function __construct(
        protected TotpService $totp,
        protected AuditLogService $auditLog
    ) {
    }

    public function showSetup(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->redirectWhenMfaDisabled()) {
            return $redirect;
        }

        $user = $this->resolveTargetUser($request);
        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->mfa_enabled && $request->session()->get('mfa_verified') === true) {
            return redirect()->route('dashboard');
        }

        $secret = $request->session()->get('mfa_setup_secret');
        if (! $secret) {
            $secret = $this->totp->generateSecret();
            $request->session()->put('mfa_setup_secret', $secret);
        }

        $otpAuthUri = $this->totp->getProvisioningUri($user, $secret);

        return view('auth.mfa-setup', [
            'user' => $user,
            'secret' => $secret,
            'otpAuthUri' => $otpAuthUri,
        ]);
    }

    public function confirmSetup(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->redirectWhenMfaDisabled()) {
            return $redirect;
        }

        $user = $this->resolveTargetUser($request);
        if (! $user) {
            return redirect()->route('login');
        }

        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $secret = (string) $request->session()->get('mfa_setup_secret');
        if ($secret === '' || ! $this->totp->verifyCode($secret, $validated['code'])) {
            return back()->withErrors([
                'code' => 'Invalid 6-digit authenticator code. Please check your authenticator app time and try again.',
            ]);
        }

        $user->forceFill([
            'mfa_enabled' => true,
            'mfa_secret' => $secret,
            'mfa_confirmed_at' => now(),
            'last_login_at' => now(),
        ])->save();

        $backupCodes = $this->totp->generateAndStoreBackupCodes($user, 8);

        $remember = (bool) $request->session()->pull('mfa_remember', false);
        $request->session()->forget(['mfa_setup_secret', 'mfa_pending_user_id']);

        if (! Auth::check() || Auth::id() !== $user->id) {
            Auth::loginUsingId($user->id, $remember);
        }

        $request->session()->regenerate();
        $request->session()->put('mfa_verified', true);
        $request->session()->put('last_activity_at', now()->timestamp);
        $request->session()->put('step_up_verified_at', now()->timestamp);

        $this->auditLog->log(
            action: 'MFA_ENROLLED',
            module: 'security',
            auditable: $user,
            description: "Enrolled TOTP 2FA and generated hashed backup codes for {$user->email}.",
            recordLabel: $user->email,
            actor: $user
        );

        return view('auth.mfa-backup-codes', [
            'user' => $user,
            'backupCodes' => $backupCodes,
        ]);
    }

    public function showVerify(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->redirectWhenMfaDisabled()) {
            return $redirect;
        }

        $user = $this->resolveTargetUser($request);
        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->mfa_enabled) {
            return redirect()->route('mfa.setup');
        }

        return view('auth.mfa-verify', [
            'user' => $user,
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        if ($redirect = $this->redirectWhenMfaDisabled()) {
            return $redirect;
        }

        $user = $this->resolveTargetUser($request);
        if (! $user || ! $user->mfa_enabled || ! $user->mfa_secret) {
            return redirect()->route('login');
        }

        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $throttleKey = 'mfa-verify:' . $user->id;
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()->withErrors([
                'code' => "Too many verification attempts. Wait {$seconds} seconds before trying again.",
            ]);
        }

        $codeInput = trim($validated['code']);
        $verifiedTotp = $this->totp->verifyCode($user->mfa_secret, $codeInput);
        $verifiedBackup = ! $verifiedTotp && $this->totp->verifyAndConsumeBackupCode($user, $codeInput);

        if (! $verifiedTotp && ! $verifiedBackup) {
            RateLimiter::hit($throttleKey, 60);

            $this->auditLog->log(
                action: 'MFA_FAILED',
                module: 'security',
                auditable: $user,
                description: "Failed MFA verification attempt for {$user->email}.",
                recordLabel: $user->email,
                actor: $user
            );

            return back()->withErrors([
                'code' => 'Invalid authenticator or backup code.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        $remember = (bool) $request->session()->pull('mfa_remember', false);
        $request->session()->forget('mfa_pending_user_id');

        if (! Auth::check() || Auth::id() !== $user->id) {
            Auth::loginUsingId($user->id, $remember);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        $request->session()->regenerate();
        $request->session()->put('mfa_verified', true);
        $request->session()->put('last_activity_at', now()->timestamp);

        $this->auditLog->log(
            action: $verifiedBackup ? 'MFA_BACKUP_CODE_USED' : 'MFA_VERIFIED',
            module: 'security',
            auditable: $user,
            description: $verifiedBackup
                ? "Signed in using single-use hashed backup code ({$user->email})."
                : "Signed in with TOTP MFA verification ({$user->email}).",
            recordLabel: $user->email,
            actor: $user
        );

        return redirect()->intended(route('dashboard'));
    }

    public function showStepUp(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->redirectWhenMfaDisabled()) {
            return $redirect;
        }

        return view('auth.mfa-step-up', [
            'user' => $request->user(),
        ]);
    }

    public function verifyStepUp(Request $request): RedirectResponse
    {
        if ($redirect = $this->redirectWhenMfaDisabled()) {
            return $redirect;
        }

        $user = $request->user();
        if (! $user->mfa_enabled || ! $user->mfa_secret) {
            return redirect()->route('mfa.setup');
        }

        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $codeInput = trim($validated['code']);
        $verifiedTotp = $this->totp->verifyCode($user->mfa_secret, $codeInput);
        $verifiedBackup = ! $verifiedTotp && $this->totp->verifyAndConsumeBackupCode($user, $codeInput);

        if (! $verifiedTotp && ! $verifiedBackup) {
            $this->auditLog->log(
                action: 'STEP_UP_FAILED',
                module: 'security',
                auditable: $user,
                description: "Failed step-up MFA challenge for {$user->email}.",
                recordLabel: $user->email,
                actor: $user
            );

            return back()->withErrors([
                'code' => 'Invalid 6-digit MFA code or backup code.',
            ]);
        }

        $request->session()->put('step_up_verified_at', now()->timestamp);
        $intended = (string) $request->session()->pull('step_up_intended_url', route('dashboard'));

        $this->auditLog->log(
            action: 'STEP_UP_VERIFIED',
            module: 'security',
            auditable: $user,
            description: "Step-up MFA verification completed by {$user->email}.",
            recordLabel: $user->email,
            actor: $user
        );

        return redirect($intended)->with('success', 'Step-up verification granted for 5 minutes. Please proceed with your action.');
    }

    public function regenerateBackupCodes(Request $request): View
    {
        $user = $request->user();
        $backupCodes = $this->totp->generateAndStoreBackupCodes($user, 8);

        $this->auditLog->log(
            action: 'MFA_BACKUP_REGENERATED',
            module: 'security',
            auditable: $user,
            description: "Regenerated 8 single-use MFA backup codes for {$user->email}.",
            recordLabel: $user->email,
            actor: $user
        );

        return view('auth.mfa-backup-codes', [
            'user' => $user,
            'backupCodes' => $backupCodes,
        ]);
    }

    private function resolveTargetUser(Request $request): ?User
    {
        if (Auth::check()) {
            return Auth::user();
        }

        $pendingId = $request->session()->get('mfa_pending_user_id');
        if (! $pendingId) {
            return null;
        }

        $user = User::find($pendingId);
        if (! $user || ! $user->is_active) {
            $request->session()->forget('mfa_pending_user_id');
            return null;
        }

        return $user;
    }

    private function redirectWhenMfaDisabled(): ?RedirectResponse
    {
        if (config('auth.mfa_enabled', true)) {
            return null;
        }

        return Auth::check()
            ? redirect()->route('dashboard')
            : redirect()->route('login');
    }
}
