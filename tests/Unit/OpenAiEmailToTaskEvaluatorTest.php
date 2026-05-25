<?php

namespace Tests\Unit;

use App\Enums\TaskPriority;
use App\Enums\TaskType;
use App\Exceptions\AiEvaluationFailedException;
use App\Exceptions\EmailTooVagueException;
use App\Services\Ai\OpenAiEmailToTaskEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\CreatesEmailFixtures;
use Tests\TestCase;

class OpenAiEmailToTaskEvaluatorTest extends TestCase
{
    use CreatesEmailFixtures;
    use RefreshDatabase;

    #[Test]
    public function it_requires_api_key(): void
    {
        config(['ai.openai.api_key' => null]);

        $evaluator = app(OpenAiEmailToTaskEvaluator::class);

        $this->expectException(AiEvaluationFailedException::class);
        $this->expectExceptionMessage('OPENAI_API_KEY is not configured.');

        $evaluator->evaluate($this->makeIncomingEmail());
    }

    #[Test]
    public function it_maps_openai_json_into_task_draft_suggestion(): void
    {
        config(['ai.openai.api_key' => 'test-key']);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'model' => 'gpt-4o-mini',
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'type' => 'feature_request',
                            'title' => 'Add CSV export',
                            'summary' => 'Client wants CSV export for board reports.',
                            'priority' => 'medium',
                            'suggested_project' => 'Reporting',
                            'suggested_team' => 'Product',
                            'confidence' => 0.88,
                            'missing_information' => ['Target dashboard page'],
                            'suggested_next_action' => 'Schedule product review.',
                            'too_vague' => false,
                        ], JSON_THROW_ON_ERROR),
                    ],
                ]],
                'usage' => ['total_tokens' => 120],
            ]),
        ]);

        $result = app(OpenAiEmailToTaskEvaluator::class)->evaluate($this->makeIncomingEmail([
            'subject' => 'Feature: export CSV',
            'body' => 'Project: Reporting. Please add CSV export to the dashboard.',
        ]));

        $this->assertSame('openai', $result->provider);
        $this->assertSame(TaskType::FeatureRequest, $result->suggestion->type);
        $this->assertSame(TaskPriority::Medium, $result->suggestion->priority);
        $this->assertSame('Reporting', $result->suggestion->suggestedProject);
        $this->assertSame(['Target dashboard page'], $result->suggestion->missingInformation);
    }

    #[Test]
    public function it_throws_when_openai_flags_email_as_too_vague(): void
    {
        config(['ai.openai.api_key' => 'test-key']);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'model' => 'gpt-4o-mini',
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'type' => 'unknown',
                            'title' => 'Unclear request',
                            'summary' => 'Not enough detail.',
                            'priority' => 'low',
                            'suggested_project' => null,
                            'suggested_team' => null,
                            'confidence' => 0.2,
                            'missing_information' => ['What is needed'],
                            'suggested_next_action' => 'Ask for clarification.',
                            'too_vague' => true,
                        ], JSON_THROW_ON_ERROR),
                    ],
                ]],
            ]),
        ]);

        $this->expectException(EmailTooVagueException::class);

        app(OpenAiEmailToTaskEvaluator::class)->evaluate($this->makeIncomingEmail([
            'subject' => 'Help',
            'body' => 'Not sure what I need.',
        ]));
    }

    #[Test]
    public function it_surfaces_openai_api_errors(): void
    {
        config(['ai.openai.api_key' => 'test-key']);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'error' => ['message' => 'Invalid API key'],
            ], 401),
        ]);

        $this->expectException(AiEvaluationFailedException::class);
        $this->expectExceptionMessage('Invalid API key');

        app(OpenAiEmailToTaskEvaluator::class)->evaluate($this->makeIncomingEmail());
    }
}
