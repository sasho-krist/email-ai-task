<?php

namespace App\DTOs;

readonly class AiEvaluationResult
{
    /**
     * @param  array<string, mixed>  $rawRequest
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        public TaskDraftSuggestion $suggestion,
        public string $provider,
        public string $promptVersion,
        public array $rawRequest,
        public array $rawResponse,
        public int $processingTimeMs,
    ) {}
}
