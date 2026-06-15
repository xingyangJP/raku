<?php

use App\Http\Controllers\Api\ConfirmedEstimateController;
use App\Http\Middleware\EnsureExternalApiToken;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware(EnsureExternalApiToken::class)
    ->group(function (): void {
        Route::get('/confirmed-estimates', [ConfirmedEstimateController::class, 'index']);
        Route::get('/confirmed-estimates/{estimate}', [ConfirmedEstimateController::class, 'show'])
            ->whereNumber('estimate');
    });
