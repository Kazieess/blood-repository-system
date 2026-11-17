<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FrontendController extends Controller
{
    public function home(): BinaryFileResponse
    {
        return response()->file(base_path('../frontend/index.html'));
    }

    public function recovery(): BinaryFileResponse
    {
        return response()->file(base_path('../frontend/recovery.html'));
    }

    public function availability(): BinaryFileResponse
    {
        return response()->file(base_path('../frontend/availability.html'));
    }

    public function workspace(): BinaryFileResponse
    {
        return response()->file(base_path('../frontend/connected.html'));
    }

    public function asset(string $directory, string $file): BinaryFileResponse
    {
        abort_unless(in_array($directory, ['css', 'src', 'assets/images'], true), 404);
        $base = realpath(base_path('../frontend/'.$directory));
        $path = realpath(base_path('../frontend/'.$directory.'/'.$file));
        abort_unless($base && $path && str_starts_with($path, $base.DIRECTORY_SEPARATOR) && is_file($path), 404);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        abort_unless(in_array($extension, ['css', 'js', 'png'], true), 404);
        $mime = match ($extension) {
            'css' => 'text/css; charset=utf-8',
            'js' => 'text/javascript; charset=utf-8',
            default => 'image/png',
        };

        return response()->file($path, ['Content-Type' => $mime, 'X-Content-Type-Options' => 'nosniff']);
    }
}
