<?php

declare(strict_types=1);

use App\DTOs\RecordInput;
use App\Models\EntityType;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Records\RecordWriteService;
use App\Support\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('filters and sorts the records list endpoint via the DSL query params', function () {
    $tenant = Tenant::create(['slug' => 'alpha', 'name' => ['en' => 'Alpha']]);
    app(TenantManager::class)->set($tenant->id);
    $deal = EntityType::create(['key' => 'deal', 'label' => ['en' => 'Deal']]);
    $deal->fieldDefinitions()->create(['key' => 'budget', 'type' => 'money', 'label' => ['en' => 'Budget'], 'position' => 0, 'is_filterable' => true, 'is_sortable' => true]);

    $writer = app(RecordWriteService::class);
    $writer->create($deal, new RecordInput(null, null, null, ['budget' => 100]));
    $writer->create($deal, new RecordInput(null, null, null, ['budget' => 300]));
    $writer->create($deal, new RecordInput(null, null, null, ['budget' => 200]));

    $user = User::create(['name' => 'u', 'email' => 'u@alpha.test', 'password' => Hash::make('secret123')]);
    $tenant->users()->attach($user->id, ['status' => 'active']);
    grantOps($user, $tenant, ['record.read']);
    $token = $user->createToken('t', ["tenant:{$tenant->id}"])->plainTextToken;

    $filters = json_encode([['field' => 'budget', 'op' => 'gte', 'value' => 200]]);

    $res = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/entity-types/deal/records?filters='.urlencode((string) $filters).'&sort=budget&dir=asc')
        ->assertOk();

    $budgets = collect($res->json('data'))->pluck('data.budget')->all();
    expect($budgets)->toBe([200, 300]); // filtered (>=200) and sorted ascending
});
