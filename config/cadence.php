<?php

declare(strict_types=1);

return [
    /*
     | The tenant every request runs as until authentication lands. Server-side
     | only — never influenced by the request payload.
     */
    'default_tenant' => env('CADENCE_DEFAULT_TENANT', 'tenant-thomas'),
];
