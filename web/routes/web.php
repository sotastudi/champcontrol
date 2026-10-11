<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Panel\CategoryController;
use App\Http\Controllers\Panel\EventController;
use App\Http\Controllers\PublicEventController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('eventos/{event:slug}', [PublicEventController::class, 'show'])->name('public.events.show');

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

Route::middleware('auth')->prefix('panel')->name('panel.')->group(function () {
    Route::get('eventos', [EventController::class, 'index'])->name('events.index');
    Route::get('eventos/crear', [EventController::class, 'create'])->name('events.create');
    Route::post('eventos', [EventController::class, 'store'])->name('events.store');

    // Todo lo que cuelga de un evento exige ser su propietario; scopeBindings impide mezclar recursos de otro evento.
    Route::middleware('can:manage,event')->scopeBindings()->prefix('eventos/{event}')->group(function () {
        Route::get('/', [EventController::class, 'show'])->name('events.show');
        Route::get('editar', [EventController::class, 'edit'])->name('events.edit');
        Route::put('/', [EventController::class, 'update'])->name('events.update');
        Route::delete('/', [EventController::class, 'destroy'])->name('events.destroy');
        Route::get('categorias/crear', [CategoryController::class, 'create'])->name('categories.create');
        Route::post('categorias', [CategoryController::class, 'store'])->name('categories.store');
        Route::get('categorias/{category}/editar', [CategoryController::class, 'edit'])->name('categories.edit');
        Route::put('categorias/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::delete('categorias/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
        // Las tareas siguientes añaden aquí categorías, inscripciones, cuadros, exportaciones…
    });
});
