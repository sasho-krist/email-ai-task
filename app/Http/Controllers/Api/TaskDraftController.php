<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OverrideTaskDraftRequest;
use App\Http\Requests\ReviewTaskDraftRequest;
use App\Http\Resources\TaskDraftResource;
use App\Models\TaskDraft;
use App\Services\TaskDraftReviewService;
use Illuminate\Http\JsonResponse;

class TaskDraftController extends Controller
{
    public function __construct(
        private readonly TaskDraftReviewService $reviewService,
    ) {}

    public function show(TaskDraft $taskDraft): TaskDraftResource
    {
        return new TaskDraftResource(
            $taskDraft->load(['incomingEmail', 'aiEvaluation', 'approvalDecisions'])
        );
    }

    public function approve(TaskDraft $taskDraft, ReviewTaskDraftRequest $request): TaskDraftResource
    {
        $draft = $this->reviewService->approve(
            $taskDraft,
            $request->validated('operator_name'),
            $request->validated('note'),
        );

        return new TaskDraftResource($draft);
    }

    public function reject(TaskDraft $taskDraft, ReviewTaskDraftRequest $request): TaskDraftResource
    {
        $draft = $this->reviewService->reject(
            $taskDraft,
            $request->validated('operator_name'),
            $request->validated('note'),
        );

        return new TaskDraftResource($draft);
    }

    public function override(TaskDraft $taskDraft, OverrideTaskDraftRequest $request): JsonResponse|TaskDraftResource
    {
        $draft = $this->reviewService->override(
            $taskDraft,
            $request->validated('operator_name'),
            $request->validated('fields', []),
            $request->validated('override_reason'),
            $request->validated('note'),
        );

        return new TaskDraftResource($draft);
    }
}
