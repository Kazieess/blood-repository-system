<?php

namespace App\Support;

/** Public source assets only; this class deliberately needs no framework bootstrap. */
class FrontendAssets
{
    /** @return array{path: string, mime: string}|null */
    public static function resolve(string $uri): ?array
    {
        $uri = rawurldecode($uri);
        if (! preg_match('~\A/(css|src|assets/images)/([A-Za-z0-9_ .%-]+)\z~', $uri, $parts)) {
            return null;
        }
        $extensions = ['css' => 'css', 'src' => 'js', 'assets/images' => 'png'];
        $extension = strtolower(pathinfo($parts[2], PATHINFO_EXTENSION));
        if ($extension !== $extensions[$parts[1]]) {
            return null;
        }
        $root = realpath(dirname(__DIR__, 3).'/frontend');
        $directory = realpath(dirname(__DIR__, 3).'/frontend/'.$parts[1]);
        $path = realpath(dirname(__DIR__, 3).'/frontend'.$uri);
        if (! $root || ! $directory || ! $path || ! str_starts_with($directory, $root.DIRECTORY_SEPARATOR) || dirname($path) !== $directory || ! is_file($path)) {
            return null;
        }

        return ['path' => $path, 'mime' => match ($extension) {
            'css' => 'text/css; charset=utf-8',
            'js' => 'text/javascript; charset=utf-8',
            'png' => 'image/png',
        }];
    }

    public static function send(string $uri, string $method): bool
    {
        if (! in_array($method, ['GET', 'HEAD'], true) || ! ($asset = self::resolve($uri))) {
            return false;
        }
        $etag = '"'.hash_file('sha256', $asset['path']).'"';
        header('Content-Type: '.$asset['mime']);
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');
        header('Cache-Control: public, max-age=0, must-revalidate');
        header('ETag: '.$etag);
        $requestedTags = array_map('trim', explode(',', $_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
        if (in_array($etag, $requestedTags, true) || in_array('W/'.$etag, $requestedTags, true) || in_array('*', $requestedTags, true)) {
            http_response_code(304);

            return true;
        }
        header('Content-Length: '.filesize($asset['path']));
        if ($method === 'GET') {
            readfile($asset['path']);
        }

        return true;
    }
}
