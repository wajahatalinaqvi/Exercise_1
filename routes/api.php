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
Route::post('/exercise-7-inventory', [ArtWorkController::class, 'inventoryReservation'])->name('inventory.reservation' );
Route::post('/exercise-8-shipment', [ArtWorkController::class, 'shipmentTracker'])->name('shipment.tracker' );
Route::post('/exercise-9-webhook', [ArtWorkController::class, 'webhookDeduplicator'])->name('webhook.deduplicator' );
Route::post('/exercise-10-quote-expiry', [ArtWorkController::class, 'quoteExpiryEngine'])->name('quote.expiry');
Route::post('/exercise-11-product-visibility', [ArtWorkController::class, 'productVisibilityEngine'])->name('product.visibility');
Route::post('/exercise-12-bundle-pricing', [ArtWorkController::class, 'bundlePricingEngine'])->name('bundle.pricing');
Route::post('/exercise-13-cart-merge', [ArtWorkController::class, 'mergeCarts'])->name('merge.carts');
Route::post('/exercise-14-upsell', [ArtWorkController::class, 'findtwoNumberIndices'])->name('upsell.suggestions');
Route::post('/exercise-15-shipping-rule', [ArtWorkController::class, 'shippingEngineRule'])->name('shipping.rule');
Route::post('/exercise-16-fraud-detector', [ArtWorkController::class, 'fraudDetector'])->name('fraud.detector');
