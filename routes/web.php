<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', 'office');

Route::view('/{application}/{path?}', 'platform')
    ->whereIn('application', ['office', 'sales', 'driver', 'client'])
    ->where('path', '.*')
    ->name('platform');
