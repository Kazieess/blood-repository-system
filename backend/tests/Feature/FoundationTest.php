<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Donor;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role = 'Red Cross Administrator', bool $active = true): User
    {
        $record = Role::firstOrCreate(['name' => $role]);

        return User::factory()->create(['role_id' => $record->id, 'active' => $active]);
    }

    private function input(array $overrides = []): array
    {
        return [...['name' => 'Fictional Donor', 'blood_type' => 'O+', 'municipality' => 'Digos City'], ...$overrides];
    }

    public function test_unauthenticated_requests_are_safe_json(): void
    {
        $response = $this->getJson('/api/v1/donors');
        $response->assertUnauthorized()->assertJsonStructure(['message', 'code', 'request_id'])->assertHeader('X-Request-ID');
        $response->assertJsonMissingPath('exception')->assertJsonMissingPath('trace');
    }

    public function test_login_returns_server_permissions_without_secrets_and_logout_revokes_session(): void
    {
        $user = $this->staff();
        $response = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password']);
        $response->assertOk()->assertJsonPath('data.role', 'Red Cross Administrator')->assertJsonPath('data.permissions.0', 'donors.read');
        $response->assertJsonMissingPath('data.password')->assertJsonMissingPath('data.remember_token');
        $this->assertAuthenticatedAs($user, 'web');
        $this->getJson('/api/v1/me')->assertOk();
        $this->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->assertGuest('web');
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $user->id, 'action' => 'auth.logout']);
    }

    public function test_inactive_and_invalid_credentials_have_the_same_generic_failure(): void
    {
        $user = $this->staff(active: false);
        $inactive = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password']);
        $invalid = $this->postJson('/api/v1/auth/login', ['email' => 'missing@example.test', 'password' => 'wrong']);
        $inactive->assertUnprocessable();
        $invalid->assertUnprocessable();
        $this->assertSame($inactive->json('errors.email'), $invalid->json('errors.email'));
        $this->assertGuest();
    }

    public function test_login_is_throttled(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'missing@example.test', 'password' => 'wrong'])->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', ['email' => 'missing@example.test', 'password' => 'wrong'])->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_deactivation_blocks_an_existing_session(): void
    {
        $user = $this->staff();
        $this->actingAs($user, 'web');
        $user->update(['active' => false]);
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_registration_persists_a_uuid_and_atomic_audit_event(): void
    {
        $user = $this->staff();
        $response = $this->actingAs($user, 'web')->postJson('/api/v1/donors', $this->input(), ['Idempotency-Key' => (string) Str::uuid()]);
        $response->assertCreated()->assertJsonPath('data.version', 1)->assertJsonPath('data.record_status', 'for_review');
        $id = $response->json('data.id');
        $this->assertTrue(Str::isUuid($id));
        $this->assertDatabaseHas('donors', ['id' => $id, 'created_by' => $user->id]);
        $this->assertDatabaseHas('audit_logs', ['subject_id' => $id, 'action' => 'donor.created', 'actor_id' => $user->id]);
        $this->app['auth']->forgetGuards();
        $this->actingAs(User::findOrFail($user->id), 'web')->getJson('/api/v1/donors/'.$id)->assertOk()->assertJsonPath('data.name', 'Fictional Donor');
    }

    public function test_retrying_registration_does_not_duplicate_a_record_or_audit(): void
    {
        $this->actingAs($this->staff(), 'web');
        $key = (string) Str::uuid();
        $first = $this->postJson('/api/v1/donors', $this->input(), ['Idempotency-Key' => $key])->assertCreated();
        $this->postJson('/api/v1/donors', $this->input(), ['Idempotency-Key' => $key])->assertOk()->assertJsonPath('data.id', $first->json('data.id'));
        $this->assertDatabaseCount('donors', 1);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->postJson('/api/v1/donors', $this->input(['name' => 'Different Input']), ['Idempotency-Key' => $key])->assertConflict();
    }

    public function test_registration_rejects_missing_keys_invalid_fields_and_server_field_injection(): void
    {
        $this->actingAs($this->staff(), 'web');
        $this->postJson('/api/v1/donors', $this->input())->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
        $this->postJson('/api/v1/donors', $this->input(['name' => ' ', 'blood_type' => 'X', 'created_by' => 99]), ['Idempotency-Key' => (string) Str::uuid()])
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'blood_type', 'created_by']);
        $this->assertDatabaseCount('donors', 0);
    }

    public function test_coordinator_can_only_list_read_and_correct_their_own_records(): void
    {
        $coordinator = $this->staff('Barangay Health Coordinator');
        $other = $this->staff();
        $owned = Donor::factory()->create(['created_by' => $coordinator->id, 'updated_by' => $coordinator->id]);
        $foreign = Donor::factory()->create(['created_by' => $other->id, 'updated_by' => $other->id]);
        $this->actingAs($coordinator, 'web')->getJson('/api/v1/donors')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $owned->id);
        $this->getJson('/api/v1/donors/'.$foreign->id)->assertNotFound();
        $this->patchJson('/api/v1/donors/'.$foreign->id, $this->input(['version' => 1, 'reason' => 'Sample correction']))->assertNotFound();
        $this->patchJson('/api/v1/donors/'.$owned->id, $this->input(['version' => 1, 'reason' => 'Sample correction']))->assertOk();
    }

    public function test_read_only_and_technical_roles_cannot_read_or_create_donors(): void
    {
        foreach (['Report Viewer', 'System Administrator'] as $role) {
            $this->app['auth']->forgetGuards();
            $this->actingAs($this->staff($role), 'web')->getJson('/api/v1/donors')->assertForbidden();
            $this->postJson('/api/v1/donors', $this->input(), ['Idempotency-Key' => (string) Str::uuid()])->assertForbidden();
        }
    }

    public function test_search_filters_and_pagination_remain_inside_the_authorized_scope(): void
    {
        $user = $this->staff();
        Donor::factory()->count(12)->create(['created_by' => $user->id, 'updated_by' => $user->id, 'name' => 'Fictional Match', 'blood_type' => 'B+']);
        Donor::factory()->create(['created_by' => $user->id, 'updated_by' => $user->id, 'name' => 'Other Record', 'blood_type' => 'A+']);
        $this->actingAs($user, 'web')->getJson('/api/v1/donors?q=Fictional&blood_type=B%2B&per_page=5&page=2')
            ->assertOk()->assertJsonCount(5, 'data')->assertJsonPath('meta.total', 12)->assertJsonPath('meta.current_page', 2);
        $this->getJson('/api/v1/donors?q=%25')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/donors?per_page=500')->assertUnprocessable();
    }

    public function test_corrections_require_reason_and_current_version_without_lost_updates(): void
    {
        $user = $this->staff();
        $donor = Donor::factory()->create(['created_by' => $user->id, 'updated_by' => $user->id]);
        $this->actingAs($user, 'web')->patchJson('/api/v1/donors/'.$donor->id, $this->input(['version' => 1]))->assertUnprocessable();
        $body = $this->input(['version' => 1, 'reason' => 'Correcting sample municipality']);
        $this->patchJson('/api/v1/donors/'.$donor->id, $body)->assertOk()->assertJsonPath('data.version', 2);
        $this->patchJson('/api/v1/donors/'.$donor->id, [...$body, 'name' => 'Stale Overwrite'])->assertConflict();
        $this->assertDatabaseHas('donors', ['id' => $donor->id, 'name' => 'Fictional Donor', 'version' => 2]);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_audit_failure_rolls_back_the_donor_write(): void
    {
        $user = $this->staff();
        AuditLog::creating(function (): void {
            throw new \RuntimeException('Simulated audit failure');
        });

        try {
            $this->actingAs($user, 'web')->postJson('/api/v1/donors', $this->input(), ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(500)->assertJsonMissingPath('trace');
            $this->assertDatabaseCount('donors', 0);
        } finally {
            AuditLog::flushEventListeners();
        }
    }

    public function test_audit_is_restricted_and_does_not_return_donor_values(): void
    {
        $user = $this->staff();
        $this->actingAs($user, 'web')->postJson('/api/v1/donors', $this->input(), ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
        $response = $this->getJson('/api/v1/audit-logs')->assertOk()->assertJsonCount(1, 'data');
        $this->assertStringNotContainsString('Fictional Donor', $response->getContent());
        $this->assertStringNotContainsString('creation_hash', $response->getContent());
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->staff('Red Cross Personnel'), 'web')->getJson('/api/v1/audit-logs')->assertForbidden();
    }

    public function test_csrf_is_required_for_browser_writes_outside_the_test_bypass(): void
    {
        $this->app['env'] = 'local';
        $this->postJson('/api/v1/auth/login', ['email' => 'missing@example.test', 'password' => 'wrong'])->assertStatus(419);
        $this->get('/sanctum/csrf-cookie')->assertNoContent()->assertCookie('XSRF-TOKEN');
    }

    public function test_unexpected_failures_do_not_expose_sql_secrets_or_stack_traces(): void
    {
        Route::middleware('web')->get('/api/v1/test-failure', function (): void {
            throw new \RuntimeException('secret sql donor details');
        });
        config(['app.debug' => true]);
        $response = $this->getJson('/api/v1/test-failure')->assertStatus(500)->assertJsonStructure(['message', 'code', 'request_id']);
        $this->assertStringNotContainsString('secret sql', $response->getContent());
        $response->assertJsonMissingPath('trace');
    }

    public function test_frontend_entries_and_relocated_assets_are_served_without_exposing_server_files(): void
    {
        $workspace = $this->get('/workspace')->assertOk();
        $home = $this->get('/')->assertOk();
        $this->assertStringContainsString('src/connected.js', file_get_contents($workspace->baseResponse->getFile()->getPathname()));
        $this->assertStringContainsString('css/styles.css', file_get_contents($home->baseResponse->getFile()->getPathname()));
        $this->assertStringContainsString('css/experience.css', file_get_contents($home->baseResponse->getFile()->getPathname()));
        $this->get('/css/styles.css')->assertOk()->assertHeader('Content-Type', 'text/css; charset=utf-8');
        $this->get('/src/connected.js')->assertOk()->assertHeader('Content-Type', 'text/javascript; charset=utf-8');
        $this->get('/assets/images/Logo%20-%20Primary.png')->assertOk();
        $this->get('/src/.env')->assertNotFound();
    }

    public function test_database_connection_and_health_check_are_available(): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->getJson('/api/v1/health')->assertOk()->assertJsonPath('status', 'ok');
    }

    public function test_staff_provisioning_hashes_the_password_and_records_an_audit(): void
    {
        $this->artisan('app:create-user')
            ->expectsQuestion('Staff display name (use fictional details during development)', 'Fictional Staff')
            ->expectsQuestion('Email address', 'staff@example.test')
            ->expectsChoice('Role', 'Red Cross Administrator', Role::NAMES)
            ->expectsQuestion('Password (12+ characters, upper/lowercase, number, symbol)', 'Fictional-Only-123!')
            ->expectsQuestion('Confirm password', 'Fictional-Only-123!')
            ->assertSuccessful();
        $user = User::where('email', 'staff@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('Fictional-Only-123!', $user->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.provisioned', 'subject_id' => (string) $user->id]);
    }
}
