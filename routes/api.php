<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\ProductSyncController;
use App\Http\Controllers\Api\PunchOutCxmlController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/products/sync', [ProductSyncController::class, 'sync']);
Route::post('/punchout/cxml/order', [PunchOutCxmlController::class, 'handleOrder']);
