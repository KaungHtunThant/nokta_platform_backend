<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Records\UploadRecordFileRequest;
use App\Http\Resources\RecordResource;
use App\Models\Record;
use App\Models\User;
use App\Services\Records\FieldGate;
use App\Services\Records\RecordFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * Thin controller for file/media fields (Phase 7). Routes are gated by can:update,record; per-field
 * write authorization + storage live in RecordFileService. Returns the record with its files map.
 */
final class RecordFileController extends Controller
{
    public function __construct(
        private readonly RecordFileService $files,
        private readonly FieldGate $fieldGate,
    ) {}

    public function store(UploadRecordFileRequest $request, Record $record): RecordResource
    {
        /** @var UploadedFile $file */
        $file = $request->file('file');
        $this->files->attach($record, $request->fieldKey(), $file, $this->actor($request));

        return $this->present($request, $record);
    }

    public function destroy(Request $request, Record $record, int $media): JsonResponse
    {
        $this->files->detach($record, $media);

        return response()->json(status: 204);
    }

    private function present(Request $request, Record $record): RecordResource
    {
        $type = $record->entityType()->firstOrFail();
        $actor = $this->actor($request);
        if ($actor !== null) {
            $request->attributes->set('readableFieldKeys', $this->fieldGate->readableKeysForType($actor, $type));
        }

        return RecordResource::make($record->load('entityType', 'media'));
    }

    private function actor(Request $request): ?User
    {
        /** @var User|null $user */
        $user = $request->user();

        return $user;
    }
}
