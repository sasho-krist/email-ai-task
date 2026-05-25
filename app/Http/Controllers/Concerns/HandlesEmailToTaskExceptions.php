<?php

namespace App\Http\Controllers\Concerns;

use App\Exceptions\AiEvaluationFailedException;
use App\Exceptions\DuplicateEmailException;
use App\Exceptions\EmailTooVagueException;
use App\Exceptions\OverrideRequiresReasonException;
use App\Exceptions\TaskDraftAlreadyProcessedException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Throwable;

trait HandlesEmailToTaskExceptions
{
    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T|RedirectResponse|JsonResponse
     */
    protected function handleIncomingEmailAction(callable $callback, bool $json = false): mixed
    {
        try {
            return $callback();
        } catch (DuplicateEmailException $exception) {
            return $this->respondWithError(
                $exception->getMessage(),
                409,
                $json,
                preserveInput: ! $json,
                extra: ['existing_email_id' => $exception->existingEmailId],
            );
        } catch (EmailTooVagueException|AiEvaluationFailedException $exception) {
            return $this->respondWithError(
                $exception->getMessage(),
                $exception instanceof AiEvaluationFailedException ? 502 : 422,
                $json,
                preserveInput: ! $json,
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->respondWithError(
                'An unexpected error occurred while processing the email.',
                500,
                $json,
                preserveInput: ! $json,
            );
        }
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T|RedirectResponse|JsonResponse
     */
    protected function handleReviewAction(callable $callback, bool $json = false): mixed
    {
        try {
            return $callback();
        } catch (TaskDraftAlreadyProcessedException $exception) {
            return $this->respondWithError(
                $exception->getMessage(),
                409,
                $json,
                extra: ['current_status' => $exception->currentStatus->value],
            );
        } catch (OverrideRequiresReasonException $exception) {
            return $this->respondWithError(
                $exception->getMessage(),
                422,
                $json,
                preserveInput: ! $json,
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->respondWithError(
                'An unexpected error occurred while saving the review decision.',
                500,
                $json,
                preserveInput: ! $json,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function respondWithError(
        string $message,
        int $status,
        bool $json,
        bool $preserveInput = false,
        array $extra = [],
    ): RedirectResponse|JsonResponse {
        if ($json) {
            return response()->json([
                'message' => $message,
                ...$extra,
            ], $status);
        }

        $redirect = back()->with('error', $message);

        return $preserveInput ? $redirect->withInput() : $redirect;
    }
}
