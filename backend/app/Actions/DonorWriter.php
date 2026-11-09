<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Donor;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class DonorWriter
{
    /** @return array{0: Donor, 1: bool} */
    public function create(User $actor, array $input, string $key, string $requestId): array
    {
        $hash = hash('sha256', json_encode($input, JSON_THROW_ON_ERROR));
        $lookup = fn () => Donor::where('created_by', $actor->id)->where('creation_key', $key)->first();

        if ($existing = $lookup()) {
            abort_if(! hash_equals($existing->creation_hash, $hash), 409, 'The creation key was already used with different input.');

            return [$existing, false];
        }

        try {
            return DB::transaction(function () use ($actor, $input, $key, $hash, $requestId): array {
                $donor = Donor::create([
                    ...$input, 'created_by' => $actor->id, 'updated_by' => $actor->id,
                    'creation_key' => $key, 'creation_hash' => $hash,
                ]);
                AuditLog::create([
                    'actor_id' => $actor->id, 'action' => 'donor.created', 'subject_id' => $donor->id,
                    'changed_fields' => array_keys($input), 'request_id' => $requestId,
                ]);

                return [$donor, true];
            });
        } catch (QueryException $exception) {
            if ($existing = $lookup()) {
                abort_if(! hash_equals($existing->creation_hash, $hash), 409, 'The creation key was already used with different input.');

                return [$existing, false];
            }

            throw $exception;
        }
    }

    public function update(User $actor, Donor $donor, array $input, string $requestId): Donor
    {
        return DB::transaction(function () use ($actor, $donor, $input, $requestId): Donor {
            $fields = ['name' => $input['name'], 'blood_type' => $input['blood_type'], 'municipality' => $input['municipality']];
            $changed = array_keys(array_filter($fields, fn (string $value, string $key) => $donor->{$key} !== $value, ARRAY_FILTER_USE_BOTH));
            $updated = Donor::whereKey($donor->id)->where('version', $input['version'])->update([
                ...$fields, 'updated_by' => $actor->id, 'version' => DB::raw('version + 1'),
            ]);
            abort_if($updated !== 1, 409, 'This donor record was changed. Reload it before saving.');

            AuditLog::create([
                'actor_id' => $actor->id, 'action' => 'donor.corrected', 'subject_id' => $donor->id,
                'reason' => $input['reason'], 'changed_fields' => $changed, 'request_id' => $requestId,
            ]);

            return $donor->fresh();
        });
    }
}
