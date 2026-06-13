<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DailyReportController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DrillingToolController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\EquipmentController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\MaterialController;
use App\Http\Controllers\Api\RigController;
use App\Http\Controllers\Api\ShiftController;
use App\Http\Controllers\Api\TvDisplayController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Drilling OMS v2
|--------------------------------------------------------------------------
| Prefix  : /api
| Auth    : Laravel Sanctum (Bearer token)
|--------------------------------------------------------------------------
*/

// ════════════════════════════════════════════════════════════════════════
// PUBLIC — No auth required
// ════════════════════════════════════════════════════════════════════════

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
});

// ════════════════════════════════════════════════════════════════════════
// PROTECTED — Requires valid Sanctum token
// ════════════════════════════════════════════════════════════════════════
Route::middleware(['auth:sanctum'])->group(function () {

    // ── Auth / Profile ────────────────────────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::post('logout',  [AuthController::class, 'logout']);
        Route::get('me',       [AuthController::class, 'me']);
        Route::put('profile',  [AuthController::class, 'updateProfile']);
        Route::put('password', [AuthController::class, 'changePassword']);
    });

    // ── Executive Dashboard ───────────────────────────────────────────
    Route::prefix('dashboard')->group(function () {
        Route::get('stats',             [DashboardController::class, 'stats']);
        Route::get('depth-chart',       [DashboardController::class, 'depthChart']);
        Route::get('weekly-progress',   [DashboardController::class, 'weeklyProgress']);
        Route::get('rig-status',        [DashboardController::class, 'rigStatusDistribution']);
        Route::get('active-rigs',       [DashboardController::class, 'activeRigsOverview']);
        Route::get('alerts',            [DashboardController::class, 'alerts']);
        Route::get('system-status',     [DashboardController::class, 'systemStatus']);
    });

    // ── TV Display ────────────────────────────────────────────────────
    Route::get('tv-display',           [TvDisplayController::class, 'index']);
    Route::get('tv-display/rigs',      [TvDisplayController::class, 'rigs']);
    Route::get('tv-display/crew',      [TvDisplayController::class, 'crew']);
    Route::get('tv-display/equipment', [TvDisplayController::class, 'equipment']);

    // ── Rigs (Drilling Machines) ──────────────────────────────────────
    // Extra actions must be declared BEFORE apiResource to avoid route conflicts
    Route::prefix('rigs')->group(function () {
        Route::get('stats',              [RigController::class, 'stats']);
        Route::patch('{rig}/status',     [RigController::class, 'updateStatus']);
    });
    Route::apiResource('rigs', RigController::class);
    // GET    /api/rigs                → index
    // POST   /api/rigs                → store  (admin only — enforced in FormRequest)
    // GET    /api/rigs/{rig}          → show   (full detail page data)
    // PUT    /api/rigs/{rig}          → update
    // DELETE /api/rigs/{rig}          → destroy

    // ── Locations ─────────────────────────────────────────────────────
    Route::apiResource('locations', LocationController::class)->except(['show']);

    // ── Daily Reports ─────────────────────────────────────────────────
    // Static routes first — then apiResource
    Route::get('daily-reports/summary',            [DailyReportController::class, 'summary']);
    Route::patch('daily-reports/{report}/submit',   [DailyReportController::class, 'submit']);
    Route::patch('daily-reports/{report}/approve',  [DailyReportController::class, 'approve'])
        ->middleware('role:Super_Admin');
    Route::apiResource('daily-reports', DailyReportController::class);

    // ── BHA / Drilling Tools ──────────────────────────────────────────
    Route::get('tool-types',                        [DrillingToolController::class, 'toolTypes']);
    Route::get('drilling-tools/bha/{reportId}',     [DrillingToolController::class, 'bhaForReport']);
    Route::apiResource('drilling-tools', DrillingToolController::class)->except(['show']);

    // ── Materials & Fuel ──────────────────────────────────────────────
    // Fuel dashboard (Fuel Tracking tab)
    Route::prefix('materials')->group(function () {
        Route::get('types',              [MaterialController::class, 'types']);
        Route::post('types',             [MaterialController::class, 'storeType'])
            ->middleware('role:Super_Admin');

        Route::get('fuel-stats',         [MaterialController::class, 'fuelStats']);
        Route::get('fuel-levels',        [MaterialController::class, 'fuelLevels']);
        Route::get('fuel-weekly',        [MaterialController::class, 'fuelWeekly']);

        // Per-rig material stock
        Route::get('rig/{rig}',          [MaterialController::class, 'forRig']);
        Route::post('rig/{rig}',         [MaterialController::class, 'setForRig']);

        // Logs for a specific rig-material entry
        Route::get('{rigMaterial}/logs', [MaterialController::class, 'logs']);
    });

    // ── Employees ─────────────────────────────────────────────────────
    Route::get('positions',                       [EmployeeController::class, 'positions']);
    Route::post('add_postion',                    [EmployeeController::class, 'add_position']);
    Route::prefix('employees')->group(function () {
        Route::get('stats',                       [EmployeeController::class, 'stats']);
        Route::patch('{employee}/status',         [EmployeeController::class, 'updateStatus']);
        Route::get('/',                            [EmployeeController::class, 'index']);
        Route::post('/',                           [EmployeeController::class, 'store']);
        Route::get('{employee}',                     [EmployeeController::class, 'show']);
        Route::put('{employee}',                    [EmployeeController::class, 'update']);
        Route::delete('{employee}',                  [EmployeeController::class, 'destroy']);

    });
    //Route::apiResource('employees', EmployeeController::class);

    
    Route::post('employees/{employee}', [EmployeeController::class, 'update']);

    // ── Shifts ────────────────────────────────────────────────────────
    Route::prefix('shifts')->group(function () {
        Route::post('{shift}/employees',                       [ShiftController::class, 'attachEmployee']);
        Route::delete('{shift}/employees/{employeeId}',        [ShiftController::class, 'detachEmployee']);
    });
    Route::apiResource('shifts', ShiftController::class);

    // ── Equipment ─────────────────────────────────────────────────────
    Route::prefix('equipments')->group(function () {
        Route::get('stats',                       [EquipmentController::class, 'stats']);
        Route::delete('{equipment}/photo',        [EquipmentController::class, 'deletePhoto']);
        Route::get('/',                           [EquipmentController::class, 'index']);
        Route::post('/',                          [EquipmentController::class, 'store']);
        Route::get('{equipment}',                 [EquipmentController::class, 'show']);
        Route::post('{equipment}',                [EquipmentController::class, 'update']);
        Route::delete('{equipment}',              [EquipmentController::class, 'destroy']);
    });
    //Route::apiResource('equipments', EquipmentController::class);

    // ── Users Management ─────────────────────────────────────────────
    // All user management is admin-only
    Route::middleware('role:Super_Admin')->group(function () {
        Route::get('roles',                       [UserController::class, 'roles']);

        Route::prefix('users')->group(function () {
            Route::get('stats',                   [UserController::class, 'stats']);
            Route::patch('{user}/toggle-active',  [UserController::class, 'toggleActive']);
            Route::post('{user}/assign-role',     [UserController::class, 'assignRole']);
            Route::get('/',                       [UserController::class, 'index']);
            Route::post('/',                      [UserController::class, 'store']);
            Route::get('{user}',                  [UserController::class, 'show']);
            Route::post('{user}',                 [UserController::class, 'update']);
            Route::delete('{user}',               [UserController::class, 'destroy']);
        });
        //Route::apiResource('users', UserController::class);
    });
});
