<?php

declare(strict_types=1);

use Cadence\Activity\Infrastructure\Http\Controller\ShowDashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', ShowDashboardController::class)->name('dashboard');
