<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Donor;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Tests\TestCase;

class StaffSecurityTest extends TestCase
{
    use RefreshDatabase;

    private string $password = 'Fictional-Password9!';

    private function staff(string $role = 'System Administrator'): User
    {
        return User::factory()->create(['role_id' => Role::firstOrCreate(['name' => $role])->id, 'active' => true, 'password' => $this->password])->fresh();
    }

    private function signIn(User $user): void
    {
        $this->app['auth']->forgetGuards();
        $this->actingAs($user->fresh(), 'web');
        $this->withSession(['account_session_version' => $user->fresh()->session_version]);
    }

    public function test_account_creation_is_reauthenticated_retry_safe_and_private(): void
    {
        $admin = $this->staff();
        $this->signIn($admin);
        $body = ['name' => 'QA Worker', 'email' => 'qa.worker@example.test', 'role' => 'Red Cross Personnel', 'password' => $this->password, 'password_confirmation' => $this->password, 'current_password' => $this->password, 'reason' => 'Fictional provisioning'];
        $headers = ['Idempotency-Key' => (string) Str::uuid()];
        $this->postJson('/api/v1/staff', [...$body, 'current_password' => 'wrong'], $headers)->assertUnprocessable();
        $id = $this->postJson('/api/v1/staff', $body, $headers)->assertCreated()->assertJsonPath('data.version', 1)->assertJsonMissingPath('data.password')->json('data.id');
        $this->postJson('/api/v1/staff', $body, $headers)->assertCreated()->assertJsonPath('data.id', $id);
        $this->postJson('/api/v1/staff', [...$body, 'name' => 'Changed'], $headers)->assertConflict();
        $this->assertSame(2, User::count());
        $this->assertTrue(Hash::check($this->password, User::findOrFail($id)->password));
        $this->assertStringNotContainsString($this->password, AuditLog::all()->toJson());
        $this->getJson('/api/v1/staff')->assertOk()->assertJsonMissingPath('data.0.provisioning_hash');
        $this->signIn($this->staff('Red Cross Administrator'));
        $this->getJson('/api/v1/staff')->assertForbidden();
    }

    public function test_access_changes_revoke_assignments_and_prior_sessions_without_self_lockout(): void
    {
        $admin = $this->staff();
        $worker = $this->staff('Red Cross Personnel');
        $donor = Donor::factory()->create(['created_by' => $worker->id, 'updated_by' => $worker->id]);
        $this->signIn($admin);
        $body = ['name' => $worker->name, 'role' => 'Report Viewer', 'active' => true, 'version' => 1, 'current_password' => $this->password, 'reason' => 'Change fictional responsibilities'];
        $this->patchJson('/api/v1/staff/'.$worker->id, $body)->assertOk()->assertJsonPath('data.version', 2);
        $this->patchJson('/api/v1/staff/'.$worker->id, $body)->assertConflict();
        $this->assertDatabaseHas('donor_assignments', ['donor_id' => $donor->id, 'user_id' => $worker->id, 'active' => false]);
        $this->patchJson('/api/v1/staff/'.$admin->id, [...$body, 'role' => 'System Administrator', 'active' => false])->assertUnprocessable();
        $this->app['auth']->forgetGuards();
        $this->actingAs($worker->fresh(), 'web')->withSession(['account_session_version' => 1]);
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->signIn($worker);
        $this->getJson('/api/v1/reports/inventory')->assertOk();
    }

