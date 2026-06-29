<?php

declare(strict_types=1);

use App\Models\EntityType;
use App\Models\Pipeline;
use App\Models\Record;
use App\Models\RoleStageAccess;
use App\Models\Stage;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/**
 * Phase 3 — per-stage move authorization (role_stage_access), wired through StageTransitionPolicy.
 * Stages are open until governed; once a stage has any access row, a move into it requires a grant.
 *
 * @return array{0: Tenant, 1: EntityType, 2: Pipeline, 3: Stage, 4: Stage}
 */
function bootStageAccessTenant(): array
{
    $tenant = Tenant::create(['slug' => 'alpha', 'name' => ['en' => 'Alpha']]);
    app(TenantManager::class)->set($tenant->id);

    $deal = EntityType::create(['key' => 'deal', 'label' => ['en' => 'Deal'], 'supports_pipeline' => true]);
    $pipeline = Pipeline::create(['entity_type_id' => $deal->id, 'key' => 'sales', 'label' => ['en' => 'Sales'], 'position' => 0]);
    $new = $pipeline->stages()->create(['key' => 'new', 'label' => ['en' => 'New'], 'position' => 0, 'is_initial' => true]);
    $won = $pipeline->stages()->create(['key' => 'won', 'label' => ['en' => 'Won'], 'position' => 1, 'is_won' => true]);

    return [$tenant, $deal, $pipeline, $new, $won];
}

function stageActor(Tenant $tenant, array $ops = ['record.read', 'stage.move']): array
{
    $user = User::create(['name' => 'u', 'email' => 'u@alpha.test', 'password' => Hash::make('secret123')]);
    $tenant->users()->attach($user->id, ['status' => 'active']);
    $role = grantOps($user, $tenant, $ops);
    $token = $user->createToken('t', ["tenant:{$tenant->id}"])->plainTextToken;

    return [$user, $role, $token];
}

it('blocks a move into a governed stage when the role lacks can_move_to', function () {
    [$tenant, $deal, $pipeline, $new, $won] = bootStageAccessTenant();
    [, $role, $token] = stageActor($tenant);

    // Govern 'won' but grant the role no move-to.
    setPermissionsTeamId($tenant->id);
    RoleStageAccess::create(['role_id' => $role->id, 'stage_id' => $won->id, 'can_move_from' => true, 'can_move_to' => false]);

    $record = Record::create(['entity_type_id' => $deal->id, 'pipeline_id' => $pipeline->id, 'stage_id' => $new->id, 'position' => 0, 'data' => ['x' => 1]]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/records/{$record->id}/move", ['stage_id' => $won->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['stage']);

    expect($record->fresh()->stage_id)->toBe($new->id);
});

it('allows a move into a governed stage when the role has can_move_to', function () {
    [$tenant, $deal, $pipeline, $new, $won] = bootStageAccessTenant();
    [, $role, $token] = stageActor($tenant);

    setPermissionsTeamId($tenant->id);
    RoleStageAccess::create(['role_id' => $role->id, 'stage_id' => $won->id, 'can_move_from' => true, 'can_move_to' => true]);

    $record = Record::create(['entity_type_id' => $deal->id, 'pipeline_id' => $pipeline->id, 'stage_id' => $new->id, 'position' => 0, 'data' => ['x' => 1]]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/records/{$record->id}/move", ['stage_id' => $won->id])
        ->assertOk()
        ->assertJsonPath('stage_id', $won->id);
});

it('leaves ungoverned stages open (no access rows = any move allowed)', function () {
    [$tenant, $deal, $pipeline, $new, $won] = bootStageAccessTenant();
    [, , $token] = stageActor($tenant);

    $record = Record::create(['entity_type_id' => $deal->id, 'pipeline_id' => $pipeline->id, 'stage_id' => $new->id, 'position' => 0, 'data' => ['x' => 1]]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/records/{$record->id}/move", ['stage_id' => $won->id])
        ->assertOk();
});
