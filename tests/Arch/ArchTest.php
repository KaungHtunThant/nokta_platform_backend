<?php

declare(strict_types=1);

/*
 | Architecture tests — mechanically ENFORCE the backend conventions
 | (MVC + SOLID, see TECHNICAL-STRUCTURE Part A). These run in CI on every push.
 | Marked group "arch" so `composer test:arch` can run them in isolation.
 */

arch('strict types everywhere')
    ->expect('App')
    ->toUseStrictTypes()
    ->group('arch');

arch('controllers stay thin — no direct DB / Eloquent access')
    ->expect('App\Http\Controllers')
    ->not->toUse([
        'Illuminate\Support\Facades\DB',
        'Illuminate\Database\Eloquent\Builder',
    ])
    ->group('arch');

arch('models contain no business logic dependencies')
    ->expect('App\Models')
    ->not->toUse(['App\Services', 'App\Actions'])
    ->group('arch');

arch('actions and services depend on contracts, not concrete repositories')
    ->expect('App\Actions')
    ->not->toUse('App\Repositories')
    ->group('arch');

arch('contracts are interfaces')
    ->expect('App\Contracts')
    ->toBeInterfaces()
    ->group('arch');

arch('only cache/read-model services touch the cache (not controllers/actions)')
    ->expect('Illuminate\Support\Facades\Cache')
    ->not->toBeUsedIn(['App\Http\Controllers', 'App\Actions'])
    ->group('arch');

arch('enums for the permission catalog')
    ->expect('App\Enums')
    ->toBeEnums()
    ->group('arch');
