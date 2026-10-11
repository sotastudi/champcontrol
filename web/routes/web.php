<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('registro', [RegisterController::class, 'create'])->name('register');
    Route::post('registro', [RegisterController::class, 'store'])->middleware('throttle:10,1');
    Route::get('acceso', [LoginController::class, 'create'])->name('login');
    Route::post('acceso', [LoginController::class, 'store'])->middleware('throttle:6,1');
    Route::get('recuperar-contrasena', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('recuperar-contrasena', [PasswordResetLinkController::class, 'store'])->middleware('throttle:6,1')->name('password.email');
    Route::get('restablecer-contrasena/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('restablecer-contrasena', [NewPasswordController::class, 'store'])->name('password.update');
});

Route::post('salir', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

// Provisional: la Task 4 lo sustituye por EventController.
Route::middleware('auth')->prefix('panel')->name('panel.')->group(function () {
    Route::view('eventos', 'panel.events.index', ['events' => collect()])->name('events.index');
});
