<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\HandlesEmailToTaskExceptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\OverrideTaskDraftRequest;
use App\Http\Requests\ReviewTaskDraftRequest;
use App\Models\TaskDraft;
use App\Services\TaskDraftReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TaskDraftController extends Controller
{
    use HandlesEmailToTaskExceptions;

    public function __construct(
        private readonly TaskDraftReviewService $reviewService,
    ) {}

    public function show(TaskDraft $taskDraft): View|RedirectResponse
    {
        try {
            $taskDraft->load(['incomingEmail', 'aiEvaluation', 'approvalDecisions']);

            return view('task-drafts.show', [
                'draft' => $taskDraft,
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()
                ->route('dashboard')
                ->with('error', 'Unable to load task draft.');
        }
    }

    public function approve(TaskDraft $taskDraft, ReviewTaskDraftRequest $request): RedirectResponse
    {
        $result = $this->handleReviewAction(function () use ($taskDraft, $request) {
            $this->reviewService->approve(
                $taskDraft,
                $request->validated('operator_name'),
                $request->validated('note'),
            );

            return redirect()
                ->route('task-drafts.show', $taskDraft)
                ->with('success', 'Draft approved.');
        });

        return $result instanceof RedirectResponse ? $result : back()->with('error', 'Unexpected error.');
    }

    public function reject(TaskDraft $taskDraft, ReviewTaskDraftRequest $request): RedirectResponse
    {
        $result = $this->handleReviewAction(function () use ($taskDraft, $request) {
            $this->reviewService->reject(
                $taskDraft,
                $request->validated('operator_name'),
                $request->validated('note'),
            );

            return redirect()
                ->route('task-drafts.show', $taskDraft)
                ->with('success', 'Draft rejected.');
        });

        return $result instanceof RedirectResponse ? $result : back()->with('error', 'Unexpected error.');
    }

    public function override(TaskDraft $taskDraft, OverrideTaskDraftRequest $request): RedirectResponse
    {
        $result = $this->handleReviewAction(function () use ($taskDraft, $request) {
            $this->reviewService->override(
                $taskDraft,
                $request->validated('operator_name'),
                $request->validated('fields', []),
                $request->validated('override_reason'),
                $request->validated('note'),
            );

            return redirect()
                ->route('task-drafts.show', $taskDraft)
                ->with('success', 'Draft overridden and saved.');
        });

        return $result instanceof RedirectResponse ? $result : back()->with('error', 'Unexpected error.');
    }
}
