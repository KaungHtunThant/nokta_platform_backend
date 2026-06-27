<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\RecordRepositoryInterface;
use App\Repositories\Eloquent\EloquentRecordRepository;
use App\Support\Tenancy\TenantManager;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Single tenant context per request — middleware sets it, models read it.
        $this->app->singleton(TenantManager::class);

        // DIP: depend on the contract; swap implementations freely.
        $this->app->bind(RecordRepositoryInterface::class, EloquentRecordRepository::class);
    }

    public function boot(): void
    {
        // Flat resources (no "data" envelope): consistent contract + the record's custom-field
        // bag is itself named "data", which would otherwise collide with the wrapper.
        JsonResource::withoutWrapping();
    }
}
