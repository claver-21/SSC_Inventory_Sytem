<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    public const GENERIC_LOGIN_ERROR = 'Invalid email or password, or account temporarily locked.';

    public function __construct(
        protected AuditLogService $auditLog
    ) {
    }

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $email = Str::lower(trim($credentials['email']));
        $ipKey = 'login-ip:' . $request->ip();

        if (RateLimiter::tooManyAttempts($ipKey, 20)) {
            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => self::GENERIC_LOGIN_ERROR]);
        }

        $user = User::where('email', $email)->first();

        if (! $user || ! $user->is_active || $user->isLocked()) {
            RateLimiter::hit($ipKey, 60);

            if ($user) {
                $this->auditLog->log(
                    action: 'LOGIN_FAILED',
                    module: 'security',
                    auditable: $user,
                    description: $user->isLocked()
                        ? "Blocked login attempt on locked account {$user->email}."
                        : "Blocked login attempt on inactive account {$user->email}.",
                    recordLabel: $user->email,
                    actor: $user
                );
            } else {
                $this->auditLog->log(
                    action: 'LOGIN_FAILED',
                    module: 'security',
                    description: 'Failed login attempt with unrecognized email.',
                    recordLabel: $email
                );
            }

            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => self::GENERIC_LOGIN_ERROR]);
        }

        if (! Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($ipKey, 60);

            $attempts = (int) $user->failed_login_attempts + 1;
            $updates = ['failed_login_attempts' => $attempts];

            if ($attempts >= 5) {
                $updates['locked_until'] = now()->addMinutes(15);
            }

            $user->forceFill($updates)->save();

            $this->auditLog->log(
                action: $attempts >= 5 ? 'ACCOUNT_LOCKED' : 'LOGIN_FAILED',
                module: 'security',
                auditable: $user,
                newValues: ['failed_attempts' => $attempts],
                description: $attempts >= 5
                    ? "Account {$user->email} locked for 15 minutes after {$attempts} failed password attempts."
                    : "Failed password attempt #{$attempts} for {$user->email}.",
                recordLabel: $user->email,
                actor: $user
            );

            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => self::GENERIC_LOGIN_ERROR]);
        }

        // Valid password: clear lock counters and require TOTP MFA verification/setup
        RateLimiter::clear($ipKey);
        $user->forceFill([
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ])->save();

        if (! config('auth.mfa_enabled', true)) {
            $this->startDirectSession($request, $user, $request->boolean('remember'));

            $this->auditLog->log(
                action: 'USER_LOGIN',
                module: 'auth',
                auditable: $user,
                description: "User {$user->name} ({$user->email}) signed in.",
                recordLabel: $user->email,
                actor: $user
            );

            return redirect()->intended(route('dashboard'));
        }

        $request->session()->regenerate();
        $request->session()->put('mfa_pending_user_id', $user->id);
        $request->session()->put('mfa_remember', $request->boolean('remember'));
        $request->session()->forget('mfa_verified');

        if (! $user->mfa_enabled) {
            return redirect()->route('mfa.setup');
        }

        return redirect()->route('mfa.verify');
    }

    public function logout(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user) {
            $this->auditLog->log(
                action: 'USER_LOGOUT',
                module: 'auth',
                auditable: $user,
                description: "User {$user->name} ({$user->email}) signed out.",
                recordLabel: $user->email,
                actor: $user
            );
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'You have been signed out securely.');
    }

    private function startDirectSession(Request $request, User $user, bool $remember = false): void
    {
        Auth::login($user, $remember);
        $user->forceFill(['last_login_at' => now()])->save();

        $request->session()->regenerate();
        $request->session()->put('mfa_verified', true);
        $request->session()->put('last_activity_at', now()->timestamp);
    }
}
