<?php

namespace App\Services;

use App\DTOs\AiEvaluationResult;
use App\Contracts\EmailToTaskEvaluator;
use App\Enums\AiEvaluationStatus;
use App\Enums\IncomingEmailStatus;
use App\Enums\TaskDraftStatus;
use App\Exceptions\AiEvaluationFailedException;
use App\Exceptions\DuplicateEmailException;
use App\Exceptions\EmailTooVagueException;
use App\Models\AiEvaluation;
use App\Models\IncomingEmail;
use App\Models\TaskDraft;
use Illuminate\Support\Facades\DB;
use Throwable;

class IncomingEmailService
{
    public function __construct(
        private readonly EmailToTaskEvaluator $evaluator,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{from: string, subject: string, body: string}  $payload
     */
    public function process(array $payload): TaskDraft
    {
        try {
            $contentHash = $this->buildContentHash($payload);

            $existing = IncomingEmail::query()->where('content_hash', $contentHash)->first();
            if ($existing !== null) {
                throw new DuplicateEmailException($existing->id);
            }

            $email = $this->createIncomingEmail($payload, $contentHash);

            try {
                $result = $this->evaluator->evaluate($email);
            } catch (EmailTooVagueException|AiEvaluationFailedException $exception) {
                $this->persistFailedEvaluation($email, $exception);
                throw $exception;
            } catch (Throwable $exception) {
                $wrapped = new AiEvaluationFailedException(
                    'Unexpected AI error: '.$exception->getMessage(),
                    previous: $exception,
                );
                $this->persistFailedEvaluation($email, $wrapped);
                throw $wrapped;
            }

            return $this->persistSuccessfulEvaluation($email, $result);
        } catch (DuplicateEmailException|EmailTooVagueException|AiEvaluationFailedException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new AiEvaluationFailedException(
                'Unexpected processing error: '.$exception->getMessage(),
                previous: $exception,
            );
        }
    }

    /**
     * @param  array{from: string, subject: string, body: string}  $payload
     */
    private function createIncomingEmail(array $payload, string $contentHash): IncomingEmail
    {
        return DB::transaction(function () use ($payload, $contentHash) {
            $email = IncomingEmail::query()->create([
                'from' => $payload['from'],
                'subject' => $payload['subject'],
                'body' => $payload['body'],
                'content_hash' => $contentHash,
                'status' => IncomingEmailStatus::Processing,
            ]);

            $this->auditLogger->log('incoming_email', $email->id, 'received', null, [
                'from' => $email->from,
                'subject' => $email->subject,
            ]);

            return $email;
        });
    }

    private function persistFailedEvaluation(
        IncomingEmail $email,
        EmailTooVagueException|AiEvaluationFailedException $exception,
    ): void {
        DB::transaction(function () use ($email, $exception) {
            $email->update([
                'status' => IncomingEmailStatus::Failed,
                'failure_reason' => $exception->getMessage(),
            ]);

            AiEvaluation::query()->create([
                'incoming_email_id' => $email->id,
                'provider' => config('ai.provider'),
                'prompt_version' => $this->promptVersion(),
                'raw_request' => [
                    'from' => $email->from,
                    'subject' => $email->subject,
                    'body' => $email->body,
                ],
                'status' => AiEvaluationStatus::Failed,
                'error_message' => $exception->getMessage(),
            ]);

            $this->auditLogger->log('incoming_email', $email->id, 'ai_evaluation_failed', null, [
                'reason' => $exception->getMessage(),
            ]);
        });
    }

    private function persistSuccessfulEvaluation(IncomingEmail $email, AiEvaluationResult $result): TaskDraft
    {
        return DB::transaction(function () use ($email, $result) {
            $draft = TaskDraft::query()->create([
                'incoming_email_id' => $email->id,
                ...$result->suggestion->toArray(),
                'status' => TaskDraftStatus::PendingReview,
            ]);

            AiEvaluation::query()->create([
                'incoming_email_id' => $email->id,
                'task_draft_id' => $draft->id,
                'provider' => $result->provider,
                'prompt_version' => $result->promptVersion,
                'raw_request' => $result->rawRequest,
                'raw_response' => $result->rawResponse,
                'status' => AiEvaluationStatus::Success,
                'processing_time_ms' => $result->processingTimeMs,
            ]);

            $email->update(['status' => IncomingEmailStatus::Processed]);

            $this->auditLogger->log('task_draft', $draft->id, 'created', null, [
                'incoming_email_id' => $email->id,
                'confidence' => $draft->confidence,
            ]);

            return $draft->load(['incomingEmail', 'aiEvaluation']);
        });
    }

    /**
     * @param  array{from: string, subject: string, body: string}  $payload
     */
    private function buildContentHash(array $payload): string
    {
        return hash('sha256', strtolower(trim($payload['from'])).'|'.trim($payload['subject']).'|'.trim($payload['body']));
    }

    private function promptVersion(): string
    {
        return config('ai.provider') === 'openai'
            ? config('ai.openai.prompt_version')
            : 'mock-v1';
    }
}
