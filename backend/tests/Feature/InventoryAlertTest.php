<?php

namespace Tests\Feature;

use App\Actions\StockEvaluator;
use App\Models\AlertReceipt;
use App\Models\AuditLog;
use App\Models\BloodUnit;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\Role;
use App\Models\StockAlert;
use App\Models\StockRule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class InventoryAlertTest extends TestCase
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

    private function unit(string $reference, int $hours = 2): array
    {
        $unit = $this->postJson('/api/v1/inventory', ['reference' => $reference, 'blood_type' => 'O+', 'component' => 'Fictional component', 'location' => 'Fictional store', 'collected_at' => now()->subDay()->toISOString(), 'expires_at' => now()->addHours($hours)->toISOString(), 'reason' => 'Documented fictional unit'], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated()->json('data');
        $actor = auth()->user();
        $donor = Donor::factory()->create(['created_by' => $actor->id, 'updated_by' => $actor->id]);
        $source = Donation::create(['donor_id' => $donor->id, 'reference' => 'DON-'.$reference, 'donated_on' => now('Asia/Manila')->subDay()->toDateString(), 'location' => 'Fictional source', 'status' => 'completed', 'created_by' => $actor->id, 'creation_key' => (string) Str::uuid(), 'creation_hash' => str_repeat('a', 64), 'version' => 1]);
        BloodUnit::whereKey($unit['id'])->update(['donation_id' => $source->id]);

        return $unit;
    }

    private function transition(array $unit, string $action, int $version): TestResponse
    {
        return $this->postJson('/api/v1/inventory/'.$unit['id'].'/transitions', ['action' => $action, 'version' => $version, 'verification_confirmed' => true, 'reason' => 'Authorized fictional action']);
    }

    public function test_units_require_verification_exclude_spoiled_and_reject_repeat_or_stale_release(): void
    {
        $this->signIn($this->staff());
        $unit = $this->unit('QA-STATE');
        $this->getJson('/api/v1/inventory?status=available')->assertOk()->assertJsonPath('meta.total', 0);
        $this->transition($unit, 'release', 1)->assertConflict();
        $this->postJson('/api/v1/inventory/'.$unit['id'].'/transitions', ['action' => 'verify', 'version' => 1, 'verification_confirmed' => false, 'reason' => 'Fictional check'])->assertUnprocessable();
        $this->transition($unit, 'verify', 1)->assertOk()->assertJsonPath('data.status', 'available');
        $this->transition($unit, 'release', 1)->assertConflict();
        $this->transition($unit, 'spoil', 2)->assertOk()->assertJsonPath('data.status', 'spoiled');
        $this->getJson('/api/v1/inventory?status=available')->assertOk()->assertJsonPath('available_total', 0);
        $this->transition($unit, 'release', 3)->assertConflict();
        $this->transition($unit, 'discard', 3)->assertOk();
        $this->transition($unit, 'discard', 4)->assertConflict();
        $this->getJson('/api/v1/inventory/'.$unit['id'])->assertOk()->assertJsonCount(4, 'data.history')->assertJsonMissingPath('data.creation_key');
    }

    public function test_expiry_order_and_exact_boundary_use_only_approved_available_units(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-09T00:00:00Z'));
        $this->signIn($this->staff());
        $later = $this->unit('QA-LATER', 3);
        $earlier = $this->unit('QA-EARLIER', 1);
        $pending = $this->unit('QA-PENDING', 2);
        $this->transition($later, 'verify', 1)->assertOk();
        $this->transition($earlier, 'verify', 1)->assertOk();
        $this->getJson('/api/v1/inventory?status=available')->assertOk()->assertJsonPath('data.0.reference', 'QA-EARLIER')->assertJsonPath('meta.total', 2);
        $this->travelTo(CarbonImmutable::parse('2026-11-09T01:00:00Z'));
        $this->transition($earlier, 'release', 2)->assertConflict();
        $this->getJson('/api/v1/inventory?status=available')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.reference', 'QA-LATER');
        $this->getJson('/api/v1/inventory?status=expired')->assertOk()->assertJsonPath('data.0.reference', 'QA-EARLIER');
    }

    public function test_scoped_alerts_deduplicate_acknowledge_resolve_and_reopen(): void
    {
        $admin = $this->staff();
        $worker = $this->staff('Red Cross Personnel');
        $other = $this->staff('Red Cross Personnel');
        $this->signIn($admin);
        $rule = ['version' => 0, 'blood_type' => 'O+', 'component' => 'Fictional component', 'location' => 'Fictional store', 'threshold' => 1, 'recipient_ids' => [$worker->id], 'active' => true, 'reason' => 'Approved fictional threshold'];
        $this->postJson('/api/v1/stock-rules', $rule)->assertOk();
        app(StockEvaluator::class)->evaluate();
        app(StockEvaluator::class)->evaluate();
        $this->assertDatabaseCount('stock_alerts', 1);
        $this->signIn($other);
        $this->getJson('/api/v1/stock-alerts')->assertOk()->assertJsonPath('meta.total', 0);
        $this->postJson('/api/v1/stock-alerts/1/acknowledge')->assertNotFound();
        $this->signIn($worker);
        $first = $this->getJson('/api/v1/stock-alerts')->assertOk()->assertJsonPath('data.0.condition', 'out_of_stock')->json('data.0.id');
        $this->postJson('/api/v1/stock-alerts/'.$first.'/acknowledge')->assertOk();
        $this->getJson('/api/v1/stock-alerts')->assertOk()->assertJsonPath('unacknowledged_open_count', 0);
        $a = $this->unit('QA-ALERT-A');
        $this->transition($a, 'verify', 1)->assertOk();
        $this->getJson('/api/v1/stock-alerts')->assertOk()->assertJsonPath('data.0.condition', 'low_stock')->assertJsonPath('data.0.acknowledged_at', null);
        $b = $this->unit('QA-ALERT-B');
        $this->transition($b, 'verify', 1)->assertOk();
        $this->assertDatabaseHas('stock_alerts', ['id' => $first, 'open' => false]);
        $this->transition($a, 'release', 2)->assertOk();
        $this->assertDatabaseCount('stock_alerts', 2);
        $this->transition($b, 'spoil', 2)->assertOk();
        $this->getJson('/api/v1/stock-alerts')->assertOk()->assertJsonPath('data.0.condition', 'out_of_stock');
        $this->postJson('/api/v1/stock-rules', $rule)->assertForbidden();
    }

    public function test_scheduled_expiry_changes_alerts_without_a_user_write(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-09T00:00:00Z'));
        $admin = $this->staff();
        $this->signIn($admin);
        $unit = $this->unit('QA-CLOCK', 1);
        $this->transition($unit, 'verify', 1)->assertOk();
        $this->postJson('/api/v1/stock-rules', ['version' => 0, 'blood_type' => 'O+', 'component' => 'Fictional component', 'location' => 'Fictional store', 'threshold' => 0, 'recipient_ids' => [$admin->id], 'active' => true, 'reason' => 'Fictional zero threshold'])->assertOk();
        $this->assertDatabaseCount('stock_alerts', 0);
        $this->travelTo(CarbonImmutable::parse('2026-11-09T01:00:00Z'));
        $this->artisan('stock:evaluate')->assertSuccessful();
        $this->getJson('/api/v1/stock-alerts')->assertOk()->assertJsonPath('data.0.condition', 'out_of_stock');
    }

    public function test_rule_update_database_errors_are_not_mislabeled_as_duplicate_scope(): void
    {
        $admin = $this->staff();
        $this->signIn($admin);
        $body = ['version' => 0, 'blood_type' => 'O+', 'component' => 'Fictional component', 'location' => 'Fictional store', 'threshold' => 1, 'recipient_ids' => [$admin->id], 'active' => true, 'reason' => 'Fictional rule'];
        $record = $this->postJson('/api/v1/stock-rules', $body)->assertOk()->json('data');
        $this->postJson('/api/v1/stock-rules', $body)->assertConflict();
        AuditLog::creating(function (): void {
            DB::statement('SELECT * FROM intentionally_missing_test_table');
        });
        try {
            $this->postJson('/api/v1/stock-rules', [...$body, 'id' => $record['id'], 'version' => 1, 'threshold' => 99])->assertStatus(500);
            $this->assertDatabaseHas('stock_rules', ['id' => $record['id'], 'threshold' => 1, 'version' => 1]);
        } finally {
            AuditLog::flushEventListeners();
        }
    }

    public function test_retry_atomic_failure_and_permission_boundaries(): void
    {
        $this->signIn($this->staff('System Administrator'));
        $this->getJson('/api/v1/inventory')->assertForbidden();
        $this->signIn($this->staff());
        $body = ['reference' => 'QA-RETRY', 'blood_type' => 'B+', 'component' => 'Fictional component', 'location' => 'Fictional store', 'collected_at' => now()->subDay()->toISOString(), 'expires_at' => now()->addDay()->toISOString(), 'reason' => 'Fictional unit'];
        $headers = ['Idempotency-Key' => (string) Str::uuid()];
        $id = $this->postJson('/api/v1/inventory', $body, $headers)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/inventory', $body, $headers)->assertOk()->assertJsonPath('data.id', $id);
        $this->postJson('/api/v1/inventory', [...$body, 'reference' => 'QA-CHANGED'], $headers)->assertConflict();
        AuditLog::creating(function (): void {
            throw new \RuntimeException('Fictional audit failure');
        });
        try {
            $this->transition(['id' => $id], 'quarantine', 1)->assertStatus(500);
            $this->assertDatabaseHas('blood_units', ['id' => $id, 'status' => 'pending', 'version' => 1]);
            $this->assertDatabaseCount('inventory_events', 1);
        } finally {
            AuditLog::flushEventListeners();
        }

    }

    public function test_alert_polling_batches_receipts_without_exposing_other_recipients(): void
    {
        $worker = $this->staff('Red Cross Personnel');
        $other = $this->staff('Red Cross Personnel');
        $alerts = [];
        for ($index = 0; $index < 10; $index++) {
            $rule = StockRule::create(['blood_type' => 'O+', 'component' => 'QA component', 'location' => 'QA location '.$index, 'threshold' => 1, 'recipient_ids' => [$worker->id, $other->id], 'active' => true, 'version' => 1]);
            $alerts[] = StockAlert::create(['stock_rule_id' => $rule->id, 'open_rule_id' => $rule->id, 'condition' => 'out_of_stock', 'available_count' => 0, 'open' => true]);
        }
        $privateRule = StockRule::create(['blood_type' => 'O+', 'component' => 'QA component', 'location' => 'QA private location', 'threshold' => 1, 'recipient_ids' => [$other->id], 'active' => true, 'version' => 1]);
        StockAlert::create(['stock_rule_id' => $privateRule->id, 'open_rule_id' => $privateRule->id, 'condition' => 'out_of_stock', 'available_count' => 0, 'open' => true]);
        AlertReceipt::create(['stock_alert_id' => $alerts[0]->id, 'user_id' => $worker->id, 'acknowledged_at' => now()]);
        AlertReceipt::create(['stock_alert_id' => $alerts[1]->id, 'user_id' => $other->id, 'acknowledged_at' => now()]);
        $this->signIn($worker);
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $response = $this->getJson('/api/v1/stock-alerts')->assertOk()->assertJsonPath('meta.total', 10)->assertJsonPath('configured_scopes', 10)->assertJsonPath('unacknowledged_open_count', 9);
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
        $rows = collect($response->json('data'))->keyBy('id');
        $this->assertNotNull($rows[$alerts[0]->id]['acknowledged_at']);
        $this->assertNull($rows[$alerts[1]->id]['acknowledged_at']);
        $this->assertStringNotContainsString('QA private location', $response->getContent());
        $receiptReads = array_filter($queries, fn ($query) => str_starts_with(strtolower($query['query']), 'select') && str_contains($query['query'], 'alert_receipts'));
        $this->assertLessThanOrEqual(2, count($receiptReads), 'Polling must not read acknowledgement rows separately for every displayed alert.');
    }

    public function test_rule_recipient_validation_batches_users_and_rejects_ineligible_accounts(): void
    {
        $admin = $this->staff();
        $role = Role::firstOrCreate(['name' => 'Red Cross Personnel']);
        $workers = User::factory()->count(10)->create(['role_id' => $role->id, 'active' => true]);
        $this->signIn($admin);
        $body = ['version' => 0, 'blood_type' => 'O+', 'component' => 'QA component', 'location' => 'QA store', 'threshold' => 1, 'recipient_ids' => $workers->pluck('id')->all(), 'active' => true, 'reason' => 'QA recipient validation'];
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $this->postJson('/api/v1/stock-rules', $body)->assertOk();
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
        $userReads = array_filter($queries, fn ($query) => str_starts_with(strtolower($query['query']), 'select') && preg_match('/\bfrom\s+["`]?users["`]?/i', $query['query']));
        $this->assertLessThanOrEqual(3, count($userReads), 'Validating recipients must not issue a user query for every selected staff member.');
        $inactive = User::factory()->create(['role_id' => $role->id, 'active' => false]);
        $viewer = $this->staff('Report Viewer');
        foreach ([$inactive->id, $viewer->id, 999999999] as $id) {
            $this->postJson('/api/v1/stock-rules', [...$body, 'location' => 'QA denied '.$id, 'recipient_ids' => [$id]])->assertUnprocessable();
        }
        $this->assertDatabaseCount('stock_rules', 1);
    }
}
