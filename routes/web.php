<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AppAuthController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return redirect()->to(url('/office'));
});

Route::prefix('api/auth')->group(function () {
    Route::get('/user', [AppAuthController::class, 'user']);
    Route::post('/login', [AppAuthController::class, 'login']);
    Route::post('/logout', [AppAuthController::class, 'logout']);
});

Route::view('/office', 'app')->name('office');
Route::view('/client', 'app')->name('client');
Route::view('/sales', 'app')->name('sales');
Route::view('/driver', 'app')->name('driver');

Route::view('/office/{any}', 'app')->where('any', '.*');
Route::view('/client/{any}', 'app')->where('any', '.*');
Route::view('/sales/{any}', 'app')->where('any', '.*');
Route::view('/driver/{any}', 'app')->where('any', '.*');
