<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/**
 * Reverb channel authorization (Phase 7 isolation hardening): every tenant-prefixed channel must
 * reject a user who is not a member of that tenant — otherwise a second tenant's client could
 * subscribe to another tenant's board/config events. Authorized via /broadcasting/auth (auth:sanctum).
 */
function memberToken(Tenant $tenant, string $email): string
{
    $user = User::create(['name' => $email, 'email' => $email, 'password' => Hash::make('secret123')]);
    $tenant->users()->attach($user->id, ['status' => 'active']);

    return $user->createToken('t', ["tenant:{$tenant->id}"])->plainTextToken;
}

it('rejects a non-member subscribing to another tenant\'s channels', function () {
    // Use the reverb (pusher-compatible) driver so /broadcasting/auth actually evaluates the channel
    // callback — the default `null` driver authorizes everything. Dummy creds: auth fails before signing.
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
    ]);

    $alpha = Tenant::create(['slug' => 'alpha', 'name' => ['en' => 'Alpha']]);
    $beta = Tenant::create(['slug' => 'beta', 'name' => ['en' => 'Beta']]);
    $betaToken = memberToken($beta, 'b@beta.test'); // member of beta only

    foreach (["private-tenant.{$alpha->id}.entity.deal.board", "private-tenant.{$alpha->id}.config"] as $channel) {
        $this->withHeader('Authorization', "Bearer {$betaToken}")
            ->postJson('/broadcasting/auth', ['channel_name' => $channel, 'socket_id' => '123.456'])
            ->assertForbidden();
    }
});
