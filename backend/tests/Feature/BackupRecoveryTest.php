<?php

namespace Tests\Feature;

use App\Actions\BackupStore;
use App\Models\BackupRun;
use App\Models\Donor;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class BackupRecoveryTest extends TestCase
{
    use DatabaseMigrations;

    private array $ownedArtifacts = [];

    protected function tearDown(): void
    {
        foreach ($this->ownedArtifacts as $path) {
            if (File::exists($path)) {
                File::delete($path);
            }
        }
        parent::tearDown();
    }

    private function staff(string $role): User
    {
        return User::factory()->create(['role_id' => Role::firstOrCreate(['name' => $role])->id, 'active' => true]);
    }

    private function signIn(User $user): void
    {
        $this->app['auth']->forgetGuards();
        $this->actingAs($user, 'web');
    }

    public function test_actual_encrypted_snapshot_restores_to_an_isolated_copy_with_matching_records(): void
    {
        $operator = $this->staff('Red Cross Administrator');
        $donor = Donor::factory()->create(['created_by' => $operator->id, 'updated_by' => $operator->id, 'name' => 'Fictional recovery donor']);
        $admin = $this->staff('System Administrator');
        $this->signIn($admin);
        $key = (string) Str::uuid();
        $headers = ['Idempotency-Key' => $key];
        $body = ['reason' => 'Fictional recovery rehearsal'];
        $this->postJson('/api/v1/backups', $body, $headers)->assertOk()->assertJsonPath('data.status', 'completed');
        $run = BackupRun::findOrFail($key);
        $artifact = app(BackupStore::class)->artifact($run);
        $this->ownedArtifacts[] = $artifact;
        $this->assertStringNotContainsString('Fictional recovery donor', File::get($artifact));
        $this->postJson('/api/v1/backups', $body, $headers)->assertOk()->assertJsonPath('data.id', $key);
        $this->assertDatabaseCount('backup_runs', 1);
        $this->postJson('/api/v1/backups/'.$key.'/restore-copy', [])->assertUnprocessable();
        $copy = $this->postJson('/api/v1/backups/'.$key.'/restore-copy', ['isolated_copy_confirmed' => true])->assertOk()->assertJsonPath('data.integrity', 'ok')->assertJsonPath('data.counts_match', true)->json('data.copy_id');
        $target = storage_path('app/private/repository-restores/'.$copy.'.sqlite');
        $this->ownedArtifacts[] = $target;
        $pdo = new PDO('sqlite:'.$target);
        $this->assertSame('Fictional recovery donor', $pdo->query('SELECT name FROM donors')->fetchColumn());
        $pdo = null;
        $this->assertDatabaseHas('donors', ['id' => $donor->id]);
        $this->getJson('/api/v1/backups')->assertOk()->assertJsonMissingPath('data.0.checksum')->assertJsonMissingPath('data.0.payload');
        $this->get('/storage/repository-backups/'.$key.'.enc')->assertForbidden();
    }

    public function test_tampered_artifacts_are_rejected_without_a_verified_restore(): void
    {
        $admin = $this->staff('System Administrator');
        $this->signIn($admin);
        $key = (string) Str::uuid();
        $this->postJson('/api/v1/backups', ['reason' => 'Fictional tamper check'], ['Idempotency-Key' => $key])->assertOk();
        $run = BackupRun::findOrFail($key);
        $artifact = app(BackupStore::class)->artifact($run);
        $this->ownedArtifacts[] = $artifact;
        File::put($artifact, 'Fictional corrupt artifact');
        $this->postJson('/api/v1/backups/'.$key.'/restore-copy', ['isolated_copy_confirmed' => true])->assertStatus(500)->assertJsonMissingPath('trace');
        $this->assertDatabaseHas('backup_runs', ['id' => $key, 'verified_at' => null, 'restore_copy' => null]);
    }

    public function test_permissions_and_unsupported_adapter_are_explicit(): void
    {
        $this->signIn($this->staff('Red Cross Administrator'));
        $this->getJson('/api/v1/backups')->assertForbidden();
        $this->postJson('/api/v1/backups', ['reason' => 'Fictional scope test'], ['Idempotency-Key' => (string) Str::uuid()])->assertForbidden();
        $this->signIn($this->staff('System Administrator'));
        $this->getJson('/api/v1/donors')->assertForbidden();
        $this->postJson('/api/v1/backups', ['reason' => 'Missing retry reference'])->assertUnprocessable();
        $this->getJson('/api/v1/backups')->assertOk()->assertJsonPath('supported', true)->assertJsonPath('schedule_pht', null);
        $admin = auth('web')->user()->load('role');
        config(['database.default' => 'mysql']);
        try {
            $this->postJson('/api/v1/backups', ['reason' => 'Unsupported adapter test'], ['Idempotency-Key' => (string) Str::uuid()])->assertUnprocessable();
        } finally {
            config(['database.default' => 'sqlite']);
        }

    }
}
