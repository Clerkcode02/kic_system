<?php

declare(strict_types=1);

arch('controllers do not access the database directly')
    ->expect('App\Http\Controllers')
    ->not->toUse([
        'Illuminate\Support\Facades\DB',
        'Illuminate\Database\Query\Builder',
        'Illuminate\Database\Eloquent\Builder',
    ]);
