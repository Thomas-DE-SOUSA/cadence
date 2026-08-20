<?php

declare(strict_types=1);

use Cadence\Activity\Infrastructure\Http\Controller\ShowDashboardController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', ShowDashboardController::class)->name('dashboard');

// Section shells — filled in as each bounded context lands (see ROADMAP.md).
Route::get('/historique', fn () => Inertia::render('History'))->name('history');
Route::get('/progression', fn () => Inertia::render('Progression'))->name('progression');
Route::get('/programme', fn () => Inertia::render('Program'))->name('program');
Route::get('/allures', fn () => Inertia::render('Paces'))->name('paces');
Route::get('/profil', fn () => Inertia::render('Profile'))->name('profile');
