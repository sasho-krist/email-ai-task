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

class MockEmailToTaskEvaluator implements EmailToTaskEvaluator
{
    private const PROMPT_VERSION = 'mock-v1';

    public function evaluate(IncomingEmail $email): AiEvaluationResult
    {
        $startedAt = microtime(true);
        $combined = strtolower($email->subject.' '.$email->body);

        if (str_contains(strtolower($email->subject), '[ai_fail]')) {
            throw new AiEvaluationFailedException('Simulated AI provider outage.');
        }

        $normalizedBody = trim($email->body);
        if (strlen($normalizedBody) < 15 || in_array($normalizedBody, ['???', 'help', 'see below'], true)) {
            throw new EmailTooVagueException();
        }

        $type = $this->detectType($combined);
        $priority = $this->detectPriority($combined, $type);
        $missingInformation = $this->detectMissingInformation($email, $type);
        $confidence = $this->calculateConfidence($email, $missingInformation);
        $suggestedProject = $this->detectProject($email);
        $suggestedTeam = $this->detectTeam($type, $suggestedProject);

        $suggestion = new TaskDraftSuggestion(
            type: $type,
            title: $this->buildTitle($email, $type),
            summary: $this->buildSummary($email, $type),
            priority: $priority,
            suggestedProject: $suggestedProject,
            suggestedTeam: $suggestedTeam,
            confidence: $confidence,
            missingInformation: $missingInformation,
            suggestedNextAction: $this->suggestNextAction($type, $missingInformation),
        );

        $rawRequest = [
            'from' => $email->from,
            'subject' => $email->subject,
            'body' => $email->body,
        ];

        $rawResponse = [
            'model' => 'mock-heuristic-v1',
            'suggestion' => $suggestion->toArray(),
        ];

        return new AiEvaluationResult(
            suggestion: $suggestion,
            provider: 'mock',
            promptVersion: self::PROMPT_VERSION,
            rawRequest: $rawRequest,
            rawResponse: $rawResponse,
            processingTimeMs: (int) round((microtime(true) - $startedAt) * 1000),
        );
    }

    private function detectType(string $combined): TaskType
    {
        $bugSignals = ['bug', 'error', 'crash', 'broken', 'regression', 'fix'];
        $featureSignals = ['feature', 'enhancement', 'add ', 'implement', 'request'];
        $questionSignals = ['?', 'how do', 'how to', 'what is', 'can you explain', 'question'];

        $scores = [
            TaskType::Bug->value => $this->countSignals($combined, $bugSignals),
            TaskType::FeatureRequest->value => $this->countSignals($combined, $featureSignals),
            TaskType::Question->value => $this->countSignals($combined, $questionSignals),
        ];

        arsort($scores);
        $topType = array_key_first($scores);
        $topScore = $scores[$topType];

        if ($topScore === 0) {
            return str_contains($combined, 'feedback') ? TaskType::Feedback : TaskType::Unknown;
        }

        $nonZero = array_filter($scores, fn (int $score) => $score > 0);
        if (count($nonZero) > 1 && reset($nonZero) === end($nonZero)) {
            return TaskType::Mixed;
        }

        return TaskType::from($topType);
    }

    /**
     * @param  list<string>  $signals
     */
    private function countSignals(string $haystack, array $signals): int
    {
        $count = 0;

        foreach ($signals as $signal) {
            if (str_contains($haystack, $signal)) {
                $count++;
            }
        }

        return $count;
    }

    private function detectPriority(string $combined, TaskType $type): TaskPriority
    {
        if (str_contains($combined, 'critical') || str_contains($combined, 'production down') || str_contains($combined, 'urgent')) {
            return TaskPriority::Critical;
        }

        if ($type === TaskType::Bug || str_contains($combined, 'asap') || str_contains($combined, 'blocked')) {
            return TaskPriority::High;
        }

        if ($type === TaskType::Question || $type === TaskType::Feedback) {
            return TaskPriority::Low;
        }

        return TaskPriority::Medium;
    }

    /**
     * @return list<string>
     */
    private function detectMissingInformation(IncomingEmail $email, TaskType $type): array
    {
        $missing = [];
        $combined = strtolower($email->subject.' '.$email->body);

        if ($type === TaskType::Bug && ! str_contains($combined, 'step')) {
            $missing[] = 'Steps to reproduce';
        }

        if ($type === TaskType::Bug && ! preg_match('/\b(chrome|firefox|safari|edge|browser|version)\b/', $combined)) {
            $missing[] = 'Environment or browser details';
        }

        if (! preg_match('/\bproject[:\s]|client[:\s]/i', $email->body.$email->subject)) {
            $missing[] = 'Affected project or client name';
        }

        if ($type === TaskType::FeatureRequest && ! str_contains($combined, 'because') && ! str_contains($combined, 'so that')) {
            $missing[] = 'Business justification or expected outcome';
        }

        return $missing;
    }

    /**
     * @param  list<string>  $missingInformation
     */
    private function calculateConfidence(IncomingEmail $email, array $missingInformation): float
    {
        $confidence = 0.85;

        $confidence -= count($missingInformation) * 0.08;
        $confidence -= max(0, 120 - strlen(trim($email->body))) / 400;

        return round(max(0.25, min(0.98, $confidence)), 4);
    }

    private function detectProject(IncomingEmail $email): ?string
    {
        if (preg_match('/project[:\s]+([^\n\r,.]+)/i', $email->body, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/@([\w-]+)\./', $email->from, $matches)) {
            return ucfirst($matches[1]);
        }

        return null;
    }

    private function detectTeam(TaskType $type, ?string $project): ?string
    {
        return match ($type) {
            TaskType::Bug => 'Engineering',
            TaskType::FeatureRequest => 'Product',
            TaskType::Question => 'Support',
            TaskType::Feedback => 'Product',
            default => $project ? "{$project} Core Team" : null,
        };
    }

    private function buildTitle(IncomingEmail $email, TaskType $type): string
    {
        $subject = trim($email->subject);

        if ($subject !== '') {
            return $subject;
        }

        return match ($type) {
            TaskType::Bug => 'Investigate reported issue',
            TaskType::FeatureRequest => 'Review feature request',
            TaskType::Question => 'Answer client question',
            default => 'Review incoming email',
        };
    }

    private function buildSummary(IncomingEmail $email, TaskType $type): string
    {
        $snippet = trim(preg_replace('/\s+/', ' ', $email->body));
        $snippet = substr($snippet, 0, 280);

        return sprintf(
            '%s request from %s: %s',
            str_replace('_', ' ', $type->value),
            $email->from,
            $snippet
        );
    }

    /**
     * @param  list<string>  $missingInformation
     */
    private function suggestNextAction(TaskType $type, array $missingInformation): string
    {
        if ($missingInformation !== []) {
            return 'Reply to sender requesting: '.implode(', ', $missingInformation).'.';
        }

        return match ($type) {
            TaskType::Bug => 'Create a bug ticket and assign to engineering for triage.',
            TaskType::FeatureRequest => 'Schedule product review and estimate scope.',
            TaskType::Question => 'Draft a response or route to the appropriate subject-matter expert.',
            TaskType::Feedback => 'Summarize feedback for the product team and acknowledge receipt.',
            default => 'Review with PM and decide whether to escalate or request clarification.',
        };
    }
}
