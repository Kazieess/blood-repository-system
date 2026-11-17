<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BloodUnit;
use App\Models\Donor;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TraceabilityTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $user = User::factory()->create(['active' => true, 'role_id' => Role::firstOrCreate(['name' => 'Red Cross Administrator'])->id]);
        $this->actingAs($user, 'web');
        $donor = Donor::factory()->create(['created_by' => $user->id, 'updated_by' => $user->id]);
        $source = $this->postJson('/api/v1/donors/'.$donor->id.'/donations', ['reference' => 'QA-SOURCE', 'donated_on' => now('Asia/Manila')->subDay()->toDateString(), 'location' => 'Fictional source'], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated()->json('data');
        $unit = $this->postJson('/api/v1/inventory', ['reference' => 'QA-BAG', 'blood_type' => 'O+', 'component' => 'Fictional component', 'location' => 'Fictional store', 'collected_at' => now()->subDay()->toISOString(), 'expires_at' => now()->addDay()->toISOString(), 'reason' => 'Fictional record'], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated()->json('data');

        return [$user, $donor, $source, $unit];
    }

    public function test_link_is_scoped_versioned_and_required_for_verification(): void
    {
        [$user, $donor, $source, $unit] = $this->fixture();
        $transition = '/api/v1/inventory/'.$unit['id'].'/transitions';
        $this->postJson($transition, ['version' => 1, 'action' => 'verify', 'verification_confirmed' => true, 'reason' => 'Fictional verification'])->assertConflict();
        $body = ['version' => '1', 'donation_reference' => $source['reference'], 'reason' => 'Source checked'];
        $other = User::factory()->create(['active' => true, 'role_id' => $user->role_id]);
        $this->app['auth']->forgetGuards();
        $this->actingAs($other, 'web');
        $this->postJson('/api/v1/inventory/'.$unit['id'].'/source', $body)->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->actingAs($user, 'web');
        $this->postJson('/api/v1/inventory/'.$unit['id'].'/source', $body)->assertOk();
        $this->postJson('/api/v1/inventory/'.$unit['id'].'/source', $body)->assertConflict();
        $this->postJson($transition, ['version' => 2, 'action' => 'verify', 'verification_confirmed' => true, 'reason' => 'Fictional verification'])->assertOk();
        $this->getJson('/api/v1/donors/'.$donor->id.'/units')->assertOk()->assertJsonPath('data.0.donation_reference', 'QA-SOURCE');
        $this->getJson('/api/v1/inventory/'.$unit['id'])->assertOk()->assertJsonPath('data.source_linked', true)->assertJsonMissingPath('data.donation_id')->assertJsonMissingPath('data.donor_id');
        $this->patchJson('/api/v1/donors/'.$donor->id.'/donations/'.$source['id'], ['version' => 1, 'donated_on' => $source['donated_on'], 'location' => $source['location'], 'status' => 'voided', 'reason' => 'Attempt void'])->assertConflict();
        $this->assertSame(1, BloodUnit::available()->count());
    }

    public function test_date_mismatch_and_audit_failure_do_not_link_stock(): void
    {
        [, , $source, $unit] = $this->fixture();
        $body = ['version' => 1, 'donation_reference' => $source['reference'], 'reason' => 'Source checked'];
        BloodUnit::whereKey($unit['id'])->update(['collected_at' => now()->subDays(2)]);
        $this->postJson('/api/v1/inventory/'.$unit['id'].'/source', $body)->assertUnprocessable();
        BloodUnit::whereKey($unit['id'])->update(['collected_at' => now()->subDay()]);
        AuditLog::creating(function () {
            throw new \RuntimeException('Fictional audit outage');
        });
        try {
            $this->postJson('/api/v1/inventory/'.$unit['id'].'/source', $body)->assertStatus(500);
        } finally {
            AuditLog::flushEventListeners();
        }
        $this->assertNull(BloodUnit::findOrFail($unit['id'])->donation_id);
    }

    public function test_clinical_association_cannot_cross_donors_and_is_retained_in_revisions(): void
    {
        [$user, $donor, $source] = $this->fixture();
        $donor->assignments()->where('user_id', $user->id)->update(['clinical_permissions' => ['screening.read', 'screening.write']]);
        $body = ['source_reference' => 'QA-SCREEN', 'observed_at' => now()->toISOString(), 'payload' => ['decision' => 'recorded', 'notes' => 'Fictional only'], 'donation_id' => $source['id']];
        $other = Donor::factory()->create(['created_by' => $user->id, 'updated_by' => $user->id]);
        $other->assignments()->where('user_id', $user->id)->update(['clinical_permissions' => ['screening.write']]);
        $this->postJson('/api/v1/donors/'.$other->id.'/clinical/screening', $body, ['Idempotency-Key' => (string) Str::uuid()])->assertNotFound();
        $entry = $this->postJson('/api/v1/donors/'.$donor->id.'/clinical/screening', $body, ['Idempotency-Key' => (string) Str::uuid()])->assertCreated()->json('data');
        $this->getJson('/api/v1/donors/'.$donor->id.'/clinical/screening/'.$entry['id'].'/history')->assertOk()->assertJsonPath('data.0.snapshot.donation_id', $source['id']);
    }
}
