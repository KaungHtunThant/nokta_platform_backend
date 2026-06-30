<?php

declare(strict_types=1);

use App\DTOs\RecordInput;
use App\Models\EntityType;
use App\Models\Record;
use App\Models\RecordLink;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Records\RecordLinkService;
use App\Services\Records\RecordWriteService;
use App\Support\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function relationToken(Tenant $tenant, string $email = 'rel@alpha.test'): string
{
    $user = User::create(['name' => $email, 'email' => $email, 'password' => Hash::make('secret123')]);
    $tenant->users()->attach($user->id, ['status' => 'active']);
    grantOps($user, $tenant);

    return $user->createToken('t', ["tenant:{$tenant->id}"])->plainTextToken;
}

/**
 * Boot a tenant with a `contact` entity type and a `deal` entity type whose `contact` field is a
 * canonical relation → contact. Proves Phase 6 generalization: relations need no new tables.
 *
 * @return array{0: Tenant, 1: EntityType, 2: EntityType}
 */
function bootRelationTenant(string $slug = 'alpha'): array
{
    $tenant = Tenant::create(['slug' => $slug, 'name' => ['en' => ucfirst($slug)]]);
    app(TenantManager::class)->set($tenant->id);

    $contact = EntityType::create(['key' => 'contact', 'label' => ['en' => 'Contact'], 'supports_pipeline' => false, 'config' => ['title_field' => 'name']]);
    $contact->fieldDefinitions()->create(['key' => 'name', 'type' => 'text', 'label' => ['en' => 'Name'], 'validation' => ['required' => true], 'storage_strategy' => 'json', 'position' => 0]);

    $deal = EntityType::create(['key' => 'deal', 'label' => ['en' => 'Deal'], 'supports_pipeline' => true, 'config' => ['title_field' => 'title']]);
    $deal->fieldDefinitions()->create(['key' => 'title', 'type' => 'text', 'label' => ['en' => 'Title'], 'validation' => ['required' => true], 'storage_strategy' => 'json', 'position' => 0]);
    $deal->fieldDefinitions()->create([
        'key' => 'contact', 'type' => 'relation', 'label' => ['en' => 'Contact'], 'storage_strategy' => 'json', 'position' => 1,
        'ui' => ['target_entity_type' => 'contact', 'canonical_column' => 'contact_id'],
    ]);

    return [$tenant, $deal, $contact];
}

it('links a deal to a contact: mirrors record_links and sets the canonical contact_id', function () {
    [, $deal, $contact] = bootRelationTenant();
    $writer = app(RecordWriteService::class);

    $c = $writer->create($contact, new RecordInput(null, null, null, ['name' => 'Jane Doe']));
    $d = $writer->create($deal, new RecordInput(null, null, null, ['title' => 'Knee surgery', 'contact' => $c->id]));

    expect($d->contact_id)->toBe($c->id)
        ->and($d->data['contact'])->toBe($c->id);

    $link = RecordLink::query()->where('from_record_id', $d->id)->where('relation_key', 'contact')->first();
    expect($link)->not->toBeNull()
        ->and($link->to_record_id)->toBe($c->id);
});

it('re-pointing a relation prunes the stale link and updates contact_id', function () {
    [, $deal, $contact] = bootRelationTenant();
    $writer = app(RecordWriteService::class);

    $c1 = $writer->create($contact, new RecordInput(null, null, null, ['name' => 'First']));
    $c2 = $writer->create($contact, new RecordInput(null, null, null, ['name' => 'Second']));
    $d = $writer->create($deal, new RecordInput(null, null, null, ['title' => 'Deal', 'contact' => $c1->id]));

    $d = $writer->update($deal, $d, new RecordInput(null, null, null, ['contact' => $c2->id]));

    expect($d->contact_id)->toBe($c2->id);
    expect(RecordLink::query()->where('from_record_id', $d->id)->where('relation_key', 'contact')->count())->toBe(1);
    expect(RecordLink::query()->where('from_record_id', $d->id)->where('to_record_id', $c2->id)->exists())->toBeTrue();
    expect(RecordLink::query()->where('from_record_id', $d->id)->where('to_record_id', $c1->id)->exists())->toBeFalse();
});

