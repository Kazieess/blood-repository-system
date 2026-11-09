<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\BackupRun;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;

class BackupStore
{
    public function directory(): string
    {
        $path = storage_path('app/private/repository-backups');
        File::ensureDirectoryExists($path, 0700);

        return $path;
    }

    public function artifact(BackupRun $run): string
    {
        if (! Str::isUuid($run->id)) {
            throw new RuntimeException('Invalid backup identity.');
        }

        return $this->directory().DIRECTORY_SEPARATOR.$run->id.'.enc';
    }

    private function audit(?int $actor, string $action, string $subject, string $requestId): void
    {
        AuditLog::create(['actor_id' => $actor, 'action' => $action, 'subject_id' => $subject, 'changed_fields' => ['backup_status'], 'request_id' => $requestId]);
    }

    private function inspect(string $path): array
    {
        $pdo = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('PRAGMA query_only=ON');
        $pdo->exec('PRAGMA trusted_schema=OFF');
        if ($pdo->query('PRAGMA integrity_check')->fetchColumn() !== 'ok' || $pdo->query('PRAGMA foreign_key_check')->fetchAll()) {
            throw new RuntimeException('Snapshot integrity verification failed.');
        }
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
        $counts = [];
        foreach ($tables as $table) {
            if (! preg_match('/^[a-z][a-z0-9_]*$/', $table)) {
                throw new RuntimeException('Unsupported table identifier.');
            }
            $counts[$table] = (int) $pdo->query('SELECT COUNT(*) FROM "'.$table.'"')->fetchColumn();
        }

        return $counts;
    }

    public function create(?int $actor, string $id, string $reason, string $requestId): BackupRun
    {
        abort_unless(DB::connection()->getDriverName() === 'sqlite', 422, 'This adapter supports SQLite only; MySQL recovery is not verified.');
        abort_unless(Str::isUuid($id), 422);
        $hash = hash('sha256', $reason);
        if ($existing = BackupRun::find($id)) {
            abort_unless($existing->actor_id === $actor && hash_equals($existing->request_hash, $hash) && $existing->status === 'completed', 409, 'Check the existing backup run before starting another.');

            return $existing;
        }
        abort_if(empty(config('app.key')), 422, 'An application key is required.');
        $run = BackupRun::create(['id' => $id, 'actor_id' => $actor, 'request_hash' => $hash, 'status' => 'running']);
        $temporary = $this->directory().DIRECTORY_SEPARATOR.$id.'.work.sqlite';
        try {
            $pdo = DB::connection()->getPdo();
            $pdo->exec('VACUUM INTO '.$pdo->quote($temporary));
            if (File::size($temporary) > config('repository.backup_max_bytes')) {
                throw new RuntimeException('Snapshot exceeds configured local backup capacity.');
            }
            $manifest = $this->inspect($temporary);
            $plaintext = File::get($temporary);
            $envelope = json_encode(['format' => 1, 'created_at' => now()->toISOString(), 'key_fingerprint' => hash('sha256', config('app.key')), 'manifest' => $manifest, 'database' => base64_encode($plaintext)], JSON_THROW_ON_ERROR);
            $encrypted = Crypt::encryptString($envelope);
            if (File::put($this->artifact($run), $encrypted, true) !== strlen($encrypted)) {
                throw new RuntimeException('Could not store encrypted backup.');
            }
            DB::transaction(function () use ($run, $encrypted, $actor, $requestId): void {
                $run->update(['status' => 'completed', 'bytes' => strlen($encrypted), 'checksum' => hash('sha256', $encrypted), 'completed_at' => now()]);
                $this->audit($actor, 'backup.completed', $run->id, $requestId);
            });
        } catch (\Throwable $exception) {
            $run->update(['status' => 'failed', 'error_code' => 'backup_failed']);
            if (File::exists($this->artifact($run)) && ! File::delete($this->artifact($run))) {
                throw new RuntimeException('Failed backup artifact cleanup requires attention.', 0, $exception);
            }
            throw new RuntimeException('The backup failed. Review its safe request reference.', 0, $exception);
        } finally {
            if (File::exists($temporary) && ! File::delete($temporary)) {
                throw new RuntimeException('Temporary snapshot cleanup requires attention.');
            }
        }

        return $run->fresh();
    }

    public function restoreCopy(BackupRun $run, ?int $actor, string $requestId): array
    {
        abort_unless($run->status === 'completed', 409);
        $artifact = $this->artifact($run);
        if (! File::exists($artifact) || File::size($artifact) > config('repository.backup_max_bytes') * 3) {
            throw new RuntimeException('Backup artifact is missing or exceeds the configured capacity.');
        }
        $encrypted = File::get($artifact);
        if (! hash_equals($run->checksum, hash('sha256', $encrypted))) {
            throw new RuntimeException('Backup checksum verification failed.');
        }
        $envelope = json_decode(Crypt::decryptString($encrypted), true, 64, JSON_THROW_ON_ERROR);
        if (($envelope['format'] ?? null) !== 1 || ! hash_equals($envelope['key_fingerprint'] ?? '', hash('sha256', config('app.key')))) {
            throw new RuntimeException('Backup key/format verification failed.');
        }
        $bytes = base64_decode($envelope['database'], true);
        if ($bytes === false || ! str_starts_with($bytes, "SQLite format 3\0")) {
            throw new RuntimeException('Snapshot format verification failed.');
        }
        $copyId = (string) Str::uuid();
        $directory = storage_path('app/private/repository-restores');
        File::ensureDirectoryExists($directory, 0700);
        $target = $directory.DIRECTORY_SEPARATOR.$copyId.'.sqlite';
        $handle = fopen($target, 'x+b');
        if ($handle === false) {
            throw new RuntimeException('Could not create a new isolated recovery copy.');
        }
        try {
            try {
                if (fwrite($handle, $bytes) !== strlen($bytes)) {
                    throw new RuntimeException('Isolated restore write failed.');
                }
            } finally {
                fclose($handle);
            }
            if ($this->inspect($target) !== $envelope['manifest']) {
                throw new RuntimeException('Recovered record counts do not match the snapshot.');
            }
            DB::transaction(function () use ($run, $actor, $requestId, $copyId): void {
                $run->update(['verified_at' => now(), 'restore_copy' => $copyId]);
                $this->audit($actor, 'backup.restore_verified', $run->id, $requestId);
            });
        } catch (\Throwable $exception) {
            if (File::exists($target) && ! File::delete($target)) {
                throw new RuntimeException('Unverified recovery copy cleanup requires attention.', 0, $exception);
            }
            throw $exception;
        }

        return ['copy_id' => $copyId, 'verified_at' => $run->verified_at, 'integrity' => 'ok', 'counts_match' => true];
    }
}
