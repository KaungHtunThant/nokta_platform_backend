<?php

declare(strict_types=1);

use App\DTOs\RecordInput;
use App\Enums\RolesAndPermissions\OperationPermission;
use App\Models\EntityType;
use App\Models\Record;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Records\RecordWriteService;
use App\Support\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/** A tenant + deal entity + an authenticated user; returns [tenant, deal, token]. */
function bootLockTenant(?array $ops = null): array
{
    $tenant = Tenant::create(['slug' => 'lock', 'name' => ['en' => 'Lock']]);
    app(TenantManager::class)->set($tenant->id);

    $deal = EntityType::create(['key' => 'deal', 'label' => ['en' => 'Deal'], 'supports_pipeline' => true, 'config' => ['title_field' => 'title']]);
    $deal->fieldDefinitions()->create(['key' => 'title', 'type' => 'text', 'label' => ['en' => 'Title'], 'validation' => ['required' => true], 'storage_strategy' => 'json', 'position' => 0]);

    $user = User::create(['name' => 'u', 'email' => 'u@lock.test', 'password' => Hash::make('secret123')]);
    $tenant->users()->attach($user->id, ['status' => 'active']);
    grantOps($user, $tenant, $ops);
    $token = $user->createToken('t', ["tenant:{$tenant->id}"])->plainTextToken;

    return [$tenant, $deal, $token];
}

it('rejects an update to a locked record at the service layer', function () {
    [, $deal] = bootLockTenant();
    $writer = app(RecordWriteService::class);
    $record = $writer->create($deal, new RecordInput(null, null, 'open', ['title' => 'Signed note']));
    $record->update(['is_locked' => true]);

    expect(fn () => $writer->update($deal, $record->fresh(), new RecordInput(null, null, null, ['title' => 'Tampered'])))
        ->toThrow(ValidationException::class);
});

it('blocks editing a locked record over HTTP even with record.update', function () {
    [, $deal, $token] = bootLockTenant(); // full op catalog incl. record.update + record.lock
    $record = app(RecordWriteService::class)->create($deal, new RecordInput(null, null, 'open', ['title' => 'Note']));

    // Lock it (admin has record.lock).
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/records/{$record->id}/lock")
        ->assertOk()
        ->assertJsonPath('is_locked', true);

    // Now an update is refused by the policy (403) despite holding record.update.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson("/api/records/{$record->id}", ['data' => ['title' => 'Tampered']])
        ->assertForbidden();

    expect(Record::find($record->id)->data['title'])->toBe('Note');
});

it('allows editing again after unlock', function () {
    [, $deal, $token] = bootLockTenant();
    $record = app(RecordWriteService::class)->create($deal, new RecordInput(null, null, 'open', ['title' => 'Note']));

    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/records/{$record->id}/lock")->assertOk();
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/records/{$record->id}/unlock")->assertOk()->assertJsonPath('is_locked', false);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson("/api/records/{$record->id}", ['data' => ['title' => 'Edited']])
        ->assertOk()
        ->assertJsonPath('data.title', 'Edited');
});

it('forbids locking without the record.lock permission', function () {
    // Grant everything EXCEPT lock/unlock.
    $ops = array_values(array_filter(
        array_map(fn (OperationPermission $c): string => $c->value, OperationPermission::cases()),
        fn (string $v): bool => ! in_array($v, ['record.lock', 'record.unlock'], true),
    ));
    [, $deal, $token] = bootLockTenant($ops);
    $record = app(RecordWriteService::class)->create($deal, new RecordInput(null, null, 'open', ['title' => 'Note']));

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/records/{$record->id}/lock")
        ->assertForbidden();
});
