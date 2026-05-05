<?php

use Illuminate\Support\Facades\Route;

$distRoot = '/var/www/sims-front/dist';

// Serve Vue SPA static assets securely
Route::get('/assets/{file}', function (string $file) use ($distRoot) {
    $path = realpath($distRoot . '/assets/' . $file);
    if ($path && str_starts_with($path, $distRoot) && is_file($path)) {
        return response()->file($path);
    }
    abort(404);
})->where('file', '[^.][^/].*');

Route::get('/branding/{file}', function (string $file) use ($distRoot) {
    $path = realpath($distRoot . '/branding/' . $file);
    if ($path && str_starts_with($path, $distRoot) && is_file($path)) {
        return response()->file($path);
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
