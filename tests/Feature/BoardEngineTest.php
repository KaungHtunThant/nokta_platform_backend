<?php

declare(strict_types=1);

use App\Events\RecordMoved;
use App\Models\EntityType;
use App\Models\Pipeline;
use App\Models\Record;
use App\Models\Stage;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Records\RecordMoveService;
use App\Support\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/**
 * Boot a tenant with a 'deal' entity type + a 'sales' pipeline whose 'won' stage requires the
 * `budget` field and a comment. Returns [tenant, deal, pipeline, newStage, wonStage].
 *
 * @return array{0: Tenant, 1: EntityType, 2: Pipeline, 3: Stage, 4: Stage}
 */
function bootBoardTenant(string $slug = 'alpha'): array
{
    $tenant = Tenant::create(['slug' => $slug, 'name' => ['en' => ucfirst($slug)]]);
    app(TenantManager::class)->set($tenant->id);

    $deal = EntityType::create(['key' => 'deal', 'label' => ['en' => 'Deal'], 'supports_pipeline' => true]);
    $deal->fieldDefinitions()->create(['key' => 'title', 'type' => 'text', 'label' => ['en' => 'Title'], 'validation' => ['required' => true], 'position' => 0]);
    $deal->fieldDefinitions()->create(['key' => 'budget', 'type' => 'money', 'label' => ['en' => 'Budget'], 'position' => 1]);

    $pipeline = Pipeline::create(['entity_type_id' => $deal->id, 'key' => 'sales', 'label' => ['en' => 'Sales'], 'position' => 0]);
    $new = $pipeline->stages()->create(['key' => 'new', 'label' => ['en' => 'New'], 'position' => 0, 'is_initial' => true]);
    $won = $pipeline->stages()->create(['key' => 'won', 'label' => ['en' => 'Won'], 'position' => 1, 'is_won' => true]);
    $won->rules()->create(['rule' => 'require_fields', 'value' => ['budget']]);
    $won->rules()->create(['rule' => 'require_comment', 'value' => true]);

    return [$tenant, $deal, $pipeline, $new, $won];
}

function tokenForTenant(Tenant $tenant, string $email = 'board@alpha.test'): string
{
    $user = User::create(['name' => $email, 'email' => $email, 'password' => Hash::make('secret123')]);
    $tenant->users()->attach($user->id, ['status' => 'active']);

    return $user->createToken('t', ["tenant:{$tenant->id}"])->plainTextToken;
}

function dealRecord(EntityType $deal, Pipeline $pipeline, Stage $stage, array $data): Record
{
    return Record::create([
        'entity_type_id' => $deal->id,
        'pipeline_id' => $pipeline->id,
        'stage_id' => $stage->id,
        'position' => 0,
        'data' => $data,
    ]);
}

it('blocks a move that fails require_fields, with a clear reason', function () {
    [, $deal, $pipeline, $new, $won] = bootBoardTenant();
    $record = dealRecord($deal, $pipeline, $new, ['title' => 'A']); // no budget

    try {
        app(RecordMoveService::class)->move($record, $won, 'a comment');
        $this->fail('Expected the move to be rejected.');
    } catch (ValidationException $e) {
        expect($e->errors()['stage'][0])->toContain('budget');
    }

    expect($record->fresh()->stage_id)->toBe($new->id);
});

it('blocks a move that fails require_comment', function () {
    [, $deal, $pipeline, $new, $won] = bootBoardTenant();
    $record = dealRecord($deal, $pipeline, $new, ['title' => 'A', 'budget' => 1000]);

    try {
        app(RecordMoveService::class)->move($record, $won, null); // budget ok, comment missing
        $this->fail('Expected the move to be rejected.');
    } catch (ValidationException $e) {
        expect($e->errors()['stage'][0])->toContain('comment');
    }
});

it('blocks backward moves when allow_backward is false', function () {
    [, $deal, $pipeline, $new, $won] = bootBoardTenant();
    $new->rules()->create(['rule' => 'allow_backward', 'value' => false]);
    $record = dealRecord($deal, $pipeline, $won, ['title' => 'A', 'budget' => 1000]);

    try {
        app(RecordMoveService::class)->move($record, $new, null); // won(1) -> new(0) is backward
        $this->fail('Expected the move to be rejected.');
    } catch (ValidationException $e) {
        expect($e->errors()['stage'][0])->toContain('backward');
    }
});

