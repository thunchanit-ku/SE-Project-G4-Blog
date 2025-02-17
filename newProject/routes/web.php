<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BookingController;
Route::get('/', function () {
    return view('welcome');
});

// Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
Route::get('book',[BookingController::class,'checkTable']);