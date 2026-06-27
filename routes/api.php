<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\FieldDefinitionController;
use App\Http\Controllers\LayoutController;
use App\Http\Controllers\PipelineController;
use App\Http\Controllers\RecordController;
use App\Http\Controllers\RecordMoveController;
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
    Route::post('/logout', [AuthController::class, 'logout']);

    // --- Phase 1: configuration engine on one entity ---
    Route::get('/entity-types/{entityTypeKey}/schema', [SchemaController::class, 'show']);
    Route::post('/entity-types/{entityTypeKey}/fields', [FieldDefinitionController::class, 'store']);

    Route::get('/entity-types/{entityTypeKey}/records', [RecordController::class, 'index']);
    Route::post('/entity-types/{entityTypeKey}/records', [RecordController::class, 'store']);
    Route::get('/records/{record}', [RecordController::class, 'show']);
    Route::put('/records/{record}', [RecordController::class, 'update']);
    Route::delete('/records/{record}', [RecordController::class, 'destroy']);

    Route::get('/layouts/{surface}/{key}', [LayoutController::class, 'show']);

    // --- Phase 2: pipelines, stages, board, stage moves ---
    Route::get('/entity-types/{entityTypeKey}/pipelines', [PipelineController::class, 'index']);
    Route::post('/entity-types/{entityTypeKey}/pipelines', [PipelineController::class, 'store']);

    Route::get('/pipelines/{pipeline}/stages', [StageController::class, 'index']);
    Route::post('/pipelines/{pipeline}/stages', [StageController::class, 'store']);
    Route::put('/pipelines/{pipeline}/stages/reorder', [StageController::class, 'reorder']);
    Route::put('/stages/{stage}', [StageController::class, 'update']);
    Route::delete('/stages/{stage}', [StageController::class, 'destroy']);

    Route::get('/entity-types/{entityTypeKey}/board', [BoardController::class, 'show']);
    Route::post('/records/{record}/move', [RecordMoveController::class, 'store']);
});
