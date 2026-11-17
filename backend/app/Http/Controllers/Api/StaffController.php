<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DonorAssignment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StaffController extends Controller
{
    private function permit(Request $request): void
    {
        abort_unless($request->user()->hasPermission('users.manage'), 403);
    }

    private function present(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role->name, 'active' => $user->active, 'version' => $user->version];
    }

    public function index(Request $request): JsonResponse
    {
        $this->permit($request);
        $input = $request->validate(['q' => ['nullable', 'string', 'max:120'], 'page' => ['nullable', 'integer', 'min:1']]);
        $query = User::with('role');
        if (! empty($input['q'])) {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$input['q'].'%')->orWhere('email', 'like', '%'.$input['q'].'%'));
        }
        $rows = $query->orderBy('id')->paginate(20);

        return response()->json(['data' => array_map(fn ($user) => $this->present($user), $rows->items()), 'roles' => Role::NAMES, 'meta' => ['total' => $rows->total(), 'current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage()]]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->permit($request);
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        $input = $request->validate(['name' => ['required', 'string', 'min:2', 'max:120'], 'email' => ['required', 'email', 'max:255'], 'role' => ['required', Rule::in(Role::NAMES)], 'password' => ['required', 'confirmed', 'max:128', Password::min(12)->mixedCase()->numbers()->symbols()], 'current_password' => ['required', 'current_password:web'], 'reason' => ['required', 'string', 'min:3', 'max:500']]);
        $key = $request->header('Idempotency-Key');
        abort_unless(Str::isUuid($key), 422);
        $hash = hash_hmac('sha256', json_encode([$request->user()->id, $input['name'], $input['email'], $input['role'], $input['password'], $input['reason']], JSON_THROW_ON_ERROR), config('app.key'));
        $user = DB::transaction(function () use ($request, $input, $key, $hash): User {
            Role::where('name', 'System Administrator')->lockForUpdate()->firstOrFail();
            $this->reauthenticate($request, $input['current_password']);
            if ($existing = User::where('provisioning_key', $key)->first()) {
                abort_unless(hash_equals($existing->provisioning_hash, $hash), 409);

                return $existing;
            }
            abort_if(User::where('email', $input['email'])->exists(), 409);
            $role = Role::firstOrCreate(['name' => $input['role']]);
            $user = User::create(['name' => $input['name'], 'email' => $input['email'], 'password' => $input['password'], 'role_id' => $role->id, 'active' => true]);
            $user->forceFill(['provisioning_key' => $key, 'provisioning_hash' => $hash])->save();
            $this->audit($request, $user, 'staff.created', $input['reason']);

            return $user;
        });

        return response()->json(['data' => $this->present($user->fresh('role'))], 201);
    }

    public function update(Request $request, string $staff): JsonResponse
    {
        $this->permit($request);
        $input = $request->validate(['name' => ['required', 'string', 'min:2', 'max:120'], 'role' => ['required', Rule::in(Role::NAMES)], 'active' => ['required', 'boolean'], 'version' => ['required', 'integer', 'min:1'], 'current_password' => ['required', 'current_password:web'], 'reason' => ['required', 'string', 'min:3', 'max:500']]);
        $user = DB::transaction(function () use ($request, $staff, $input): User {
            $adminRole = Role::where('name', 'System Administrator')->lockForUpdate()->firstOrFail();
            $this->reauthenticate($request, $input['current_password']);
            $user = User::whereKey($staff)->lockForUpdate()->firstOrFail();
            abort_unless($user->version === (int) $input['version'], 409);
            $role = Role::firstOrCreate(['name' => $input['role']]);
            $accessChanged = $user->role_id !== $role->id || $user->active !== (bool) $input['active'];
            if ($accessChanged) {
                abort_if($user->id === $request->user()->id, 422, 'Another administrator must change your access.');
                abort_if($user->role_id === $adminRole->id && $user->active && User::where('role_id', $adminRole->id)->where('active', true)->count() <= 1, 409);
                DonorAssignment::where('user_id', $user->id)->update(['active' => false, 'clinical_permissions' => '[]', 'version' => DB::raw('version + 1')]);
                DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            }
            $user->forceFill(['name' => $input['name'], 'role_id' => $role->id, 'active' => $input['active'], 'version' => $user->version + 1, 'session_version' => $user->session_version + ($accessChanged ? 1 : 0)])->save();
            $this->audit($request, $user, 'staff.updated', $input['reason']);

            return $user;
        });

        return response()->json(['data' => $this->present($user->fresh('role'))]);
    }

    private function reauthenticate(Request $request, string $password): void
    {
        $actor = User::with('role')->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
        abort_unless($actor->active && (int) $request->session()->get('account_session_version', 1) === $actor->session_version, 401);
        abort_unless($actor->hasPermission('users.manage'), 403);
        abort_unless(Hash::check($password, $actor->password), 422, 'Confirm your current password again.');
    }

    private function audit(Request $request, User $user, string $action, string $reason): void
    {
        AuditLog::create(['actor_id' => $request->user()->id, 'action' => $action, 'subject_id' => (string) $user->id, 'reason' => $reason, 'changed_fields' => ['staff_account'], 'request_id' => $request->attributes->get('request_id')]);
    }
}
