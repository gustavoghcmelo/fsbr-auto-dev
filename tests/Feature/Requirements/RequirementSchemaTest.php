<?php

namespace Tests\Feature\Requirements;

use App\Enums\DevStatus;
use App\Enums\DorStatus;
use App\Enums\RequirementPriority;
use App\Enums\RequirementStatus;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RequirementSchemaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_persists_all_new_fields(): void
    {
        $requirement = Requirement::factory()->create([
            'objective' => 'Permitir aprovação de DoR pelo QA',
            'user_story_role' => 'analista de QA',
            'user_story_action' => 'validar DoR',
            'user_story_benefit' => 'liberar para desenvolvimento',
            'functional_description' => 'Tela com checklist e botões aprovar/bloquear.',
            'figma_url' => 'https://www.figma.com/file/abc',
            'figma_notes' => 'Frame "DoR Validation"',
            'flow_steps' => ['Abrir tela', 'Marcar checklist', 'Aprovar'],
            'business_rules' => ['Só QA pode aprovar'],
            'error_handling' => ['Mostrar mensagem se faltar critério'],
            'dependencies' => [
                'api' => ['/api/requirements/{id}/dor'],
                'integrations' => [],
                'other_systems' => [],
            ],
            'technical_risks' => ['Concorrência entre múltiplos QAs'],
            'priority' => RequirementPriority::High,
            'story_points' => 5,
            'dor_status' => DorStatus::InQaValidation,
            'dor_checklist' => [
                'requirement_clear' => true,
                'figma_aligned' => true,
                'criteria_defined' => true,
                'business_rules_clear' => true,
                'no_ambiguity' => false,
                'testable' => true,
            ],
            'qa_observations' => 'Falta clarear regra X',
            'dev_status' => DevStatus::NotStarted,
        ]);

        $reloaded = $requirement->fresh();

        $this->assertSame('Permitir aprovação de DoR pelo QA', $reloaded->objective);
        $this->assertSame('analista de QA', $reloaded->user_story_role);
        $this->assertSame(['Abrir tela', 'Marcar checklist', 'Aprovar'], $reloaded->flow_steps);
        $this->assertSame(['Só QA pode aprovar'], $reloaded->business_rules);
        $this->assertSame(['Mostrar mensagem se faltar critério'], $reloaded->error_handling);
        $this->assertSame([
            'api' => ['/api/requirements/{id}/dor'],
            'integrations' => [],
            'other_systems' => [],
        ], $reloaded->dependencies);
        $this->assertSame(['Concorrência entre múltiplos QAs'], $reloaded->technical_risks);
        $this->assertSame(RequirementPriority::High, $reloaded->priority);
        $this->assertSame(5, $reloaded->story_points);
        $this->assertSame(DorStatus::InQaValidation, $reloaded->dor_status);
        $this->assertFalse($reloaded->dor_checklist['no_ambiguity']);
        $this->assertSame('Falta clarear regra X', $reloaded->qa_observations);
        $this->assertSame(DevStatus::NotStarted, $reloaded->dev_status);
    }

    #[Test]
    public function it_defaults_dor_status_to_draft(): void
    {
        $requirement = Requirement::factory()->create();

        $this->assertSame(DorStatus::Draft, $requirement->fresh()->dor_status);
    }

    #[Test]
    public function it_defaults_dev_status_to_not_started(): void
    {
        $requirement = Requirement::factory()->create();

        $this->assertSame(DevStatus::NotStarted, $requirement->fresh()->dev_status);
    }

    #[Test]
    public function it_defaults_priority_to_medium(): void
    {
        $requirement = Requirement::factory()->create();

        $this->assertSame(RequirementPriority::Medium, $requirement->fresh()->priority);
    }

    #[Test]
    public function it_casts_dor_checklist_as_array(): void
    {
        $checklist = [
            'requirement_clear' => true,
            'figma_aligned' => false,
            'criteria_defined' => true,
            'business_rules_clear' => false,
            'no_ambiguity' => false,
            'testable' => true,
        ];

        $requirement = Requirement::factory()->create([
            'dor_checklist' => $checklist,
        ]);

        $this->assertIsArray($requirement->fresh()->dor_checklist);
        $this->assertSame($checklist, $requirement->fresh()->dor_checklist);
    }

    #[Test]
    public function it_casts_dependencies_as_array_with_three_keys(): void
    {
        $requirement = Requirement::factory()->create([
            'dependencies' => [
                'api' => ['/foo'],
                'integrations' => ['stripe'],
                'other_systems' => ['legacy-erp'],
            ],
        ]);

        $deps = $requirement->fresh()->dependencies;

        $this->assertIsArray($deps);
        $this->assertSame(['api', 'integrations', 'other_systems'], array_keys($deps));
        $this->assertSame(['/foo'], $deps['api']);
        $this->assertSame(['stripe'], $deps['integrations']);
        $this->assertSame(['legacy-erp'], $deps['other_systems']);
    }

    #[Test]
    public function it_nulls_dev_assignee_when_user_deleted(): void
    {
        $assignee = User::factory()->create();
        $requirement = Requirement::factory()->create([
            'dev_assignee_id' => $assignee->id,
        ]);

        $assignee->delete();

        $this->assertNull($requirement->fresh()->dev_assignee_id);
    }

    #[Test]
    public function it_keeps_old_status_enum_working(): void
    {
        $requirement = Requirement::factory()->create([
            'status' => RequirementStatus::Approved,
        ]);

        $this->assertSame(RequirementStatus::Approved, $requirement->fresh()->status);
        $this->assertTrue($requirement->isApproved());

        $approved = Requirement::query()->approved()->get();
        $this->assertTrue($approved->contains($requirement));
    }
}