it('allows and persists a move when all rules pass, and dispatches RecordMoved', function () {
    Event::fake([RecordMoved::class]);
    [, $deal, $pipeline, $new, $won] = bootBoardTenant();
    $record = dealRecord($deal, $pipeline, $new, ['title' => 'A', 'budget' => 1000]);

    $moved = app(RecordMoveService::class)->move($record, $won, 'closing the deal');

    expect($moved->stage_id)->toBe($won->id);
    Event::assertDispatched(RecordMoved::class, fn (RecordMoved $e): bool => $e->toStageId === $won->id && $e->fromStageId === $new->id);
});

it('broadcasts the move only on the tenant-prefixed board channel', function () {
    [$tenant, $deal, $pipeline, $new, $won] = bootBoardTenant();
    $record = dealRecord($deal, $pipeline, $new, ['title' => 'A', 'budget' => 1000])->load('entityType');

    $channels = (new RecordMoved($record, $new->id, $won->id))->broadcastOn();

    expect($channels)->toHaveCount(1)
        ->and($channels[0]->name)->toBe("private-tenant.{$tenant->id}.entity.deal.board");
});

it('authorizes the board channel only for members of that tenant (isolation)', function () {
    [$alpha] = bootBoardTenant('alpha');
    $beta = Tenant::create(['slug' => 'beta', 'name' => ['en' => 'Beta']]);

    $member = User::create(['name' => 'm', 'email' => 'm@alpha.test', 'password' => Hash::make('x')]);
    $alpha->users()->attach($member->id, ['status' => 'active']);

    expect($member->belongsToTenant($alpha->id))->toBeTrue()
        ->and($member->belongsToTenant($beta->id))->toBeFalse();
});

it('rejects a disallowed move over HTTP with 422 validation errors', function () {
    [$tenant, $deal, $pipeline, $new, $won] = bootBoardTenant();
    $token = tokenForTenant($tenant);
    $record = dealRecord($deal, $pipeline, $new, ['title' => 'A']); // missing budget

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/records/{$record->id}/move", ['stage_id' => $won->id, 'comment' => 'x'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['stage']);

    expect($record->fresh()->stage_id)->toBe($new->id);
});

it('moves a record over HTTP when rules pass', function () {
    [$tenant, $deal, $pipeline, $new, $won] = bootBoardTenant();
    $token = tokenForTenant($tenant);
    $record = dealRecord($deal, $pipeline, $new, ['title' => 'A', 'budget' => 1000]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/records/{$record->id}/move", ['stage_id' => $won->id, 'comment' => 'done'])
        ->assertOk()
        ->assertJsonPath('stage_id', $won->id);
});

it('returns board columns grouped by stage', function () {
    [$tenant, $deal, $pipeline, $new, $won] = bootBoardTenant();
    $token = tokenForTenant($tenant);
    dealRecord($deal, $pipeline, $new, ['title' => 'In new']);
    dealRecord($deal, $pipeline, $won, ['title' => 'In won', 'budget' => 5]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/entity-types/deal/board')
        ->assertOk()
        ->assertJsonPath('pipeline.key', 'sales')
        ->assertJsonCount(2, 'columns')
        ->assertJsonPath('columns.0.stage.key', 'new')
        ->assertJsonPath('columns.0.meta.total', 1)
        ->assertJsonPath('columns.0.records.0.data.title', 'In new')
        ->assertJsonPath('columns.1.stage.key', 'won')
        ->assertJsonPath('columns.1.meta.total', 1);
});

it('reorders stages via API and the board reflects the new order with no code change', function () {
    [$tenant, $deal, $pipeline, $new, $won] = bootBoardTenant();
    $token = tokenForTenant($tenant);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson("/api/pipelines/{$pipeline->id}/stages/reorder", ['order' => [$won->id, $new->id]])
        ->assertOk();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/entity-types/deal/board')
        ->assertOk()
        ->assertJsonPath('columns.0.stage.key', 'won')
        ->assertJsonPath('columns.1.stage.key', 'new');
});
