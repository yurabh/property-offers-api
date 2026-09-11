<?php

use App\Http\Controllers\Import\ImportController;
use App\Http\Controllers\Property\PropertyController;
use App\Http\Controllers\Reservation\ReservationController;
use Illuminate\Support\Facades\Route;

Route::post('imports', [ImportController::class, 'store']);
Route::get('imports/{import}', [ImportController::class, 'show']);

Route::get('properties', PropertyController::class);

Route::post('offers/{offer}/reservations', ReservationController::class);
