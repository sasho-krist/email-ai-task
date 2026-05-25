<?php

namespace Tests\Unit;

use App\Enums\TaskPriority;
use App\Enums\TaskType;
use App\Exceptions\AiEvaluationFailedException;
use App\Exceptions\EmailTooVagueException;
use App\Services\Ai\MockEmailToTaskEvaluator;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\CreatesEmailFixtures;
use Tests\TestCase;

class MockEmailToTaskEvaluatorTest extends TestCase
{
    use CreatesEmailFixtures;

    private MockEmailToTaskEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->evaluator = new MockEmailToTaskEvaluator;
    }

    #[Test]
    public function it_classifies_bug_emails(): void
    {
        $email = $this->makeIncomingEmail([
            'subject' => 'Checkout crash',
            'body' => 'Project: Store. The app crashes with an error when I tap checkout. Steps: open cart, tap checkout.',
        ]);

        $result = $this->evaluator->evaluate($email);

        $this->assertSame(TaskType::Bug, $result->suggestion->type);
        $this->assertSame(TaskPriority::High, $result->suggestion->priority);
        $this->assertSame('mock', $result->provider);
        $this->assertGreaterThanOrEqual(0, $result->processingTimeMs);
    }

    #[Test]
    public function it_extracts_project_from_body(): void
    {
        $email = $this->makeIncomingEmail([
            'subject' => 'Question about billing',
            'body' => 'Project: Billing Portal. How do I change the invoice address on my account?',
        ]);

        $result = $this->evaluator->evaluate($email);

        $this->assertSame('Billing Portal', $result->suggestion->suggestedProject);
        $this->assertSame(TaskType::Question, $result->suggestion->type);
    }

    #[Test]
    public function it_throws_when_email_is_too_vague(): void
    {
        $email = $this->makeIncomingEmail([
            'subject' => 'Help',
            'body' => '???',
        ]);

        $this->expectException(EmailTooVagueException::class);

        $this->evaluator->evaluate($email);
    }

    #[Test]
    public function it_throws_when_ai_failure_is_simulated(): void
    {
        $email = $this->makeIncomingEmail([
            'subject' => '[AI_FAIL] outage',
            'body' => 'This body is long enough but the subject triggers a simulated failure.',
        ]);

        $this->expectException(AiEvaluationFailedException::class);

        $this->evaluator->evaluate($email);
    }
}
