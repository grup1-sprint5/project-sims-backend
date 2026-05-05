<?php

use Illuminate\Support\Facades\Route;

$distRoot = '/var/www/sims-front/dist';

$mimeTypes = [
    'js'    => 'application/javascript',
    'mjs'   => 'application/javascript',
    'css'   => 'text/css',
    'svg'   => 'image/svg+xml',
    'png'   => 'image/png',
    'jpg'   => 'image/jpeg',
    'jpeg'  => 'image/jpeg',
    'gif'   => 'image/gif',
    'webp'  => 'image/webp',
    'ico'   => 'image/x-icon',
    'woff'  => 'font/woff',
    'woff2' => 'font/woff2',
    'ttf'   => 'font/ttf',
    'otf'   => 'font/otf',
    'json'  => 'application/json',
];

$serveFile = function (string $path) use ($mimeTypes) {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $mime = $mimeTypes[$ext] ?? mime_content_type($path);
    return response()->file($path, ['Content-Type' => $mime]);
};

// Serve Vue SPA static assets securely
Route::get('/assets/{file}', function (string $file) use ($distRoot, $serveFile) {
    $path = realpath($distRoot . '/assets/' . $file);
    if ($path && str_starts_with($path, $distRoot) && is_file($path)) {
        return $serveFile($path);
    }
    abort(404);
})->where('file', '[^.][^/].*');

Route::get('/branding/{file}', function (string $file) use ($distRoot, $serveFile) {
    $path = realpath($distRoot . '/branding/' . $file);
    if ($path && str_starts_with($path, $distRoot) && is_file($path)) {
        return $serveFile($path);
    }
    abort(404);
})->where('file', '[^.][^/].*');

Route::get('/favicon.ico', function () use ($distRoot) {
    $path = $distRoot . '/favicon.ico';
    return file_exists($path) ? response()->file($path) : abort(404);
});

// Serve Vue SPA entry point — hash router only needs '/'
Route::get('/', function () use ($distRoot) {
    $index = $distRoot . '/index.html';
    if (file_exists($index)) {
        return response(file_get_contents($index))
            ->header('Content-Type', 'text/html; charset=utf-8');
    }
    return view('welcome');
});
