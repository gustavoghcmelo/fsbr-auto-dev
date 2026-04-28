<?php

namespace App\Jobs;

use App\Enums\DocumentStatus;
use App\Enums\RequirementStatus;
use App\Models\Requirement;
use App\Models\RequirementDocument;
use App\Services\AI\RequirementGenerator;
use App\Services\Parsers\DocumentTextExtractor;
use App\Services\Storage\FileStorageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateRequirementsFromDocument implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(
        public RequirementDocument $document
    ) {}

    public function handle(
        FileStorageService $storage,
        DocumentTextExtractor $extractor,
        RequirementGenerator $generator,
    ): void {
        $this->document->loadMissing('project');
        $this->document->update(['status' => DocumentStatus::Processing]);

        try {
            $text = $storage->withLocalCopy(
                $this->document->storage_path,
                fn (string $localPath) => $extractor->extract(
                    $localPath,
                    $this->document->mime_type,
                    $this->document->original_filename,
                ),
                $this->document->disk,
            );

            if ($text === '') {
                throw new \RuntimeException('Não foi possível extrair texto do documento enviado.');
            }

            $generated = $generator->generate($text);

            DB::transaction(function () use ($generated) {
                $nextOrder = ($this->document->project->requirements()->max('order') ?? -1) + 1;

                foreach ($generated as $index => $feature) {
                    Requirement::create([
                        'project_id' => $this->document->project_id,
                        'requirement_document_id' => $this->document->id,
                        'created_by' => $this->document->uploaded_by,
                        'title' => $feature['title'],
                        'description' => $feature['description'] ?? null,
                        'context' => $feature['context'] ?? null,
                        'acceptance_criteria' => $feature['acceptance_criteria'] ?? [],
                        'gherkin' => $feature['gherkin'],
                        'status' => RequirementStatus::Draft,
                        'order' => $nextOrder + $index,
                    ]);
                }

                $this->document->update([
                    'status' => DocumentStatus::Generated,
                    'failure_reason' => null,
                ]);
            });
        } catch (Throwable $e) {
            Log::error('Falha ao gerar requisitos a partir do documento', [
                'document_id' => $this->document->id,
                'error' => $e->getMessage(),
            ]);

            $this->document->update([
                'status' => DocumentStatus::Failed,
                'failure_reason' => $e->getMessage(),
            ]);
        }
    }
}
