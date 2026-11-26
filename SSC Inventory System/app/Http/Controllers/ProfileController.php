<?php

namespace App\Http\Controllers;

use App\Rules\StrongPassword;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(protected AuditLogService $auditLog)
    {
    }

    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user()->load('role'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'school_id' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);

        $old = $user->only(['name', 'email', 'school_id', 'position', 'department', 'phone']);
        $user->update($validated);

        $this->auditLog->log(
            action: 'profile.updated',
            module: 'profile',
            auditable: $user,
            oldValues: $old,
            newValues: $validated,
            description: "Updated account profile for {$user->name}.",
            recordLabel: $user->email,
            actor: $user
        );

        return back()->with('success', 'Your profile information has been updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', new StrongPassword()],
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        $this->auditLog->log(
            action: 'profile.password_changed',
            module: 'profile',
            auditable: $user,
            description: "Password updated by {$user->name}.",
            recordLabel: $user->email,
            actor: $user
        );

        return back()->with('success', 'Your password has been updated securely.');
    }
}
