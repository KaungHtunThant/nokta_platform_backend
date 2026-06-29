<?php

declare(strict_types=1);

use App\DTOs\RecordInput;
use App\Models\EntityType;
use App\Models\Layout;
use App\Models\Record;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Records\RecordWriteService;
use App\Support\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/** Create a tenant with a 'deal' entity type (title required, note, priority) and activate context. */
function bootDealTenant(string $slug = 'alpha'): array
{
    $tenant = Tenant::create(['slug' => $slug, 'name' => ['en' => ucfirst($slug)]]);
    app(TenantManager::class)->set($tenant->id);

    $deal = EntityType::create(['key' => 'deal', 'label' => ['en' => 'Deal'], 'supports_pipeline' => true]);
    $deal->fieldDefinitions()->create(['key' => 'title', 'type' => 'text', 'label' => ['en' => 'Title'], 'validation' => ['required' => true], 'position' => 0]);
    $deal->fieldDefinitions()->create(['key' => 'note', 'type' => 'textarea', 'label' => ['en' => 'Note'], 'position' => 1]);
    $deal->fieldDefinitions()->create(['key' => 'priority', 'type' => 'select', 'label' => ['en' => 'Priority'], 'position' => 2]);

    return [$tenant, $deal];
}

function bootTokenFor(Tenant $tenant, string $email = 'u@alpha.test'): string
{
    $user = User::create(['name' => $email, 'email' => $email, 'password' => Hash::make('secret123')]);
    $tenant->users()->attach($user->id, ['status' => 'active']);
    grantOps($user, $tenant); // full op catalog — these tests exercise the engine, not authz

    return $user->createToken('t', ["tenant:{$tenant->id}"])->plainTextToken;
}

it('rejects a record missing a required field', function () {
    [, $deal] = bootDealTenant();

    expect(fn () => app(RecordWriteService::class)->create($deal, new RecordInput(null, null, null, ['note' => 'x'])))
        ->toThrow(ValidationException::class);
});

it('stores valid custom data and filters unknown keys (JSON round-trip)', function () {
    [, $deal] = bootDealTenant();

    $record = app(RecordWriteService::class)->create(
        $deal,
        new RecordInput(null, null, 'open', ['title' => 'Knee surgery', 'priority' => 'high', 'unknown' => 'drop me'])
    );

    expect($record->data)->toBe(['title' => 'Knee surgery', 'priority' => 'high'])
        ->and($record->status)->toBe('open')
        ->and($record->data)->not->toHaveKey('unknown');
});

it('scopes created records to the active tenant', function () {
    [$alpha, $dealA] = bootDealTenant('alpha');
    app(RecordWriteService::class)->create($dealA, new RecordInput(null, null, null, ['title' => 'A deal']));

    // Switch to another tenant — alpha's record must be invisible.
    $beta = Tenant::create(['slug' => 'beta', 'name' => ['en' => 'Beta']]);
    app(TenantManager::class)->set($beta->id);

    expect(Record::count())->toBe(0);

    app(TenantManager::class)->set($alpha->id);
    expect(Record::count())->toBe(1);
});

it('creates and reads a record over HTTP', function () {
    [$tenant, $deal] = bootDealTenant();
    $token = bootTokenFor($tenant);

    $created = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/entity-types/deal/records', ['status' => 'open', 'data' => ['title' => 'Hip replacement', 'priority' => 'medium']])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Hip replacement')
        ->json('id');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/records/{$created}")
        ->assertOk()
        ->assertJsonPath('data.priority', 'medium')
        ->assertJsonPath('entity_type', 'deal');
});

it('exposes the entity schema and reflects a newly added field with no code change', function () {
    [$tenant] = bootDealTenant();
    $token = bootTokenFor($tenant);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/entity-types/deal/schema')
        ->assertOk()
        ->assertJsonPath('key', 'deal')
        ->assertJsonCount(3, 'fields');

    // Add a field via the API, then the schema reflects it.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/entity-types/deal/fields', ['key' => 'budget', 'type' => 'money', 'label' => ['en' => 'Budget'], 'position' => 3])
        ->assertCreated();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/entity-types/deal/schema')
        ->assertOk()
        ->assertJsonCount(4, 'fields');
});

it('serves a resolved layout for a surface', function () {
    [$tenant, $deal] = bootDealTenant();
    $token = bootTokenFor($tenant);

    Layout::create([
        'entity_type_id' => $deal->id, 'surface' => 'form', 'key' => 'deal.form', 'is_active' => true,
        'schema' => ['type' => 'section', 'id' => 'root', 'children' => [['type' => 'field', 'id' => 'f-title', 'binding' => ['field' => 'title']]]],
    ]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/layouts/form/deal.form')
        ->assertOk()
        ->assertJsonPath('surface', 'form')
        ->assertJsonPath('schema.type', 'section');
});
