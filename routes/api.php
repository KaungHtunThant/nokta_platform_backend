<?php

declare(strict_types=1);

use App\Http\Controllers\AbilitiesController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\FieldDefinitionController;
use App\Http\Controllers\LayoutController;
use App\Http\Controllers\PipelineController;
use App\Http\Controllers\RecordController;
use App\Http\Controllers\RecordLinkController;
use App\Http\Controllers\RecordMoveController;
use App\Http\Controllers\RecordPickerController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SchemaController;
use App\Http\Controllers\StageController;
use Illuminate\Support\Facades\Route;

/*
 | API routes. Public auth + a tenant-scoped authenticated group
 | (auth:sanctum -> resolve.tenant sets tenant context & spatie team).
 */

Route::get('/health', fn () => ['status' => 'ok']);

Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum', 'resolve.tenant'])->group(function (): void {
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/me/abilities', [AbilitiesController::class, 'show']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // --- Phase 3: tenant-scoped role management (op vs ui guards) ---
    Route::get('/roles', [RoleController::class, 'index'])->middleware('op:manage.roles');
    Route::post('/roles', [RoleController::class, 'store'])->middleware('op:manage.roles');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->middleware('op:manage.roles');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->middleware('op:manage.roles');

    // --- Phase 1: configuration engine on one entity ---
    // Schema/layout reads require record.read (you can render what you can read).
    Route::get('/entity-types/{entityTypeKey}/schema', [SchemaController::class, 'show'])->middleware('op:record.read');
    Route::post('/entity-types/{entityTypeKey}/fields', [FieldDefinitionController::class, 'store'])->middleware('op:manage.fields');

    // Records — coarse op gate on collection routes; object-level RecordPolicy on bound routes.
    Route::get('/entity-types/{entityTypeKey}/records', [RecordController::class, 'index'])->middleware('op:record.read');
    Route::post('/entity-types/{entityTypeKey}/records', [RecordController::class, 'store'])->middleware('op:record.create');
    // Phase 6: relation-field record picker (light {id,label} list of a target entity type).
    Route::get('/entity-types/{entityTypeKey}/records-picker', [RecordPickerController::class, 'index'])->middleware('op:record.read');
    Route::get('/records/{record}', [RecordController::class, 'show'])->middleware('can:view,record');
    Route::put('/records/{record}', [RecordController::class, 'update'])->middleware('can:update,record');
    Route::delete('/records/{record}', [RecordController::class, 'destroy'])->middleware('can:delete,record');

    // Phase 6: cross-record relations — list/create/remove links (both directions).
    Route::get('/records/{record}/links', [RecordLinkController::class, 'index'])->middleware('can:view,record');
    Route::post('/records/{record}/links', [RecordLinkController::class, 'store'])->middleware('can:update,record');
    Route::delete('/records/{record}/links', [RecordLinkController::class, 'destroy'])->middleware('can:update,record');

    Route::get('/layouts/{surface}/{key}', [LayoutController::class, 'show'])->middleware('op:record.read');

    // --- Phase 5: layout builder (draft → publish → rollback), gated by op:manage.layouts ---
    Route::get('/layouts/{surface}/{key}/versions', [LayoutController::class, 'versions'])->middleware('op:manage.layouts');
    Route::post('/layouts/{surface}/{key}/versions', [LayoutController::class, 'storeVersion'])->middleware('op:manage.layouts');
    Route::post('/layouts/{surface}/{key}/publish', [LayoutController::class, 'publish'])->middleware('op:manage.layouts');
    Route::post('/layouts/{surface}/{key}/rollback', [LayoutController::class, 'rollback'])->middleware('op:manage.layouts');
    Route::post('/layouts/{surface}/{key}/reset', [LayoutController::class, 'reset'])->middleware('op:manage.layouts');

    // --- Phase 2: pipelines, stages, board, stage moves ---
    Route::get('/entity-types/{entityTypeKey}/pipelines', [PipelineController::class, 'index'])->middleware('op:record.read');
    Route::post('/entity-types/{entityTypeKey}/pipelines', [PipelineController::class, 'store'])->middleware('op:manage.entity-types');

    Route::get('/pipelines/{pipeline}/stages', [StageController::class, 'index'])->middleware('op:record.read');
    Route::post('/pipelines/{pipeline}/stages', [StageController::class, 'store'])->middleware('op:manage.entity-types');
    Route::put('/pipelines/{pipeline}/stages/reorder', [StageController::class, 'reorder'])->middleware('op:manage.entity-types');
    Route::put('/stages/{stage}', [StageController::class, 'update'])->middleware('op:manage.entity-types');
    Route::delete('/stages/{stage}', [StageController::class, 'destroy'])->middleware('op:manage.entity-types');

    Route::get('/entity-types/{entityTypeKey}/board', [BoardController::class, 'show'])->middleware('op:record.read');
    Route::post('/records/{record}/move', [RecordMoveController::class, 'store'])->middleware('can:move,record');
});
