<?php

use App\Http\Controllers\Api\V1\DroughtController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::prefix('drought')->name('drought.')->group(function () {
        Route::get('zones/{zone}/consumption/forecast', [DroughtController::class, 'forecastZone'])->name('forecast');
    });
});
