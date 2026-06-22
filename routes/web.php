<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/run-seeders', function () {
    Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);
    return 'Seeders done';
});

Route::get('/clear-cache', function () {
    Artisan::call('permission:cache-reset');
    Artisan::call('optimize:clear');
    return 'Cache cleared';
});

Route::get('/run-migrations', function () {
    // ← حماية بسيطة بمفتاح سري
    if (request('secret') !== env('MIGRATION_SECRET')) {
        abort(403, 'Unauthorized');
    }

    Artisan::call('migrate', ['--force' => true]);

    return response()->json([
        'success' => true,
        'output'  => Artisan::output(),
    ]);
});
