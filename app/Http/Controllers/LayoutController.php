<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\LayoutResource;
use App\Models\Layout;

final class LayoutController extends Controller
{
    /**
     * Return the resolved active layout for a surface+key in the current tenant.
     * (Role/user override precedence + versioning arrive in Phases 3/5.)
     */
    public function show(string $surface, string $key): LayoutResource
    {
        $layout = Layout::query()
            ->where('surface', $surface)
            ->where('key', $key)
            ->where('is_active', true)
            ->firstOrFail();

        return LayoutResource::make($layout);
    }
}
