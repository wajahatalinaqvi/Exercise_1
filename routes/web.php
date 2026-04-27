<?php

use App\Http\Controllers\ArtWorkController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::post('/exercise-1-artwork-version', [ArtWorkController::class, 'index'])->name('artwork.submit' );