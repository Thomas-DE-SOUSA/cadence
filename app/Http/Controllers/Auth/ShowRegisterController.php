<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use Inertia\Inertia;
use Inertia\Response;

final class ShowRegisterController
{
    public function __invoke(): Response
    {
        return Inertia::render('Auth/Register');
    }
}
