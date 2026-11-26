<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Rules\StrongPassword;
use App\Services\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthAndMfaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_public_registration_is_disabled_and_only_admin_can_create_ssc_accounts(): void
    {
        // Public self-registration endpoints are removed
        $this->get('/register')->assertStatus(404);
        $this->post('/register', [
            'name' => 'Public User',
            'email' => 'outsider@pcb-ssc.edu.ph',
            'password' => 'StrongPass@2026!',
        ])->assertStatus(404);

        $memberRole = Role::where('slug', Role::SLUG_MEMBER)->firstOrFail();
        $student = User::where('email', 'student@pcb-ssc.edu.ph')->firstOrFail();

        // SSC Student cannot access Admin account creation endpoints
        $this->actingAsVerified($student)
            ->get(route('users.create'))
            ->assertStatus(403);

        $this->actingAsVerified($student)
            ->post(route('users.store'), [
                'name' => 'Unauthorized Account',
                'email' => 'unauth@pcb-ssc.edu.ph',
                'role_id' => $memberRole->id,
                'password' => 'UniqueSSC#2026!',
                'password_confirmation' => 'UniqueSSC#2026!',
            ])->assertStatus(403);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ACCESS_DENIED',
            'module' => 'security',
            'record_label' => $student->email,
        ]);

        $admin = User::where('email', 'admin@pcb-ssc.edu.ph')->firstOrFail();

        // Reject non-SSC email domain even when submitted by Admin
        $this->actingAsVerified($admin)->post(route('users.store'), [
            'name' => 'External Student',
            'email' => 'outsider@gmail.com',
            'role_id' => $memberRole->id,
            'school_id' => 'EXT-001',
            'position' => 'SSC Student',
            'password' => 'StrongPass@2026',
            'password_confirmation' => 'StrongPass@2026',
        ])->assertSessionHasErrors('email');

        // Reject short (<12 chars) and common blocklisted password
        $this->actingAsVerified($admin)->post(route('users.store'), [
            'name' => 'SSC Member',
            'email' => 'member2@pcb-ssc.edu.ph',
            'role_id' => $memberRole->id,
            'password' => 'Short123!',
            'password_confirmation' => 'Short123!',
        ])->assertSessionHasErrors('password');

        $this->actingAsVerified($admin)->post(route('users.store'), [
            'name' => 'SSC Member',
            'email' => 'member2@pcb-ssc.edu.ph',
            'role_id' => $memberRole->id,
            'password' => 'Password1234',
            'password_confirmation' => 'Password1234',
        ])->assertSessionHasErrors('password');

        // Valid Admin account creation succeeds -> redirects to users.index, hashes password, logs USER_CREATED
        $this->actingAsVerified($admin)->post(route('users.store'), [
            'name' => 'New SSC Senator',
            'email' => 'senator.new@pcb-ssc.edu.ph',
            'role_id' => $memberRole->id,
            'school_id' => 'PCB-2026-101',
            'position' => 'SSC Student',
            'password' => 'UniqueSSC#2026!',
            'password_confirmation' => 'UniqueSSC#2026!',
            'is_active' => '1',
        ])->assertRedirect(route('users.index'));

        $created = User::where('email', 'senator.new@pcb-ssc.edu.ph')->firstOrFail();
        $this->assertNotSame('UniqueSSC#2026!', $created->password);
        $this->assertTrue(Hash::check('UniqueSSC#2026!', $created->password));
        $this->assertNotNull($created->privacy_consent_at);
        $this->assertTrue($created->isMember());
        $this->assertDatabaseHas('audit_logs', ['action' => 'USER_CREATED', 'record_label' => $created->email]);
    }

    public function test_defense_test_1_five_wrong_passwords_lock_account_with_generic_error(): void
    {
        $student = User::where('email', 'student@pcb-ssc.edu.ph')->firstOrFail();

        for ($i = 1; $i <= 5; $i++) {
            $res = $this->post(route('login.attempt'), [
                'email' => $student->email,
                'password' => 'WrongPassword123!',
            ]);
            $res->assertSessionHasErrors('email');
            $this->assertStringContainsString(
                'Invalid email or password, or account temporarily locked.',
                session('errors')->first('email')
            );
        }

        $student->refresh();
        $this->assertSame(5, $student->failed_login_attempts);
        $this->assertTrue($student->isLocked());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ACCOUNT_LOCKED',
            'record_label' => $student->email,
        ]);

        // Even with the right password, locked account cannot sign in & shows identical generic error
        $this->post(route('login.attempt'), [
            'email' => $student->email,
            'password' => 'Student@PCB2026!',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();

        // Unknown email shows identical generic error (prevents account enumeration)
        $this->post(route('login.attempt'), [
            'email' => 'nonexistent@pcb-ssc.edu.ph',
            'password' => 'Student@PCB2026!',
        ])->assertSessionHasErrors('email');
        $this->assertStringContainsString(
            'Invalid email or password, or account temporarily locked.',
            session('errors')->first('email')
        );
    }

    public function test_defense_test_2_full_totp_mfa_login_backup_code_and_step_up_prompt(): void
    {
        $admin = User::where('email', 'admin@pcb-ssc.edu.ph')->firstOrFail();
        $totp = app(TotpService::class);

        // 1. First sign-in prompts MFA setup
        $this->post(route('login.attempt'), [
            'email' => $admin->email,
            'password' => 'Admin@PCB2026!',
        ])->assertRedirect(route('mfa.setup'));

        // Visit setup page to generate secret in session
        $this->get(route('mfa.setup'))->assertStatus(200);
        $secret = session('mfa_setup_secret');
        $this->assertNotEmpty($secret);

        // Confirm setup with valid 6-digit TOTP code -> displays 8 backup codes
        $code = $totp->getCode($secret);
        $this->post(route('mfa.setup.confirm'), ['code' => $code])
            ->assertStatus(200)
            ->assertSee('Save Your One-Time Backup Codes');

        $admin->refresh();
        $this->assertTrue($admin->mfa_enabled);
        $this->assertSame(8, $admin->backupCodes()->whereNull('used_at')->count());

        // Logout and sign back in using MFA Verify challenge
        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->post(route('login.attempt'), [
            'email' => $admin->email,
            'password' => 'Admin@PCB2026!',
        ])->assertRedirect(route('mfa.verify'));

        $freshCode = $totp->getCode($admin->mfa_secret);
        $this->post(route('mfa.verify.attempt'), ['code' => $freshCode])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($admin);

        // 2. Attempting to archive an inventory item without recent Step-Up redirects to Step-Up MFA
        $item = \App\Models\InventoryItem::firstOrFail();
        $this->delete(route('inventory.archive', $item))
            ->assertRedirect(route('mfa.step-up'));

        // Completing Step-Up MFA allows the sensitive delete/archive
        $this->post(route('mfa.step-up.verify'), ['code' => $totp->getCode($admin->mfa_secret)])
            ->assertRedirect();
        $this->assertNotNull(session('step_up_verified_at'));

        $this->delete(route('inventory.archive', $item))
            ->assertRedirect(route('inventory.index'));
        $this->assertSoftDeleted('inventory_items', ['id' => $item->id]);
    }

    public function test_idle_session_timeout_logs_out_inactive_admin(): void
    {
        $admin = User::where('email', 'admin@pcb-ssc.edu.ph')->firstOrFail();
        $this->actingAsVerified($admin)
            ->withSession([
                'mfa_verified' => true,
                'last_activity_at' => now()->subMinutes(12)->timestamp,
            ])
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'SESSION_IDLE_TIMEOUT',
            'record_label' => $admin->email,
        ]);
    }

    public function test_normal_login_admin_account_creation_and_sensitive_actions_bypass_mfa_when_disabled(): void
    {
        config(['auth.mfa_enabled' => false]);

        $student = User::where('email', 'student@pcb-ssc.edu.ph')->firstOrFail();

        $this->post(route('login.attempt'), [
            'email' => $student->email,
            'password' => 'Student@PCB2026!',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($student);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'USER_LOGIN',
            'record_label' => $student->email,
        ]);

        $this->post(route('logout'))->assertRedirect(route('login'));

        $admin = User::where('email', 'admin@pcb-ssc.edu.ph')->firstOrFail();
        $admin->forceFill([
            'mfa_enabled' => true,
            'mfa_secret' => app(TotpService::class)->generateSecret(),
            'mfa_confirmed_at' => now(),
        ])->save();

        $this->post(route('login.attempt'), [
            'email' => $admin->email,
            'password' => 'Admin@PCB2026!',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($admin);
        $this->get(route('dashboard'))->assertStatus(200);

        $item = \App\Models\InventoryItem::firstOrFail();
        $this->delete(route('inventory.archive', $item))
            ->assertRedirect(route('inventory.index'));
        $this->assertSoftDeleted('inventory_items', ['id' => $item->id]);

        $this->get(route('settings.backup'))->assertStatus(200);

        $this->get(route('mfa.setup'))->assertRedirect(route('dashboard'));
        $this->get(route('mfa.verify'))->assertRedirect(route('dashboard'));

        $memberRole = Role::where('slug', Role::SLUG_MEMBER)->firstOrFail();
        $this->post(route('users.store'), [
            'name' => 'Direct Login Member',
            'email' => 'direct.member@pcb-ssc.edu.ph',
            'role_id' => $memberRole->id,
            'school_id' => 'PCB-2026-205',
            'position' => 'SSC Student',
            'password' => 'DirectPass#2026!',
            'password_confirmation' => 'DirectPass#2026!',
            'is_active' => '1',
        ])->assertRedirect(route('users.index'));

        $this->post(route('logout'))->assertRedirect(route('login'));

        $created = User::where('email', 'direct.member@pcb-ssc.edu.ph')->firstOrFail();
        $this->post(route('login.attempt'), [
            'email' => $created->email,
            'password' => 'DirectPass#2026!',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($created);
    }

    public function test_admin_can_create_user_with_auto_generated_password_and_remove_user(): void
    {
        $admin = User::where('email', 'admin@pcb-ssc.edu.ph')->firstOrFail();
        $memberRole = Role::where('slug', Role::SLUG_MEMBER)->firstOrFail();

        $this->actingAsVerified($admin, true)
            ->post(route('users.store'), [
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz',
                'email' => 'juandelacruz@pcb-ssc.edu.ph',
                'school_id' => '24-00182',
                'sex' => 'Male',
                'role_id' => $memberRole->id,
                'position' => 'SSC Secretary',
                'password' => '',
            ])->assertRedirect(route('users.index'));

        $created = User::where('email', 'juandelacruz@pcb-ssc.edu.ph')->firstOrFail();
        $this->assertSame('Juan Dela Cruz', $created->name);
        $this->assertSame('Male', $created->sex);
        $this->assertSame('24-00182', $created->school_id);
        $this->assertSame('SSC Secretary', $created->position);
        $this->assertTrue(Hash::check('Juandelacruz00182', $created->password));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'USER_CREATED',
            'record_label' => $created->email,
        ]);

        // Admin can remove the created account (soft delete + USER_REMOVED log)
        $this->actingAsVerified($admin, true)
            ->delete(route('users.destroy', $created))
            ->assertRedirect(route('users.index'));

        $this->assertSoftDeleted('users', ['id' => $created->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'USER_REMOVED',
            'record_label' => $created->email,
        ]);

        // Admin cannot remove their own account
        $this->actingAsVerified($admin, true)
            ->delete(route('users.destroy', $admin))
            ->assertSessionHas('error');
        $this->assertNotSoftDeleted('users', ['id' => $admin->id]);
    }
}
