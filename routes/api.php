<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])
    ->middleware('throttle:10,1');

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:6,1');

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Sepet uçları ziyaretçiye de açıktır; kimlik `X-Cart-Token` başlığıyla taşınır.
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/cart', [CartController::class, 'show']);
    Route::delete('/cart', [CartController::class, 'clear']);
    Route::post('/cart/items', [CartController::class, 'store']);
    Route::patch('/cart/items/{item}', [CartController::class, 'update'])->whereNumber('item');
    Route::delete('/cart/items/{item}', [CartController::class, 'destroy'])->whereNumber('item');
});

Route::middleware(['auth:sanctum', 'role:admin', 'throttle:60,1'])->group(function () {
    Route::get('/categories/tree', [CategoryController::class, 'tree']);

    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('products', ProductController::class);
});
