<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\RecordRepositoryInterface;
use App\Contracts\StageRuleEvaluator;
use App\Models\Record;
use App\Policies\RecordPolicy;
use App\Repositories\Eloquent\EloquentRecordRepository;
use App\Rules\Stage\AllowBackwardRule;
use App\Rules\Stage\CooldownRule;
use App\Rules\Stage\RequireCommentRule;
use App\Rules\Stage\RequireFieldsRule;
use App\Services\Stages\StageTransitionPolicy;
use App\Support\Tenancy\TenantManager;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Single tenant context per request — middleware sets it, models read it.
        $this->app->singleton(TenantManager::class);

        // DIP: depend on the contract; swap implementations freely.
        $this->app->bind(RecordRepositoryInterface::class, EloquentRecordRepository::class);

        // Stage-transition rule evaluators (Strategy). New rule types: add the class + tag it here.
        $this->app->tag([
            RequireFieldsRule::class,
            RequireCommentRule::class,
            AllowBackwardRule::class,
            CooldownRule::class,
        ], 'stage.rules');

        $this->app->when(StageTransitionPolicy::class)
            ->needs(StageRuleEvaluator::class)
            ->giveTagged('stage.rules');
    }

    public function boot(): void
    {
        // Flat resources (no "data" envelope): consistent contract + the record's custom-field
        // bag is itself named "data", which would otherwise collide with the wrapper.
        JsonResource::withoutWrapping();

        // Object-level record authorization (op guard). Explicit registration — the class lives in
        // App\Models, and we keep policy wiring discoverable rather than relying on auto-discovery.
        Gate::policy(Record::class, RecordPolicy::class);
    }
}
