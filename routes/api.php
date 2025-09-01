<?php

use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SearchController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login',    [AuthController::class, 'login']);
    Route::post('refresh',  [AuthController::class, 'refresh'])->middleware('auth:api');
    Route::post('logout',   [AuthController::class, 'logout'])->middleware('auth:api');
    Route::get('me',        [AuthController::class, 'me'])->middleware('auth:api');
    Route::post('update-password', [AuthController::class, 'updatePassword'])->middleware('auth:api');
});

// Public
Route::get('home', [\App\Http\Controllers\Api\HomeController::class, 'index']);
Route::get('products', [\App\Http\Controllers\Api\HomeController::class, 'products']);
Route::get('stores',   [\App\Http\Controllers\Api\HomeController::class, 'stores']);

// Product Details
Route::get('product/{product}',        [ProductController::class, 'show']);
Route::get('product/{product}/related',[ProductController::class, 'related']);
Route::get('store/{store}/products',   [ProductController::class, 'storeProducts']);

// Search & suggestions
Route::get('search/suggest', [SearchController::class, 'suggest']);
Route::get('search',         [SearchController::class, 'search']);

// Auth block already added earlier…

// Authenticated user flow
Route::middleware('auth:api')->group(function () {
    // Addresses
    Route::get('addresses',            [AddressController::class, 'index']);
    Route::post('addresses',           [AddressController::class, 'store']);
    Route::put('addresses/{address}',  [AddressController::class, 'update']);
    Route::delete('addresses/{address}',[AddressController::class, 'destroy']);
    Route::post('addresses/{address}/make-default', [AddressController::class, 'makeDefault']);

    // Cart
    Route::get('cart',                     [CartController::class, 'show']);
    Route::post('cart/add',                [CartController::class, 'add']);
    Route::post('cart/update',             [CartController::class, 'updateItem']);
    Route::post('cart/remove',             [CartController::class, 'remove']);
    Route::post('cart/clear',              [CartController::class, 'clear']);
    Route::post('cart/apply-coupon',       [CartController::class, 'applyCoupon']);
    Route::post('cart/select-address',     [CartController::class, 'selectAddress']);
    Route::post('cart/select-payment',     [CartController::class, 'selectPayment']);
    Route::post('cart/checkout',           [CartController::class, 'checkout']); // convert to order

    // Orders
    Route::get('orders',          [OrderController::class, 'index']);
    Route::get('orders/{order}',  [OrderController::class, 'show']);
    Route::get('orders/{order}/timeline', [OrderController::class, 'timeline']);
});
Route::prefix('driver')
    ->middleware(['auth:api','role:delivery_boy'])
    ->group(function () {
        Route::get('me', [DriverController::class, 'me']);

        // Availability
        Route::post('availability', [DriverController::class, 'setAvailability']);

        // Orders & assignments
        Route::get('orders', [DriverController::class, 'orders']); // ?status=pending|assigned|picked|out_for_delivery|delivered|history|active
        Route::post('orders/{order}/accept', [DriverController::class, 'accept']);
        Route::post('orders/{order}/reject', [DriverController::class, 'reject']);
        Route::post('orders/{order}/status', [DriverController::class, 'updateStatus']); // body: {to_status}

        // Cash (COD)
        Route::get('cash/summary', [DriverController::class, 'cashSummary']);
        Route::post('cash/collect', [DriverController::class, 'collectCash']);   // {order_id, amount, note?}
        Route::post('cash/remit',   [DriverController::class, 'remitCash']);     // {amount, reference?}
    });