    public function test_password_reset_is_single_use_and_invalidates_sessions(): void
    {
        $user = $this->staff();
        $token = Password::createToken($user);
        $new = 'Different-Fictional9!';
        $body = ['email' => $user->email, 'token' => $token, 'password' => $new, 'password_confirmation' => $new];
        $this->postJson('/api/v1/auth/reset-password', [...$body, 'token' => 'wrong'])->assertUnprocessable();
        $this->postJson('/api/v1/auth/reset-password', $body)->assertOk();
        $this->assertTrue(Hash::check($new, $user->fresh()->password));
        $this->postJson('/api/v1/auth/reset-password', $body)->assertUnprocessable();
        $this->assertSame(2, $user->fresh()->session_version);
        $this->actingAs($user->fresh(), 'web')->withSession(['account_session_version' => 1]);
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_email_recovery_is_disabled_until_configured_and_avoids_account_enumeration(): void
    {
        $user = $this->staff();
        $this->getJson('/api/v1/auth/recovery')->assertOk()->assertJsonPath('email_recovery_enabled', false);
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertStatus(503);
        Notification::fake();
        config(['repository.password_reset_email' => true, 'mail.default' => 'smtp']);
        $known = $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk()->json();
        $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'unknown@example.test'])->assertOk()->json();
        $this->assertSame($known, $unknown);
        Notification::assertSentTo($user, ResetPassword::class);
        $this->assertDatabaseCount('password_reset_tokens', 1);
    }

    public function test_change_password_keeps_current_session_and_audit_failure_rolls_back(): void
    {
        $user = $this->staff();
        $this->signIn($user);
        $new = 'Changed-Fictional9!';
        $body = ['current_password' => $this->password, 'password' => $new, 'password_confirmation' => $new];
        AuditLog::creating(function () {
            throw new \RuntimeException('Fictional audit outage');
        });
        try {
            $this->postJson('/api/v1/auth/password', $body)->assertStatus(500);
        } finally {
            AuditLog::flushEventListeners();
        }
        $this->assertTrue(Hash::check($this->password, $user->fresh()->password));
        $this->postJson('/api/v1/auth/password', $body)->assertOk();
        $this->app['auth']->forgetGuards();
        $this->actingAs($user->fresh(), 'web');
        $this->getJson('/api/v1/me')->assertOk();
    }

    public function test_staff_writes_revalidate_actor_after_authorization_was_loaded(): void
    {
        $worker = $this->staff('Red Cross Personnel');
        $viewerRole = Role::firstOrCreate(['name' => 'Report Viewer']);
        foreach ([['active' => false], ['session_version' => 2], ['role_id' => $viewerRole->id], ['password' => Hash::make('Different-Fictional9!')]] as $changed) {
            $admin = $this->staff();
            $this->signIn($admin);
            DB::table('users')->where('id', $admin->id)->update($changed);
            $status = isset($changed['role_id']) ? 403 : (isset($changed['password']) ? 422 : 401);
            $body = ['name' => 'QA Unchanged', 'role' => 'Red Cross Personnel', 'active' => true, 'version' => 1, 'current_password' => $this->password, 'reason' => 'Fictional stale authorization'];
            $this->patchJson('/api/v1/staff/'.$worker->id, $body)->assertStatus($status);
            $this->assertSame(1, $worker->fresh()->version);

            $admin = $this->staff();
            $this->signIn($admin);
            DB::table('users')->where('id', $admin->id)->update($changed);
            $this->postJson('/api/v1/staff', [...$body, 'email' => 'qa.stale@example.test', 'password' => $this->password, 'password_confirmation' => $this->password], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus($status);
            $this->assertDatabaseMissing('users', ['email' => 'qa.stale@example.test']);
        }
        $this->assertDatabaseMissing('audit_logs', ['action' => 'staff.updated']);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'staff.created']);
    }

    public function test_staff_update_accepts_valid_string_version_and_boolean_without_revoking_access(): void
    {
        $admin = $this->staff();
        $this->signIn($admin);
        $this->patchJson('/api/v1/staff/'.$admin->id, ['name' => 'QA Renamed Administrator', 'role' => 'System Administrator', 'active' => '1', 'version' => '1', 'current_password' => $this->password, 'reason' => 'Correct fictional display name'])->assertOk()->assertJsonPath('data.version', 2)->assertJsonPath('data.active', true);
        $this->assertSame(1, $admin->fresh()->session_version);
    }

    public function test_password_change_rejects_revoked_session_after_authorization_was_loaded(): void
    {
        foreach ([['active' => false], ['session_version' => 2]] as $changed) {
            $user = $this->staff();
            $this->signIn($user);
            DB::table('users')->where('id', $user->id)->update($changed);
            $new = 'Changed-Fictional9!';
            $this->postJson('/api/v1/auth/password', ['current_password' => $this->password, 'password' => $new, 'password_confirmation' => $new])->assertUnauthorized();
            $this->assertTrue(Hash::check($this->password, $user->fresh()->password));
        }
        $this->assertDatabaseMissing('audit_logs', ['action' => 'auth.password_changed']);
    }
}
