<?php

use App\Http\Middleware\RequestContext;
use App\Http\Middleware\RequireActiveAccount;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(prepend: [RequestContext::class]);
        $middleware->alias(['active' => RequireActiveAccount::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());
        $exceptions->report(function (Throwable $exception): ?bool {
            if (request()->is('api/*')) {
                Log::error('API request failed.', [
                    'exception_type' => get_class($exception),
                    'request_id' => request()->attributes->get('request_id'),
                ]);

                return false;
            }

            return null;
        });
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = match (true) {
                $exception instanceof ValidationException => 422,
                $exception instanceof AuthenticationException => 401,
                $exception instanceof AuthorizationException => 403,
                $exception instanceof ModelNotFoundException => 404,
                $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
                default => 500,
            };
            $requestId = $request->attributes->get('request_id') ?? (string) Str::uuid();
            $message = match ($status) {
                401 => 'Sign in to continue.',
                403 => 'You do not have permission for this action.',
                404 => 'The requested record was not found.',
                409 => 'The record or request has changed. Reload before retrying.',
                419 => 'Your session expired. Sign in again.',
                422 => 'Check the submitted fields.',
                429 => 'Too many requests. Wait before trying again.',
                default => 'The service could not complete this request.',
            };
            $headers = $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [];

            return response()->json([
                'message' => $message, 'code' => 'HTTP_'.$status, 'request_id' => $requestId,
                ...($exception instanceof ValidationException ? ['errors' => $exception->errors()] : []),
            ], $status, [...$headers, 'X-Request-ID' => $requestId, 'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
        });
    })->create();
