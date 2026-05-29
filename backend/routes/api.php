<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware('throttle:api')
    ->group(base_path('routes/api/v1/central.php'));
