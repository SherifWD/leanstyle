<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/store/{path}', function (string $path) {
    // prevent path traversal
    abort_if(str_contains($path, '..'), 404);

    $full = public_path('store/' . $path);
    abort_unless(is_file($full), 404);

    return response()->file($full, [
        'Cache-Control' => 'public, max-age=31536000, immutable'
    ]);
})->where('path', '.*');