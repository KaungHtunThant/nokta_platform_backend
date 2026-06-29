<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Authorization\AbilitiesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Thin controller: returns the resolved op/ui permissions + stage/field matrices for the active
 * tenant. Any authenticated member may read their own abilities (no extra op gate).
 */
final class AbilitiesController extends Controller
{
    public function __construct(private readonly AbilitiesService $abilities) {}

    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json($this->abilities->forUser($user));
    }
}
