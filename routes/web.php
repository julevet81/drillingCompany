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
