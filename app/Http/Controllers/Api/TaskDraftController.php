<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\HandlesEmailToTaskExceptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\OverrideTaskDraftRequest;
use App\Http\Requests\ReviewTaskDraftRequest;
use App\Http\Resources\TaskDraftResource;
use App\Models\TaskDraft;
use App\Services\TaskDraftReviewService;
use Illuminate\Http\JsonResponse;

class TaskDraftController extends Controller
{
    use HandlesEmailToTaskExceptions;

    public function __construct(
        private readonly TaskDraftReviewService $reviewService,
    ) {}

    public function show(TaskDraft $taskDraft): TaskDraftResource|JsonResponse
    {
        try {
            return new TaskDraftResource(
                $taskDraft->load(['incomingEmail', 'aiEvaluation', 'approvalDecisions'])
            );
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Unable to load task draft.',
            ], 500);
        }
    }

    public function approve(TaskDraft $taskDraft, ReviewTaskDraftRequest $request): TaskDraftResource|JsonResponse
    {
        $result = $this->handleReviewAction(function () use ($taskDraft, $request) {
            $draft = $this->reviewService->approve(
                $taskDraft,
                $request->validated('operator_name'),
                $request->validated('note'),
            );

            return new TaskDraftResource($draft);
        }, json: true);

        return $result instanceof JsonResponse ? $result : $result;
    }

    public function reject(TaskDraft $taskDraft, ReviewTaskDraftRequest $request): TaskDraftResource|JsonResponse
    {
        $result = $this->handleReviewAction(function () use ($taskDraft, $request) {
            $draft = $this->reviewService->reject(
                $taskDraft,
                $request->validated('operator_name'),
                $request->validated('note'),
            );

            return new TaskDraftResource($draft);
        }, json: true);

        return $result instanceof JsonResponse ? $result : $result;
    }

    public function override(TaskDraft $taskDraft, OverrideTaskDraftRequest $request): TaskDraftResource|JsonResponse
    {
        $result = $this->handleReviewAction(function () use ($taskDraft, $request) {
            $draft = $this->reviewService->override(
                $taskDraft,
                $request->validated('operator_name'),
                $request->validated('fields', []),
                $request->validated('override_reason'),
                $request->validated('note'),
            );

            return new TaskDraftResource($draft);
        }, json: true);

        return $result instanceof JsonResponse ? $result : $result;
    }
}
