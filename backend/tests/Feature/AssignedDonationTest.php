<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Donor;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AssignedDonationTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role = 'Red Cross Administrator'): User
    {
        return User::factory()->create(['role_id' => Role::firstOrCreate(['name' => $role])->id, 'active' => true]);
    }

    private function asStaff(User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user, 'web');
    }

    private function donor(User $user): Donor
    {
        return Donor::factory()->create(['created_by' => $user->id, 'updated_by' => $user->id]);
    }

    public function test_only_assigned_staff_can_read_even_when_an_administrator(): void
    {
        $owner = $this->staff();
        $other = $this->staff();
        $donor = $this->donor($owner);
        $this->asStaff($other)->getJson('/api/v1/donors')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/donors/'.$donor->id)->assertNotFound();
        $this->getJson('/api/v1/assignments')->assertOk()->assertJsonMissingPath('data.0.name');
        $this->asStaff($owner)->getJson('/api/v1/donors/'.$donor->id)->assertOk()->assertJsonPath('data.clinical_permissions', []);
    }

    public function test_assignment_grants_revocation_and_regrant_preserve_monotonic_versions(): void
    {
        $admin = $this->staff();
        $worker = $this->staff('Red Cross Personnel');
        $donor = $this->donor($admin);
        $path = '/api/v1/assignments/'.$donor->id;
        $body = ['user_id' => $worker->id, 'version' => 0, 'clinical_permissions' => ['screening.read'], 'reason' => 'Assign fictional case'];
        $this->asStaff($worker)->putJson($path, $body)->assertForbidden();
        $this->asStaff($admin)->putJson($path, $body)->assertOk();
        $this->asStaff($worker)->getJson('/api/v1/donors/'.$donor->id)->assertOk()->assertJsonPath('data.clinical_permissions.0', 'screening.read');
        $this->asStaff($admin)->putJson($path, [...$body, 'version' => 1, 'revoke' => true])->assertOk();
        $this->asStaff($worker)->getJson('/api/v1/donors/'.$donor->id)->assertNotFound();
        $this->getJson('/api/v1/donors/'.$donor->id.'/donations')->assertNotFound();
        $this->asStaff($admin)->putJson($path, [...$body, 'version' => 2])->assertOk();
        $this->putJson($path, [...$body, 'version' => 1, 'revoke' => true])->assertConflict();
        $this->assertDatabaseHas('donor_assignments', ['donor_id' => $donor->id, 'user_id' => $worker->id, 'active' => true, 'version' => 3]);
    }

    public function test_completed_counts_retry_void_and_stale_correction_reconcile(): void
    {
        $owner = $this->staff();
        $donor = $this->donor($owner);
        $this->asStaff($owner);
        $path = '/api/v1/donors/'.$donor->id.'/donations';
        $body = ['reference' => 'QA-EVENT-001', 'donated_on' => now('Asia/Manila')->toDateString(), 'location' => 'Fictional collection'];
        $headers = ['Idempotency-Key' => (string) Str::uuid()];
        $record = $this->postJson($path, $body, $headers)->assertCreated()->assertJsonMissingPath('data.creation_hash')->json('data');
        $this->postJson($path, $body, $headers)->assertOk()->assertJsonPath('data.id', $record['id']);
        $this->postJson($path, [...$body, 'location' => 'Changed payload'], $headers)->assertConflict();
        $this->postJson($path, $body, ['Idempotency-Key' => (string) Str::uuid()])->assertConflict();
        $this->getJson($path)->assertOk()->assertJsonPath('completed_count', 1);
        $this->getJson('/api/v1/donors/'.$donor->id)->assertOk()->assertJsonPath('data.donation_count', 1);
        $correction = ['donated_on' => $body['donated_on'], 'location' => $body['location'], 'status' => 'voided', 'version' => 1, 'reason' => 'Duplicate documentary entry'];
        $this->patchJson($path.'/'.$record['id'], $correction)->assertOk()->assertJsonPath('data.version', 2);
        $this->patchJson($path.'/'.$record['id'], $correction)->assertConflict();
        $this->getJson($path)->assertOk()->assertJsonPath('completed_count', 0)->assertJsonCount(1, 'data');
        $this->patchJson($path.'/'.$record['id'], [...$correction, 'status' => 'completed', 'version' => 2])->assertConflict();
        $this->getJson('/api/v1/audit-logs?q=donation.corrected')->assertOk()->assertJsonCount(1, 'data')->assertJsonMissingPath('data.0.location');
        $this->assertDatabaseCount('donations', 1);
    }

    public function test_audit_failure_rolls_back_assignment_and_donation_changes(): void
    {
        $admin = $this->staff();
        $worker = $this->staff('Red Cross Personnel');
        $donor = $this->donor($admin);
        $this->asStaff($admin);
        AuditLog::creating(function (): void {
            throw new \RuntimeException('Fictional audit failure');
        });
        try {
            $this->putJson('/api/v1/assignments/'.$donor->id, ['user_id' => $worker->id, 'version' => 0, 'clinical_permissions' => [], 'reason' => 'Fictional assignment'])->assertStatus(500);
            $this->assertDatabaseMissing('donor_assignments', ['user_id' => $worker->id]);
            $this->postJson('/api/v1/donors/'.$donor->id.'/donations', ['reference' => 'QA-ROLLBACK', 'donated_on' => '2026-01-01', 'location' => 'Fictional collection'], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(500);
            $this->assertDatabaseCount('donations', 0);
        } finally {
            AuditLog::flushEventListeners();
        }
    }

    public function test_invalid_fields_and_out_of_scope_history_are_rejected(): void
    {
        $admin = $this->staff();
        $other = $this->staff();
        $donor = $this->donor($admin);
        $this->asStaff($other)->postJson('/api/v1/donors/'.$donor->id.'/donations', [], ['Idempotency-Key' => (string) Str::uuid()])->assertNotFound();
        $this->asStaff($admin)->postJson('/api/v1/donors/'.$donor->id.'/donations', ['reference' => 'QA-FUTURE', 'donated_on' => now()->addYear()->toDateString(), 'location' => 'Fictional'], ['Idempotency-Key' => (string) Str::uuid()])->assertUnprocessable();
        $this->putJson('/api/v1/assignments/'.$donor->id, ['user_id' => $other->id, 'version' => 0, 'clinical_permissions' => ['laboratory.everything'], 'reason' => 'Fictional grant'])->assertUnprocessable();
        $this->assertDatabaseCount('donations', 0);

    }
}
