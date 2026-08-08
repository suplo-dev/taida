<?php

use Illuminate\Support\Facades\Route;

/*
 * This application is an API backend for the Nuxt frontend; it renders no HTML.
 * Sanctum's own /sanctum/csrf-cookie route is registered by the package.
 */
Route::get('/', fn () => response()->json([
    'name' => config('app.name').' API',
    'docs' => url('/api/v1/ping'),
]));
