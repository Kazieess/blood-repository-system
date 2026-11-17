<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Throwable;

class AuthController extends Controller
{
    public function login(LoginRequest $request): UserResource
    {
        if (! Auth::guard('web')->attempt([...$request->only('email', 'password'), 'active' => true])) {
            throw ValidationException::withMessages(['email' => ['The provided credentials are invalid or unavailable.']]);
        }

        $request->session()->regenerate();
        $request->session()->put('account_session_version', (int) Auth::user()->session_version);

        try {
            AuditLog::create([
                'actor_id' => Auth::id(), 'action' => 'auth.login',
                'request_id' => $request->attributes->get('request_id'),
            ]);
        } catch (Throwable $exception) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();

            throw $exception;
        }

        return new UserResource(Auth::user()->load('role'));
    }

    public function logout(Request $request): Response
    {
        try {
            AuditLog::create([
                'actor_id' => $request->user()->id, 'action' => 'auth.logout',
                'request_id' => $request->attributes->get('request_id'),
            ]);
        } finally {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->noContent();
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('role'));
    }
}
