<?php

namespace App\DTOs;

use App\Enums\TaskPriority;
use App\Enums\TaskType;

readonly class TaskDraftSuggestion
{
    /**
     * @param  list<string>  $missingInformation
     */
    public function __construct(
        public TaskType $type,
        public string $title,
        public string $summary,
        public TaskPriority $priority,
        public ?string $suggestedProject,
        public ?string $suggestedTeam,
        public float $confidence,
        public array $missingInformation,
        public string $suggestedNextAction,
    ) {}

    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'title' => $this->title,
            'summary' => $this->summary,
            'priority' => $this->priority->value,
            'suggested_project' => $this->suggestedProject,
            'suggested_team' => $this->suggestedTeam,
            'confidence' => $this->confidence,
            'missing_information' => $this->missingInformation,
            'suggested_next_action' => $this->suggestedNextAction,
        ];
    }
}
