<?php

use App\Http\Controllers\ArtWorkController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
Route::post('/exercise-1-artwork-version', [ArtWorkController::class, 'index'])->name('artwork.submit' );
Route::post('/exercise-2-tier-pricing', [ArtWorkController::class, 'pricing'])->name('pricing.submit' );
Route::post('/exercise-3-cart-validator', [ArtWorkController::class, 'validateCart'])->name('cart.validate' );
Route::post('/exercise-4-vendor-allocation', [ArtWorkController::class, 'multiVendorAllocation'])->name('vendor.allocation' );
Route::post('/exercise-5-discount', [ArtWorkController::class, 'discountConflictResolver'])->name('discount.apply' );
Route::post('/exercise-6-approval-flow', [ArtWorkController::class, 'flowValidator'])->name('approval.flow' );