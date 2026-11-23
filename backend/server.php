<?php

// Local development router. Never use the repository root as the document root.
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
if (in_array($uri, ['/favicon.ico', '/robots.txt'], true) && is_file(__DIR__.'/public'.$uri)) {
    return false;
}

require __DIR__.'/public/index.php';
