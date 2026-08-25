<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthenticateController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ShowLoginController;
use App\Http\Controllers\Auth\ShowRegisterController;
use App\Http\Controllers\Auth\UpdatePasswordController;
use Cadence\Activity\Infrastructure\Http\Controller\DeleteActivityController;
use Cadence\Activity\Infrastructure\Http\Controller\ImportActivityFromGpxController;
use Cadence\Activity\Infrastructure\Http\Controller\ImportActivityFromTextController;
use Cadence\Activity\Infrastructure\Http\Controller\ShowActivityController;
use Cadence\Activity\Infrastructure\Http\Controller\ShowEditActivityController;
use Cadence\Activity\Infrastructure\Http\Controller\ShowHistoryController;
use Cadence\Activity\Infrastructure\Http\Controller\ShowPacesController;
use Cadence\Activity\Infrastructure\Http\Controller\ShowProgressionController;
use Cadence\Activity\Infrastructure\Http\Controller\StoreActivityController;
use Cadence\Activity\Infrastructure\Http\Controller\UpdateActivityController;
use Cadence\Athlete\Infrastructure\Http\Controller\ShowProfileController;
use Cadence\Athlete\Infrastructure\Http\Controller\UpdateProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Guest authentication (open sign-up + login).
Route::middleware('guest')->group(function (): void {
    Route::get('/login', ShowLoginController::class)->name('login');
    Route::post('/login', AuthenticateController::class);
    Route::get('/register', ShowRegisterController::class)->name('register');
    Route::post('/register', RegisterController::class);
});

Route::post('/logout', LogoutController::class)->middleware('auth')->name('logout');

// Everything below requires an authenticated user; data is scoped to the
// user's private tenant.
Route::middleware('auth')->group(function (): void {
    // The dashboard is the gamified activity board.
    Route::get('/', ShowHistoryController::class)->name('dashboard');

    // Activity read + manual entry.
    Route::get('/activites/nouvelle', fn () => Inertia::render('ActivityForm'))->name('activities.create');
    Route::post('/activites', StoreActivityController::class)->name('activities.store');
    Route::post('/activites/importer-texte', ImportActivityFromTextController::class)->name('activities.import-text');
    Route::post('/activites/importer-gpx', ImportActivityFromGpxController::class)->name('activities.import-gpx');
    Route::get('/activites/{id}/modifier', ShowEditActivityController::class)->name('activities.edit');
    Route::get('/activites/{id}', ShowActivityController::class)->name('activities.show');
    Route::put('/activites/{id}', UpdateActivityController::class)->name('activities.update');
    Route::delete('/activites/{id}', DeleteActivityController::class)->name('activities.destroy');

    // Section shells — filled in as each bounded context lands (see ROADMAP.md).
    Route::get('/progression', ShowProgressionController::class)->name('progression');
    Route::get('/allures', ShowPacesController::class)->name('paces');
    Route::get('/profil', ShowProfileController::class)->name('profile');
    Route::post('/profil', UpdateProfileController::class)->name('profile.update');
    Route::post('/profil/mot-de-passe', UpdatePasswordController::class)->name('profile.password');
});
