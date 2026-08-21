<?php

declare(strict_types=1);

use Cadence\Activity\Infrastructure\Http\Controller\DeleteActivityController;
use Cadence\Activity\Infrastructure\Http\Controller\ImportActivityFromGpxController;
use Cadence\Activity\Infrastructure\Http\Controller\ImportActivityFromTextController;
use Cadence\Activity\Infrastructure\Http\Controller\ShowActivityController;
use Cadence\Activity\Infrastructure\Http\Controller\ShowEditActivityController;
use Cadence\Activity\Infrastructure\Http\Controller\ShowHistoryController;
use Cadence\Activity\Infrastructure\Http\Controller\StoreActivityController;
use Cadence\Activity\Infrastructure\Http\Controller\UpdateActivityController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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
Route::get('/progression', fn () => Inertia::render('Progression'))->name('progression');
Route::get('/allures', fn () => Inertia::render('Paces'))->name('paces');
Route::get('/profil', fn () => Inertia::render('Profile'))->name('profile');
