<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FieldDefinitionController;
use App\Http\Controllers\LayoutController;
use App\Http\Controllers\RecordController;
use App\Http\Controllers\SchemaController;
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
});
