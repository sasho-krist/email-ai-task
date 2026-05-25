<?php

namespace Tests\Unit;

use App\Enums\ApprovalAction;
use App\Enums\TaskDraftStatus;
use App\Enums\TaskPriority;
use App\Exceptions\OverrideRequiresReasonException;
use App\Exceptions\TaskDraftAlreadyProcessedException;
use App\Models\ApprovalDecision;
use App\Models\AuditLog;
use App\Services\TaskDraftReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\CreatesEmailFixtures;
use Tests\TestCase;

class TaskDraftReviewServiceTest extends TestCase
{
    use CreatesEmailFixtures;
    use RefreshDatabase;

    private TaskDraftReviewService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TaskDraftReviewService::class);
    }

    #[Test]
    public function it_approves_a_pending_draft(): void
    {
        $draft = $this->createPendingDraft();

        $result = $this->service->approve($draft, 'Alex PM', 'Looks good.');

        $this->assertSame(TaskDraftStatus::Approved, $result->status);
        $this->assertDatabaseHas('approval_decisions', [
            'task_draft_id' => $draft->id,
            'action' => ApprovalAction::Approved->value,
            'operator_name' => 'Alex PM',
            'note' => 'Looks good.',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'task_draft',
            'entity_id' => $draft->id,
            'action' => ApprovalAction::Approved->value,
            'actor' => 'Alex PM',
        ]);
    }

    #[Test]
    public function it_rejects_a_pending_draft(): void
    {
        $draft = $this->createPendingDraft();

        $result = $this->service->reject($draft, 'Alex PM', 'Not actionable.');

        $this->assertSame(TaskDraftStatus::Rejected, $result->status);
        $this->assertSame(1, ApprovalDecision::query()->count());
    }

    #[Test]
    public function it_overrides_fields_when_reason_is_provided(): void
    {
        $draft = $this->createPendingDraft();

        $result = $this->service->override(
            $draft,
            'Alex PM',
            ['priority' => 'critical', 'suggested_team' => 'On-call'],
            'Client confirmed production impact.',
            'Escalated after call.',
        );

        $this->assertSame(TaskDraftStatus::Overridden, $result->status);
        $this->assertSame(TaskPriority::Critical, $result->priority);
        $this->assertSame('On-call', $result->suggested_team);
    }

    #[Test]
    public function it_requires_override_reason_when_fields_change(): void
    {
        $draft = $this->createPendingDraft();

        $this->expectException(OverrideRequiresReasonException::class);

        $this->service->override($draft, 'Alex PM', ['priority' => 'high']);
    }

    #[Test]
    public function it_prevents_second_review_action(): void
    {
        $draft = $this->createPendingDraft();

        $this->service->approve($draft, 'Alex PM');

        $this->expectException(TaskDraftAlreadyProcessedException::class);

        $this->service->reject($draft->fresh(), 'Alex PM');
    }

    #[Test]
    public function it_allows_override_without_field_changes(): void
    {
        $draft = $this->createPendingDraft();

        $result = $this->service->override(
            $draft,
            'Alex PM',
            [],
            null,
            'Reviewed manually without field changes.',
        );

        $this->assertSame(TaskDraftStatus::Overridden, $result->status);
        $this->assertSame(1, AuditLog::query()->count());
    }
}
