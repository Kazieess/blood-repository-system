<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\AvailabilitySnapshot;
use App\Models\BloodUnit;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportAvailabilityTest extends TestCase
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

    private function stock(User $user, string $status = 'available', string $location = 'Fictional store'): BloodUnit
    {
        $donor = Donor::factory()->create(['created_by' => $user->id, 'updated_by' => $user->id]);
        $source = Donation::create(['donor_id' => $donor->id, 'reference' => 'QA-'.Str::uuid(), 'donated_on' => now('Asia/Manila')->subDay()->toDateString(), 'location' => $location, 'status' => 'completed', 'created_by' => $user->id, 'creation_key' => (string) Str::uuid(), 'creation_hash' => str_repeat('b', 64), 'version' => 1]);

        return BloodUnit::create(['reference' => 'QA-'.Str::uuid(), 'donation_id' => $source->id, 'blood_type' => 'O+', 'component' => '=Fictional component', 'location' => $location, 'collected_at' => now()->subDay(), 'expires_at' => now()->addHour(), 'status' => $status, 'verified_at' => $status === 'available' ? now() : null, 'created_by' => $user->id, 'creation_key' => (string) Str::uuid(), 'creation_hash' => str_repeat('c', 64), 'version' => 1]);
    }

    public function test_reports_reconcile_filter_and_export_without_private_fields(): void
    {
        $admin = $this->staff();
        $this->signIn($admin);
        $this->stock($admin);
        $this->stock($admin);
        $this->stock($admin, 'pending');
        $this->stock($admin, 'spoiled', 'Other location');
        $this->getJson('/api/v1/reports/inventory?location=Fictional%20store')->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.units', 2)->assertJsonMissingPath('data.0.reference');
        $csv = $this->postJson('/api/v1/reports/inventory/export?location=Fictional%20store')->assertOk()->json('csv');
        $this->assertStringContainsString("'=Fictional component", $csv);
        $this->assertDatabaseHas('audit_logs', ['action' => 'report.inventory.exported']);
        $this->getJson('/api/v1/reports/inventory?from=2099-01-01')->assertOk()->assertJsonPath('meta.total', 0);
        $viewer = $this->staff('Report Viewer');
        $this->signIn($viewer);
        $this->getJson('/api/v1/reports/expiry')->assertOk();
        $this->getJson('/api/v1/reports/donations')->assertForbidden();
        $this->getJson('/api/v1/inventory')->assertForbidden();
        $worker = $this->staff('Red Cross Personnel');
        $this->signIn($worker);
        $this->getJson('/api/v1/reports/donations')->assertOk()->assertJsonPath('meta.total', 0);
        $this->signIn($admin);
        $this->getJson('/api/v1/reports/donations')->assertOk()->assertJsonPath('data.0.units', 4);
    }

    public function test_public_snapshot_requires_explicit_publication_and_expires_or_withdraws(): void
    {
        $admin = $this->staff();
        $this->signIn($admin);
        $unit = $this->stock($admin);
        $this->stock($admin, 'spoiled');
        $this->stock($admin, 'pending');
        $this->getJson('/api/v1/public/availability')->assertOk()->assertJsonPath('state', 'unavailable');
        $body = ['location' => 'Fictional store', 'public_label' => 'QA Public Center', 'valid_minutes' => 120, 'confirmed' => true, 'reason' => 'Publish fictional totals'];
        $key = ['Idempotency-Key' => (string) Str::uuid()];
        $id = $this->postJson('/api/v1/publications', $body, $key)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/publications', [...$body, 'valid_minutes' => '120', 'confirmed' => '1'], $key)->assertCreated()->assertJsonPath('data.id', $id);
        $this->postJson('/api/v1/publications', [...$body, 'public_label' => 'Changed'], $key)->assertConflict();
        $this->assertDatabaseCount('availability_snapshots', 1);
        $response = $this->getJson('/api/v1/public/availability')->assertOk()->assertJsonPath('state', 'available')->assertJsonPath('data.0.units', 1)->assertJsonPath('data.0.location', 'QA Public Center');
        $this->assertEqualsCanonicalizing(['blood_type', 'component', 'location', 'units'], array_keys($response->json('data.0')));
        $this->assertStringNotContainsString($unit->reference, $response->getContent());
        $this->travel(61)->minutes();
        $this->getJson('/api/v1/public/availability')->assertOk()->assertJsonPath('state', 'stale')->assertJsonCount(0, 'data');
        $this->postJson('/api/v1/publications/'.$id.'/withdraw', ['reason' => 'Withdraw fictional snapshot'])->assertOk();
        $this->getJson('/api/v1/public/availability')->assertOk()->assertJsonPath('state', 'unavailable');
    }

    public function test_stock_change_invalidates_publication_and_denied_staff_cannot_publish(): void
    {
        $admin = $this->staff();
        $this->signIn($admin);
        $unit = $this->stock($admin);
        $body = ['location' => 'Fictional store', 'public_label' => 'QA Center', 'valid_minutes' => 30, 'confirmed' => true, 'reason' => 'Fictional publication'];
        $this->postJson('/api/v1/publications', $body, ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
        $this->postJson('/api/v1/inventory/'.$unit->id.'/transitions', ['version' => 1, 'action' => 'release', 'reason' => 'Fictional release'])->assertOk();
        $this->getJson('/api/v1/public/availability')->assertOk()->assertJsonPath('state', 'stale');
        $this->signIn($this->staff('Report Viewer'));
        $this->postJson('/api/v1/publications', $body, ['Idempotency-Key' => (string) Str::uuid()])->assertForbidden();
        $this->getJson('/api/v1/publications')->assertForbidden();
    }

    public function test_publication_and_export_fail_closed_when_audit_fails(): void
    {
        $admin = $this->staff();
        $this->signIn($admin);
        $this->stock($admin);
        AuditLog::creating(function () {
            throw new \RuntimeException('Fictional audit outage');
        });
        try {
            $this->postJson('/api/v1/publications', ['location' => 'Fictional store', 'public_label' => 'QA Center', 'valid_minutes' => 30, 'confirmed' => true, 'reason' => 'Fictional publication'], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(500);
            $this->postJson('/api/v1/reports/inventory/export')->assertStatus(500);
        } finally {
            AuditLog::flushEventListeners();
        }
        $this->assertDatabaseCount('availability_snapshots', 0);
    }

    public function test_reports_do_not_count_unusable_sources_as_available(): void
    {
        $admin = $this->staff();
        $this->signIn($admin);
        $this->stock($admin);
        $this->stock($admin)->update(['donation_id' => null]);
        $this->stock($admin)->update(['verified_at' => null]);
        $this->stock($admin)->donation->update(['status' => 'voided']);
        foreach (['inventory', 'expiry'] as $kind) {
            $rows = $this->getJson('/api/v1/reports/'.$kind)->assertOk()->json('data');
            $this->assertSame(['available' => 1, 'unverified' => 3], collect($rows)->pluck('units', 'stock_state')->all());
        }
        $this->assertSame(1, BloodUnit::available()->count());
    }

    public function test_publication_recovers_from_real_duplicate_key_after_a_stale_lookup(): void
    {
        $admin = $this->staff();
        $this->signIn($admin);
        $this->stock($admin);
        $body = ['location' => 'Fictional store', 'public_label' => 'QA Center', 'valid_minutes' => 30, 'confirmed' => true, 'reason' => 'Fictional publication'];
        $headers = ['Idempotency-Key' => (string) Str::uuid()];
        $id = $this->postJson('/api/v1/publications', $body, $headers)->assertCreated()->json('data.id');
        $originalScopes = AvailabilitySnapshot::getAllGlobalScopes();
        foreach ([false, true] as $changed) {
            $firstRead = true;
            // Force the initial lookup to miss a committed winner; the insert still uses the real unique constraint.
            AvailabilitySnapshot::addGlobalScope('stale-read', function (Builder $query) use (&$firstRead): void {
                if ($firstRead) {
                    $firstRead = false;
                    $query->whereRaw('1 = 0');
                }
            });
            try {
                $response = $this->postJson('/api/v1/publications', [...$body, 'public_label' => $changed ? 'Changed label' : 'QA Center'], $headers);
                if ($changed) {
                    $response->assertConflict();
                } else {
                    $response->assertCreated()->assertJsonPath('data.id', $id);
                }
            } finally {
                AvailabilitySnapshot::setAllGlobalScopes($originalScopes);
            }
        }
        $this->assertDatabaseCount('availability_snapshots', 1);
        $this->assertSame(1, AuditLog::where('action', 'availability.published')->count());
    }
}
