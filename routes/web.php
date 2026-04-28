<?php

use App\Http\Controllers\ArtWorkController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::post('/exercise-1-artwork-version', [ArtWorkController::class, 'index'])->name('artwork.submit' );
Route::post('/exercise-2-tier-pricing', [ArtWorkController::class, 'pricing'])->name('pricing.submit' );