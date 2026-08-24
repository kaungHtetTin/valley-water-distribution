<?php

use App\Http\Controllers\Api\PhaseZeroController;
use App\Http\Controllers\Api\PublicAttendanceController;
use App\Http\Controllers\Auth\AppAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', [AppAuthController::class, 'user']);

Route::get('/phase-zero', [PhaseZeroController::class, 'show']);
Route::post('/phase-zero/login', [PhaseZeroController::class, 'validateDemoLogin'])->middleware('throttle:10,1');
Route::get('/public/attendance/{token}', [PublicAttendanceController::class, 'show'])->middleware('throttle:60,1');
Route::post('/public/attendance/{token}', [PublicAttendanceController::class, 'submit'])->middleware(['throttle:30,1', 'audit.api']);
