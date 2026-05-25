<?php

namespace Tests\Unit;

use App\Enums\AiEvaluationStatus;
use App\Enums\IncomingEmailStatus;
use App\Enums\TaskDraftStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskType;
use App\Exceptions\AiEvaluationFailedException;
use App\Exceptions\DuplicateEmailException;
use App\Exceptions\EmailTooVagueException;
use App\Models\AiEvaluation;
use App\Models\IncomingEmail;
use App\Models\TaskDraft;
use App\Services\IncomingEmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IncomingEmailServiceTest extends TestCase
{
    use RefreshDatabase;

    private IncomingEmailService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(IncomingEmailService::class);
    }

    #[Test]
    public function it_processes_email_into_pending_draft(): void
    {
        $draft = $this->service->process([
            'from' => 'client@acme.com',
            'subject' => 'Checkout bug on mobile',
            'body' => 'Project: Acme Store. The checkout button crashes on iOS. Steps: open cart, tap checkout.',
        ]);

        $this->assertSame(TaskDraftStatus::PendingReview, $draft->status);
        $this->assertSame(TaskType::Bug, $draft->type);
        $this->assertSame(IncomingEmailStatus::Processed, $draft->incomingEmail->status);
        $this->assertDatabaseCount('ai_evaluations', 1);
        $this->assertDatabaseHas('ai_evaluations', [
            'status' => AiEvaluationStatus::Success->value,
            'provider' => 'mock',
        ]);
    }

    #[Test]
    public function it_rejects_duplicate_emails(): void
    {
        $payload = [
            'from' => 'client@acme.com',
            'subject' => 'Duplicate test',
            'body' => 'Same content every time for duplicate detection.',
        ];

        $this->service->process($payload);

        $this->expectException(DuplicateEmailException::class);

        $this->service->process($payload);
    }

    #[Test]
    public function it_marks_email_failed_when_content_is_too_vague(): void
    {
        try {
            $this->service->process([
                'from' => 'client@acme.com',
                'subject' => 'Help',
                'body' => '???',
            ]);
        } catch (EmailTooVagueException) {
            // expected
        }

        $email = IncomingEmail::query()->first();

        $this->assertNotNull($email);
        $this->assertSame(IncomingEmailStatus::Failed, $email->status);
        $this->assertSame(0, TaskDraft::query()->count());
        $this->assertDatabaseHas('ai_evaluations', [
            'status' => AiEvaluationStatus::Failed->value,
        ]);
    }

    #[Test]
    public function it_marks_email_failed_when_ai_evaluation_fails(): void
    {
        try {
            $this->service->process([
                'from' => 'client@acme.com',
                'subject' => '[AI_FAIL] simulated outage',
                'body' => 'This body is long enough but the subject triggers a simulated provider failure.',
            ]);
        } catch (AiEvaluationFailedException) {
            // expected
        }

        $this->assertDatabaseHas('incoming_emails', [
            'status' => IncomingEmailStatus::Failed->value,
        ]);
    }
}
