<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Panel\BoutResultController;
use App\Http\Controllers\Panel\BoutScoreController;
use App\Http\Controllers\Panel\BracketController;
use App\Http\Controllers\Panel\CategoryController;
use App\Http\Controllers\Panel\CompetitorController;
use App\Http\Controllers\Panel\EventController;
use App\Http\Controllers\Panel\ExportController;
use App\Http\Controllers\Panel\PublicationController;
use App\Http\Controllers\Panel\RegistrationController;
use App\Http\Controllers\Panel\TatamiController;
use App\Http\Controllers\Panel\TatamiOperatorController;
use App\Http\Controllers\Panel\WithdrawalController;
use App\Http\Controllers\PublicCategoryController;
use App\Http\Controllers\PublicEventController;
use App\Http\Controllers\PublicRegistrationController;
use App\Http\Middleware\EnsureResultsAccess;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('eventos/{event:slug}', [PublicEventController::class, 'show'])->name('public.events.show');
Route::get('eventos/{event:slug}/poster', [PublicEventController::class, 'poster'])->name('public.events.poster');
Route::get('eventos/{event:slug}/normas.pdf', [PublicEventController::class, 'rules'])->name('public.events.rules');
Route::get('eventos/{event:slug}/inscripcion', [PublicRegistrationController::class, 'create'])->name('public.registration.create');
Route::post('eventos/{event:slug}/codigo', [PublicEventController::class, 'access'])
    ->middleware('throttle:10,1')->name('public.events.access');
Route::get('eventos/{event:slug}/categorias/{category}', [PublicCategoryController::class, 'show'])
    ->middleware(EnsureResultsAccess::class)->scopeBindings()->name('public.categories.show');
Route::post('eventos/{event:slug}/inscripcion', [PublicRegistrationController::class, 'store'])
    ->middleware('throttle:10,1')->name('public.registration.store');

Route::middleware('guest')->group(function () {
    Route::get('registro', [RegisterController::class, 'create'])->name('register');
    Route::post('registro', [RegisterController::class, 'store'])->middleware('throttle:10,1');
    Route::get('acceso', [LoginController::class, 'create'])->name('login');
    Route::post('acceso', [LoginController::class, 'store'])->middleware('throttle:6,1');
    Route::get('recuperar-contrasena', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('recuperar-contrasena', [PasswordResetLinkController::class, 'store'])->middleware('throttle:6,1')->name('password.email');
    Route::get('restablecer-contrasena/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('restablecer-contrasena', [NewPasswordController::class, 'store'])->middleware('throttle:6,1')->name('password.update');
});

Route::post('salir', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware('auth')->prefix('panel')->name('panel.')->group(function () {
    Route::get('eventos', [EventController::class, 'index'])->name('events.index');
    Route::get('eventos/crear', [EventController::class, 'create'])->name('events.create');
    Route::post('eventos', [EventController::class, 'store'])->name('events.store');

    // Tatami: el propietario o un controlador asignado a ese tatami puntúa el combate actual (policy operate).
    Route::middleware('can:operate,event,tatami')->prefix('eventos/{event}/tatamis/{tatami}')->whereNumber('tatami')->name('tatamis.')->group(function () {
        Route::get('/', [TatamiController::class, 'show'])->name('show');
        Route::put('combates/{bout}/puntuacion', [TatamiController::class, 'score'])->name('score');
    });

    // Todo lo que cuelga de un evento exige ser su propietario; scopeBindings impide mezclar recursos de otro evento.
    Route::middleware('can:manage,event')->scopeBindings()->prefix('eventos/{event}')->group(function () {
        Route::get('/', [EventController::class, 'show'])->name('events.show');
        Route::post('dorsales', [EventController::class, 'assignBibs'])->name('events.bibs');
        Route::post('codigo-acceso', [EventController::class, 'regenerateAccessCode'])->name('events.access-code');
        Route::post('controladores-tatami', [TatamiOperatorController::class, 'store'])->name('operators.store');
        Route::delete('controladores-tatami/{operator}', [TatamiOperatorController::class, 'destroy'])->name('operators.destroy');
        Route::get('normas.pdf', [EventController::class, 'rules'])->name('events.rules');
        Route::get('poster', [EventController::class, 'poster'])->name('events.poster');
        Route::get('editar', [EventController::class, 'edit'])->name('events.edit');
        Route::put('/', [EventController::class, 'update'])->name('events.update');
        Route::delete('/', [EventController::class, 'destroy'])->name('events.destroy');
        Route::get('categorias/crear', [CategoryController::class, 'create'])->name('categories.create');
        Route::post('categorias', [CategoryController::class, 'store'])->name('categories.store');
        Route::get('categorias/{category}/editar', [CategoryController::class, 'edit'])->name('categories.edit');
        Route::put('categorias/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::delete('categorias/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
        Route::get('inscripciones', [RegistrationController::class, 'index'])->name('registrations.index');
        Route::get('inscripciones/crear', [RegistrationController::class, 'create'])->name('registrations.create');
        Route::post('inscripciones', [RegistrationController::class, 'store'])->name('registrations.store');
        Route::get('inscripciones/{registration}', [RegistrationController::class, 'show'])->name('registrations.show');
        Route::patch('inscripciones/{registration}', [RegistrationController::class, 'update'])->name('registrations.update');
        Route::get('competidores/{competitor}/editar', [CompetitorController::class, 'edit'])->name('competitors.edit');
        Route::get('competidores/{competitor}/foto', [CompetitorController::class, 'photo'])->name('competitors.photo');
        Route::put('competidores/{competitor}', [CompetitorController::class, 'update'])->name('competitors.update');
        Route::get('exportar/inscritos.csv', [ExportController::class, 'registrations'])->name('exports.registrations');
        Route::get('categorias/{category}/cuadro', [BracketController::class, 'show'])->name('brackets.show');
        Route::post('categorias/{category}/cuadro', [BracketController::class, 'store'])->name('brackets.store');
        Route::post('categorias/{category}/cuadro/intercambio', [BracketController::class, 'swap'])->name('brackets.swap');
        Route::put('categorias/{category}/combates/{bout}/resultado', [BoutResultController::class, 'update'])->name('bouts.result');
        Route::put('categorias/{category}/combates/{bout}/puntuacion', [BoutScoreController::class, 'update'])->name('bouts.score');
        Route::post('categorias/{category}/bajas', [WithdrawalController::class, 'store'])->name('withdrawals.store');
        Route::patch('categorias/{category}/publicacion', [PublicationController::class, 'toggle'])->name('categories.publication');
        Route::get('exportar/clasificacion.csv', [ExportController::class, 'standings'])->name('exports.standings');
        // Las tareas siguientes añaden aquí categorías, inscripciones, cuadros, exportaciones…
    });
});
