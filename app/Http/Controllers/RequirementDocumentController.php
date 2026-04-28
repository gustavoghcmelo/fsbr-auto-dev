<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Http\Requests\UploadRequirementDocumentRequest;
use App\Jobs\GenerateRequirementsFromDocument;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\RequirementDocument;
use App\Services\Storage\FileStorageService;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RequirementDocumentController extends Controller
{
    public function __construct(
        private readonly FileStorageService $storage,
    ) {}

    public function store(
        UploadRequirementDocumentRequest $request,
        Project $project
    ): RedirectResponse {
        $stored = $this->storage->storeUpload(
            $request->file('file'),
            "projects/{$project->id}/requirement-documents",
        );

        $document = $project->requirementDocuments()->create([
            'uploaded_by' => $request->user()->id,
            'original_filename' => $stored['filename'],
            'disk' => $stored['disk'],
            'storage_path' => $stored['path'],
            'mime_type' => $stored['mime'],
            'size_bytes' => $stored['size'],
            'status' => DocumentStatus::Pending,
        ]);

        GenerateRequirementsFromDocument::dispatch($document);

        return back()->with('status', 'Documento enviado. Geração de requisitos em andamento.');
    }

    public function download(Project $project, RequirementDocument $document): StreamedResponse
    {
        $this->authorize('view', $project);
        abort_unless($document->project_id === $project->id, 404);

        return $this->storage
            ->disk($document->disk)
            ->download($document->storage_path, $document->original_filename);
    }

    public function retry(Project $project, RequirementDocument $document): RedirectResponse
    {
        abort_unless($document->project_id === $project->id, 404);
        $this->authorize('create', [Requirement::class, $project]);

        abort_unless(
            $document->status === DocumentStatus::Failed,
            422,
            'Só é possível reprocessar documentos com status "Falhou".'
        );

        $document->update([
            'status' => DocumentStatus::Pending,
            'failure_reason' => null,
        ]);

        GenerateRequirementsFromDocument::dispatch($document);

        return back()->with('status', 'Reprocessamento enfileirado.');
    }
}
