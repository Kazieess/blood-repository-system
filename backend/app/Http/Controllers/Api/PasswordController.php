<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    private function rules(): array
    {
        return ['required', 'confirmed', 'max:128', PasswordRule::min(12)->mixedCase()->numbers()->symbols()];
    }

    public function status(): JsonResponse
    {
        return response()->json(['email_recovery_enabled' => $this->enabled()]);
    }

    private function enabled(): bool
    {
        return (bool) config('repository.password_reset_email') && ! in_array(config('mail.default'), ['log', 'array', 'failover'], true);
    }

    public function forgot(Request $request): JsonResponse
    {
        $input = $request->validate(['email' => ['required', 'email', 'max:255']]);
        abort_unless($this->enabled(), 503);
        Password::sendResetLink(['email' => strtolower($input['email']), 'active' => true]);

        return response()->json(['message' => 'If an active account matches, a reset link will be sent. Check your inbox or contact your administrator.']);
    }

    public function reset(Request $request): JsonResponse
    {
        $input = $request->validate(['email' => ['required', 'email', 'max:255'], 'token' => ['required', 'string', 'max:255'], 'password' => $this->rules()]);
        $status = DB::transaction(function () use ($request, $input): string {
            User::where('email', strtolower($input['email']))->lockForUpdate()->first();

            return Password::reset([...$input, 'email' => strtolower($input['email']), 'active' => true], function (User $user, string $password) use ($request): void {
                $this->save($request, $user, $password, 'auth.password_reset');
            });
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['token' => ['The reset link is invalid, expired or already used. Request a new link.']]);
        }

        return response()->json(['message' => 'Password updated. Sign in with your new password.']);
    }

    public function change(Request $request): JsonResponse
    {
        $input = $request->validate(['current_password' => ['required', 'current_password:web'], 'password' => $this->rules()]);
        $user = DB::transaction(function () use ($request, $input): User {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless($user->active && (int) $request->session()->get('account_session_version', 1) === $user->session_version, 401);
            abort_unless(Hash::check($input['current_password'], $user->password), 422);
            $this->save($request, $user, $input['password'], 'auth.password_changed');
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();

            return $user;
        });
        $request->session()->regenerate();
        $request->session()->put('account_session_version', $user->session_version);

        return response()->json(['message' => 'Password updated. Other sessions are invalidated.']);
    }

    private function save(Request $request, User $user, string $password, string $action): void
    {
        $user->forceFill(['password' => $password, 'remember_token' => Str::random(60), 'session_version' => $user->session_version + 1, 'version' => $user->version + 1])->save();
        AuditLog::create(['actor_id' => $user->id, 'action' => $action, 'subject_id' => (string) $user->id, 'changed_fields' => ['password_changed'], 'request_id' => $request->attributes->get('request_id')]);
    }
}
