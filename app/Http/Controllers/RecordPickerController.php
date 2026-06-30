<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\EntityType;
use App\Services\Records\RecordPickerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Thin controller backing the relation-field picker: search records of a target entity type and return
 * a light {id, label} list. Gated by op:record.read (you can pick what you can read).
 */
final class RecordPickerController extends Controller
{
    public function __construct(private readonly RecordPickerService $picker) {}

    public function index(Request $request, string $entityTypeKey): JsonResponse
    {
        $type = EntityType::query()->where('key', $entityTypeKey)->firstOrFail();

        $query = $request->query('q');
        $results = $this->picker->search($type, is_string($query) ? $query : null);

        // Bare array — the app disables JsonResource wrapping (AppServiceProvider), so list endpoints
        // return top-level arrays for a consistent client contract.
        return response()->json($results);
    }
}
