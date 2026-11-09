<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Donor;
use App\Models\DonorAssignment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClinicalAccessTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role = 'Red Cross Administrator'): User
    {
        return User::factory()->create(['role_id' => Role::firstOrCreate(['name' => $role])->id, 'active' => true]);
    }

    private function signIn(User $user): void
    {
        $this->app['auth']->forgetGuards();
        $this->actingAs($user, 'web');
    }

    private function grant(Donor $donor, User $user, array $permissions): void
    {
        DonorAssignment::updateOrCreate(['donor_id' => $donor->id, 'user_id' => $user->id], ['assigned_by' => $donor->created_by, 'active' => true, 'clinical_permissions' => $permissions]);
    }

    private function body(): array
    {
        return ['source_reference' => 'QA-SOURCE', 'observed_at' => now()->subMinute()->toISOString(), 'payload' => ['decision' => 'recorded', 'notes' => 'Fictional restricted screening note']];
    }

    public function test_assignment_alone_never_exposes_clinical_information(): void
    {
        $admin = $this->staff();
        $donor = Donor::factory()->create(['created_by' => $admin->id, 'updated_by' => $admin->id]);
        $this->signIn($admin);
        $path = '/api/v1/donors/'.$donor->id.'/clinical/screening';
        $this->getJson($path)->assertForbidden();
        $this->postJson($path, $this->body(), ['Idempotency-Key' => (string) Str::uuid()])->assertForbidden();
        $this->grant($donor, $admin, ['screening.read', 'screening.write']);
        $record = $this->postJson($path, $this->body(), ['Idempotency-Key' => (string) Str::uuid()])->assertCreated()->json('data');
        $this->getJson($path)->assertOk()->assertJsonPath('data.0.payload.notes', 'Fictional restricted screening note');
        $this->getJson('/api/v1/donors/'.$donor->id)->assertOk()->assertJsonMissingPath('data.payload');
        $raw = DB::table('clinical_entries')->where('id', $record['id'])->value('payload');
        $this->assertStringNotContainsString('Fictional restricted screening note', $raw);
        $this->assertStringNotContainsString('Fictional restricted screening note', json_encode(DB::table('audit_logs')->get()));
        $this->grant($donor, $admin, []);
        $this->getJson($path)->assertForbidden();
        $this->getJson($path.'/'.$record['id'].'/history')->assertForbidden();
    }

    public function test_independent_review_correction_and_encrypted_history_preserve_provenance(): void
    {
        $author = $this->staff();
        $reviewer = $this->staff('Red Cross Personnel');
        $donor = Donor::factory()->create(['created_by' => $author->id, 'updated_by' => $author->id]);
        $permissions = ['screening.read', 'screening.write', 'screening.review'];
        $this->grant($donor, $author, $permissions);
        $this->grant($donor, $reviewer, $permissions);
        $this->signIn($author);
        $path = '/api/v1/donors/'.$donor->id.'/clinical/screening';
        $headers = ['Idempotency-Key' => (string) Str::uuid()];
        $body = $this->body();
        $record = $this->postJson($path, $body, $headers)->assertCreated()->json('data');
        $this->postJson($path, $body, $headers)->assertOk()->assertJsonPath('data.id', $record['id']);
        $this->postJson($path.'/'.$record['id'].'/review', ['version' => 1, 'reason' => 'Reviewed source document'])->assertForbidden();
        $this->signIn($reviewer);
        $this->postJson($path.'/'.$record['id'].'/review', ['version' => 1, 'reason' => 'Reviewed source document'])->assertOk()->assertJsonPath('data.version', 2);
        $this->signIn($author);
        $correction = [...$body, 'payload' => ['decision' => 'requires_review', 'notes' => 'Corrected fictional private note'], 'version' => 2, 'reason' => 'Fictional restricted correction'];
        $this->patchJson($path.'/'.$record['id'], $correction)->assertOk()->assertJsonPath('data.status', 'recorded')->assertJsonPath('data.reviewed_by', null);
        $this->patchJson($path.'/'.$record['id'], $correction)->assertConflict();
        $this->getJson($path.'/'.$record['id'].'/history')->assertOk()->assertJsonPath('meta.total', 3)->assertJsonPath('data.0.snapshot.reason', 'Fictional restricted correction');
        $this->assertStringNotContainsString('Fictional restricted correction', json_encode(DB::table('audit_logs')->get()));
        $this->assertStringNotContainsString('Fictional restricted correction', json_encode(DB::table('clinical_revisions')->get()));
        $this->signIn($this->staff('System Administrator'));
        $this->getJson($path)->assertForbidden();
    }

    public function test_empty_catalog_typed_fields_and_metadata_are_explicit_without_seeded_tests(): void
    {
        $admin = $this->staff();
        $donor = Donor::factory()->create(['created_by' => $admin->id, 'updated_by' => $admin->id]);
        $this->grant($donor, $admin, ['laboratory.read', 'laboratory.write']);
        $this->signIn($admin);
        $path = '/api/v1/donors/'.$donor->id.'/clinical/laboratory';
        $body = [...$this->body(), 'payload' => ['results' => ['qa_result' => 12.5], 'notes' => 'Fictional result only']];
        $this->postJson($path, $body, ['Idempotency-Key' => (string) Str::uuid()])->assertUnprocessable();
        $field = ['version' => 0, 'key' => 'qa_result', 'label' => 'Fictional QA measurement', 'format' => 'number', 'unit' => 'QA units', 'reference_text' => 'Fictional reference only', 'required' => true, 'active' => true, 'reason' => 'Fictional catalog definition'];
        $this->postJson('/api/v1/clinical-catalog', $field)->assertOk();
        $this->postJson($path, [...$body, 'payload' => ['results' => ['qa_result' => 'invalid']]], ['Idempotency-Key' => (string) Str::uuid()])->assertUnprocessable();
        $this->postJson($path, [...$body, 'payload' => ['results' => ['qa_result' => 1, 'unexpected' => 'secret']]], ['Idempotency-Key' => (string) Str::uuid()])->assertUnprocessable();
        $this->postJson($path, $body, ['Idempotency-Key' => (string) Str::uuid()])->assertCreated()->assertJsonPath('data.payload.definitions.0.label', 'Fictional QA measurement');
        $this->getJson($path)->assertOk()->assertJsonPath('data.0.payload.results.qa_result', 12.5);
        $this->getJson('/api/v1/donors/'.$donor->id.'/clinical/eligibility')->assertForbidden();
    }

    public function test_audit_failure_rolls_back_entry_and_revision(): void
    {
        $admin = $this->staff();
        $donor = Donor::factory()->create(['created_by' => $admin->id, 'updated_by' => $admin->id]);
        $this->grant($donor, $admin, ['screening.write']);
        $this->signIn($admin);
        AuditLog::creating(function (): void {
            throw new \RuntimeException('Fictional audit failure');
        });
        try {
            $this->postJson('/api/v1/donors/'.$donor->id.'/clinical/screening', $this->body(), ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(500);
            $this->assertDatabaseCount('clinical_entries', 0);
            $this->assertDatabaseCount('clinical_revisions', 0);
        } finally {
            AuditLog::flushEventListeners();
        }

    }
}
