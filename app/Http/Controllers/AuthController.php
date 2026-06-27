<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Tenancy\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 0 auth. Issues a Sanctum token SCOPED to one tenant (ability "tenant:{id}").
 * Thin controller: validation + a single operation + JSON response.
 */
final class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'tenant_id' => ['required', 'integer'],
        ]);

        /** @var User|null $user */
        $user = User::query()->where('email', $data['email'])->first();

        if ($user === null || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'Invalid credentials.']);
        }

        $tenantId = (int) $data['tenant_id'];

        if (! $user->belongsToTenant($tenantId)) {
            abort(Response::HTTP_FORBIDDEN, 'You are not a member of this tenant.');
        }

        $token = $user->createToken("tenant-{$tenantId}", ["tenant:{$tenantId}"])->plainTextToken;

        return response()->json([
            'token' => $token,
            'tenant_id' => $tenantId,
            'user' => ['id' => $user->id, 'email' => $user->email],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tenantId = app(TenantManager::class)->id();

        return response()->json([
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
            'tenant_id' => $tenantId,
        ]);
    }
}
