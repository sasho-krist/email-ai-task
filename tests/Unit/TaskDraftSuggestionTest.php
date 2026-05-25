<?php

namespace Tests\Unit;

use App\DTOs\TaskDraftSuggestion;
use App\Enums\TaskPriority;
use App\Enums\TaskType;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TaskDraftSuggestionTest extends TestCase
{
    #[Test]
    public function it_serializes_to_expected_array_shape(): void
    {
        $suggestion = new TaskDraftSuggestion(
            type: TaskType::Bug,
            title: 'Checkout crash',
            summary: 'Mobile checkout fails on iOS.',
            priority: TaskPriority::High,
            suggestedProject: 'Acme Store',
            suggestedTeam: 'Engineering',
            confidence: 0.91,
            missingInformation: ['Steps to reproduce'],
            suggestedNextAction: 'Assign to engineering for triage.',
        );

        $this->assertSame([
            'type' => 'bug',
            'title' => 'Checkout crash',
            'summary' => 'Mobile checkout fails on iOS.',
            'priority' => 'high',
            'suggested_project' => 'Acme Store',
            'suggested_team' => 'Engineering',
            'confidence' => 0.91,
            'missing_information' => ['Steps to reproduce'],
            'suggested_next_action' => 'Assign to engineering for triage.',
        ], $suggestion->toArray());
    }
}
