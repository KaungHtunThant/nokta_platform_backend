<?php

declare(strict_types=1);

use App\DTOs\RecordInput;
use App\Models\EntityType;
use App\Models\Record;
use App\Models\RoleFieldAccess;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Records\RecordWriteService;
use App\Support\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Tenant with a `patient` entity that has a text field + a `file` field. Returns [tenant, patient,
 * user, token]. The user gets the full op catalog (engine test, not authz).
 */
function bootFileTenant(): array
{
    Storage::fake('public');

    $tenant = Tenant::create(['slug' => 'files', 'name' => ['en' => 'Files']]);
    app(TenantManager::class)->set($tenant->id);

    $patient = EntityType::create(['key' => 'patient', 'label' => ['en' => 'Patient'], 'config' => ['title_field' => 'full_name']]);
    $patient->fieldDefinitions()->create(['key' => 'full_name', 'type' => 'text', 'label' => ['en' => 'Name'], 'validation' => ['required' => true], 'storage_strategy' => 'json', 'position' => 0]);
    $patient->fieldDefinitions()->create(['key' => 'chart', 'type' => 'file', 'label' => ['en' => 'Chart'], 'storage_strategy' => 'json', 'position' => 1]);

    $user = User::create(['name' => 'u', 'email' => 'u@files.test', 'password' => Hash::make('secret123')]);
    $tenant->users()->attach($user->id, ['status' => 'active']);
    grantOps($user, $tenant);
    $token = $user->createToken('t', ["tenant:{$tenant->id}"])->plainTextToken;

    return [$tenant, $patient, $user, $token];
}

it('attaches a file to a file field and returns its url, keyed by field', function () {
    [, $patient, , $token] = bootFileTenant();
    $record = app(RecordWriteService::class)->create($patient, new RecordInput(null, null, null, ['full_name' => 'Jane']));

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/records/{$record->id}/files", [
            'field_key' => 'chart',
            'file' => UploadedFile::fake()->create('mri.pdf', 64, 'application/pdf'),
        ])
        ->assertOk()
        ->assertJsonPath('files.chart.0.name', 'mri.pdf')
        ->assertJsonStructure(['files' => ['chart' => [['id', 'name', 'url']]]]);

    expect(Record::find($record->id)->getMedia('chart'))->toHaveCount(1);
});

it('rejects an unknown / non-file field', function () {
    [, $patient, , $token] = bootFileTenant();
    $record = app(RecordWriteService::class)->create($patient, new RecordInput(null, null, null, ['full_name' => 'Jane']));

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/records/{$record->id}/files", [
            'field_key' => 'full_name', // a text field, not a file field
            'file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
        ])
        ->assertStatus(422);
});

it('rejects upload to a field the role may not write (FieldGate)', function () {
    [$tenant, $patient, $user, $token] = bootFileTenant();
    $record = app(RecordWriteService::class)->create($patient, new RecordInput(null, null, null, ['full_name' => 'Jane']));

    // Deny can_update on the chart field for the user's role.
    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
    $roleId = $user->roles()->first()->id;
    $chart = $patient->fieldDefinitions()->where('key', 'chart')->first();
    RoleFieldAccess::create(['tenant_id' => $tenant->id, 'role_id' => $roleId, 'field_definition_id' => $chart->id, 'can_read' => true, 'can_update' => false, 'ui_visible' => true]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/records/{$record->id}/files", [
            'field_key' => 'chart',
            'file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
        ])
        ->assertStatus(422);

    expect(Record::find($record->id)->getMedia('chart'))->toHaveCount(0);
});

it('removes an attached file', function () {
    [, $patient, , $token] = bootFileTenant();
    $record = app(RecordWriteService::class)->create($patient, new RecordInput(null, null, null, ['full_name' => 'Jane']));
    $media = $record->addMedia(UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'))->toMediaCollection('chart', 'public');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/records/{$record->id}/files/{$media->id}")
        ->assertNoContent();

    expect(Record::find($record->id)->getMedia('chart'))->toHaveCount(0);
});
