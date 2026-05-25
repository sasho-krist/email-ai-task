<?php

namespace App\Services\Ai;

use App\Contracts\EmailToTaskEvaluator;
use App\DTOs\AiEvaluationResult;
use App\DTOs\TaskDraftSuggestion;
use App\Enums\TaskPriority;
use App\Enums\TaskType;
use App\Exceptions\AiEvaluationFailedException;
use App\Exceptions\EmailTooVagueException;
use App\Models\IncomingEmail;
use Illuminate\Support\Facades\Http;
use Throwable;

class OpenAiEmailToTaskEvaluator implements EmailToTaskEvaluator
{
    public function evaluate(IncomingEmail $email): AiEvaluationResult
    {
        try {
            return $this->performEvaluation($email);
        } catch (EmailTooVagueException|AiEvaluationFailedException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new AiEvaluationFailedException(
                'OpenAI evaluation failed: '.$exception->getMessage(),
                previous: $exception,
            );
        }
    }

    private function performEvaluation(IncomingEmail $email): AiEvaluationResult
    {
        $startedAt = microtime(true);
        $apiKey = config('ai.openai.api_key');

        if (blank($apiKey)) {
            throw new AiEvaluationFailedException('OPENAI_API_KEY is not configured.');
        }

        $rawRequest = [
            'from' => $email->from,
            'subject' => $email->subject,
            'body' => $email->body,
        ];

        $systemPrompt = <<<'PROMPT'
You are an assistant for a project management team. Analyze incoming client emails and produce a structured task draft.

Return ONLY valid JSON with this exact schema:
{
  "type": "bug|feature_request|question|feedback|mixed|unknown",
  "title": "string",
  "summary": "string",
  "priority": "low|medium|high|critical",
  "suggested_project": "string or null",
  "suggested_team": "string or null",
  "confidence": 0.0 to 1.0,
  "missing_information": ["string"],
  "suggested_next_action": "string",
  "too_vague": false
}

Rules:
- Do not invent facts that are not implied by the email.
- If the email is too vague to act on (e.g. "help", "???", no actionable detail), set "too_vague": true and still fill other fields as best you can.
- "missing_information" lists what a PM should ask the sender before creating a task.
- "confidence" reflects how sure you are about type, priority, and project assignment.
PROMPT;

        $userPrompt = sprintf(
            "From: %s\nSubject: %s\n\nBody:\n%s",
            $email->from,
            $email->subject,
            $email->body,
        );

        try {
            $response = Http::withToken($apiKey)
                ->timeout(config('ai.openai.timeout'))
                ->post(config('ai.openai.base_url').'/chat/completions', [
                    'model' => config('ai.openai.model'),
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                    'temperature' => 0.2,
                ]);
        } catch (\Throwable $exception) {
            throw new AiEvaluationFailedException('OpenAI request failed: '.$exception->getMessage());
        }

        if (! $response->successful()) {
            $error = $response->json('error.message') ?? $response->body();

            throw new AiEvaluationFailedException('OpenAI API error: '.$error);
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw new AiEvaluationFailedException('OpenAI returned an empty response.');
        }

        $parsed = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($parsed)) {
            throw new AiEvaluationFailedException('OpenAI returned invalid JSON.');
        }

        if (($parsed['too_vague'] ?? false) === true) {
            throw new EmailTooVagueException();
        }

        $suggestion = $this->buildSuggestion($parsed);

        return new AiEvaluationResult(
            suggestion: $suggestion,
            provider: 'openai',
            promptVersion: config('ai.openai.prompt_version'),
            rawRequest: [
                ...$rawRequest,
                'model' => config('ai.openai.model'),
            ],
            rawResponse: [
                'model' => $response->json('model'),
                'usage' => $response->json('usage'),
                'parsed' => $parsed,
            ],
            processingTimeMs: (int) round((microtime(true) - $startedAt) * 1000),
        );
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function buildSuggestion(array $parsed): TaskDraftSuggestion
    {
        $type = $this->parseEnum($parsed['type'] ?? null, TaskType::class, TaskType::Unknown);
        $priority = $this->parseEnum($parsed['priority'] ?? null, TaskPriority::class, TaskPriority::Medium);

        $missing = $parsed['missing_information'] ?? [];
        if (! is_array($missing)) {
            $missing = [];
        }

        $missing = array_values(array_filter(array_map(
            fn ($item) => is_string($item) ? trim($item) : null,
            $missing
        )));

        $confidence = is_numeric($parsed['confidence'] ?? null)
            ? max(0.0, min(1.0, (float) $parsed['confidence']))
            : 0.5;

        return new TaskDraftSuggestion(
            type: $type,
            title: $this->stringOrDefault($parsed['title'] ?? null, 'Review incoming email'),
            summary: $this->stringOrDefault($parsed['summary'] ?? null, 'No summary provided.'),
            priority: $priority,
            suggestedProject: $this->nullableString($parsed['suggested_project'] ?? null),
            suggestedTeam: $this->nullableString($parsed['suggested_team'] ?? null),
            confidence: round($confidence, 4),
            missingInformation: $missing,
            suggestedNextAction: $this->stringOrDefault(
                $parsed['suggested_next_action'] ?? null,
                'Review with PM and decide next steps.'
            ),
        );
    }

    /**
     * @template T of \BackedEnum
     *
     * @param  class-string<T>  $enumClass
     * @return T
     */
    private function parseEnum(?string $value, string $enumClass, \BackedEnum $default): \BackedEnum
    {
        if ($value === null) {
            return $default;
        }

        try {
            return $enumClass::from($value);
        } catch (\ValueError) {
            return $default;
        }
    }

    private function stringOrDefault(mixed $value, string $default): string
    {
        if (! is_string($value)) {
            return $default;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? $default : $trimmed;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
