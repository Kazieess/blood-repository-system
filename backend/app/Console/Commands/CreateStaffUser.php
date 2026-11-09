<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class CreateStaffUser extends Command
{
    protected $signature = 'app:create-user';

    protected $description = 'Provision a staff account locally without putting a password in shell history';

    public function handle(): int
    {
        $name = $this->ask('Staff display name (use fictional details during development)');
        $email = $this->ask('Email address');
        $role = $this->choice('Role', Role::NAMES);
        $password = $this->secret('Password (12+ characters, upper/lowercase, number, symbol)');
        $confirmation = $this->secret('Confirm password');
        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $confirmation],
            ['name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'unique:users,email'], 'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()]],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        DB::transaction(function () use ($role, $name, $email, $password): void {
            $roleRecord = Role::firstOrCreate(['name' => $role]);
            $user = User::create(['name' => $name, 'email' => $email, 'password' => $password, 'role_id' => $roleRecord->id, 'active' => true]);
            AuditLog::create([
                'action' => 'staff.provisioned', 'subject_id' => (string) $user->id,
                'reason' => 'Account provisioned through the local staff command.',
                'changed_fields' => ['name', 'email', 'role_id', 'active'], 'request_id' => (string) Str::uuid(),
            ]);
        });
        $this->info('Staff account created. No password has been printed or stored in source.');

        return self::SUCCESS;
    }
}
