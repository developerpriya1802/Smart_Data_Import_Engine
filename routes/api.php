<?php

use App\Http\Controllers\ImportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('import')->group(function () {
    Route::post('/upload', [ImportController::class, 'upload']);
    Route::post('/mapping', [ImportController::class, 'saveMapping']);
    Route::post('/start', [ImportController::class, 'start']);
    Route::get('/status/{id}', [ImportController::class, 'status']);
    Route::get('/errors/{id}', [ImportController::class, 'errors']);
    Route::get('/errors/{id}/download', [ImportController::class, 'downloadErrorReport']);
    Route::post('/retry/{id}', [ImportController::class, 'retry']);
    Route::get('/jobs', [ImportController::class, 'list']);
});