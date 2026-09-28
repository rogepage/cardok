<?php

use App\Livewire\VehicleDebtLookup;
use Illuminate\Support\Facades\Route;

Route::get('/', VehicleDebtLookup::class)->name('home');

