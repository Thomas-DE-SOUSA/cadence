<?php

declare(strict_types=1);

use Cadence\Activity\Infrastructure\Http\Controller\ShowActivityController;
use Cadence\Activity\Infrastructure\Http\Controller\ShowDashboardController;
use Cadence\Activity\Infrastructure\Http\Controller\ShowHistoryController;
use Cadence\Activity\Infrastructure\Http\Controller\StoreActivityController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', ShowDashboardController::class)->name('dashboard');

// Activity read + manual entry.
Route::get('/historique', ShowHistoryController::class)->name('history');
Route::get('/activites/nouvelle', fn () => Inertia::render('ActivityForm'))->name('activities.create');
Route::post('/activites', StoreActivityController::class)->name('activities.store');
Route::get('/activites/{id}', ShowActivityController::class)->name('activities.show');

// Section shells — filled in as each bounded context lands (see ROADMAP.md).
Route::get('/progression', fn () => Inertia::render('Progression'))->name('progression');
Route::get('/programme', fn () => Inertia::render('Program'))->name('program');
Route::get('/allures', fn () => Inertia::render('Paces'))->name('paces');
Route::get('/profil', fn () => Inertia::render('Profile'))->name('profile');