it('rejects linking to a record in another tenant', function () {
    [$alpha, , $contactA] = bootRelationTenant('alpha');
    $writer = app(RecordWriteService::class);
    $foreign = $writer->create($contactA, new RecordInput(null, null, null, ['name' => 'Alpha contact']));

    // Switch to beta and try to link a beta deal to alpha's contact id.
    [, $dealB] = bootRelationTenant('beta');

    expect(fn () => $writer->create($dealB, new RecordInput(null, null, null, ['title' => 'Cross', 'contact' => $foreign->id])))
        ->toThrow(ValidationException::class);

    expect(RecordLink::query()->where('to_record_id', $foreign->id)->count())->toBe(0);
});

it('rejects linking to a record of the wrong entity type', function () {
    [, $deal, $contact] = bootRelationTenant();
    $writer = app(RecordWriteService::class);

    // A deal is not a contact — pointing the contact relation at a deal must fail.
    $otherDeal = $writer->create($deal, new RecordInput(null, null, null, ['title' => 'Other']));
    $c = $writer->create($contact, new RecordInput(null, null, null, ['name' => 'Valid']));
    $d = $writer->create($deal, new RecordInput(null, null, null, ['title' => 'Deal', 'contact' => $c->id]));

    expect(fn () => $writer->update($deal, $d, new RecordInput(null, null, null, ['contact' => $otherDeal->id])))
        ->toThrow(ValidationException::class);
});

it('surfaces the relation from both records (bidirectional traversal)', function () {
    [, $deal, $contact] = bootRelationTenant();
    $writer = app(RecordWriteService::class);
    $links = app(RecordLinkService::class);

    $c = $writer->create($contact, new RecordInput(null, null, null, ['name' => 'Jane']));
    $d = $writer->create($deal, new RecordInput(null, null, null, ['title' => 'Deal', 'contact' => $c->id]));

    // From the deal: its contact. From the contact: its deals.
    expect($links->relatedTo($d->refresh())->pluck('id')->all())->toBe([$c->id]);
    expect($links->relatedTo(Record::find($c->id))->pluck('id')->all())->toBe([$d->id]);
});

it('searches records for the relation picker by the entity title field', function () {
    [$tenant, , $contact] = bootRelationTenant();
    $token = relationToken($tenant);
    $writer = app(RecordWriteService::class);
    $writer->create($contact, new RecordInput(null, null, null, ['name' => 'Jane Doe']));
    $writer->create($contact, new RecordInput(null, null, null, ['name' => 'John Smith']));

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/entity-types/contact/records-picker?q=jane')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.label', 'Jane Doe')
        ->assertJsonPath('0.entity_type', 'contact');
});

it('lists related records over HTTP from the deal side', function () {
    [$tenant, $deal, $contact] = bootRelationTenant();
    $token = relationToken($tenant);
    $writer = app(RecordWriteService::class);
    $c = $writer->create($contact, new RecordInput(null, null, null, ['name' => 'Jane Doe']));
    $d = $writer->create($deal, new RecordInput(null, null, null, ['title' => 'Deal', 'contact' => $c->id]));

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/records/{$d->id}/links")
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $c->id)
        ->assertJsonPath('0.label', 'Jane Doe')
        ->assertJsonPath('0.entity_type', 'contact');
});

it('creates and removes a link over HTTP', function () {
    [$tenant, $deal, $contact] = bootRelationTenant();
    $token = relationToken($tenant);
    $writer = app(RecordWriteService::class);
    $c = $writer->create($contact, new RecordInput(null, null, null, ['name' => 'Jane']));
    $d = $writer->create($deal, new RecordInput(null, null, null, ['title' => 'Deal']));

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/records/{$d->id}/links", ['to_record_id' => $c->id, 'relation_key' => 'contact'])
        ->assertCreated();

    expect(RecordLink::query()->where('from_record_id', $d->id)->where('to_record_id', $c->id)->exists())->toBeTrue();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/records/{$d->id}/links", ['to_record_id' => $c->id, 'relation_key' => 'contact'])
        ->assertNoContent();

    expect(RecordLink::query()->where('from_record_id', $d->id)->where('to_record_id', $c->id)->exists())->toBeFalse();
});
