<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

// Temporal: se reemplazan en la Task 3.
Route::view('/acceso', 'home')->name('login');
Route::view('/registro', 'home')->name('register');
