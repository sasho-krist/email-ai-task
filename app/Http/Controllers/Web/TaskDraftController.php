<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\OverrideRequiresReasonException;
use App\Exceptions\TaskDraftAlreadyProcessedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\OverrideTaskDraftRequest;
use App\Http\Requests\ReviewTaskDraftRequest;
use App\Models\TaskDraft;
use App\Services\TaskDraftReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TaskDraftController extends Controller
{
    public function __construct(
        private readonly TaskDraftReviewService $reviewService,
    ) {}

    public function show(TaskDraft $taskDraft): View
    {
        $taskDraft->load(['incomingEmail', 'aiEvaluation', 'approvalDecisions']);

        return view('task-drafts.show', [
            'draft' => $taskDraft,
        ]);
    }

    public function approve(TaskDraft $taskDraft, ReviewTaskDraftRequest $request): RedirectResponse
    {
        try {
            $this->reviewService->approve(
                $taskDraft,
                $request->validated('operator_name'),
                $request->validated('note'),
            );
        } catch (TaskDraftAlreadyProcessedException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('task-drafts.show', $taskDraft)
            ->with('success', 'Draft approved.');
    }

    public function reject(TaskDraft $taskDraft, ReviewTaskDraftRequest $request): RedirectResponse
    {
        try {
            $this->reviewService->reject(
                $taskDraft,
                $request->validated('operator_name'),
                $request->validated('note'),
            );
        } catch (TaskDraftAlreadyProcessedException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('task-drafts.show', $taskDraft)
            ->with('success', 'Draft rejected.');
    }

    public function override(TaskDraft $taskDraft, OverrideTaskDraftRequest $request): RedirectResponse
    {
        try {
            $this->reviewService->override(
                $taskDraft,
                $request->validated('operator_name'),
                $request->validated('fields', []),
                $request->validated('override_reason'),
                $request->validated('note'),
            );
        } catch (OverrideRequiresReasonException|TaskDraftAlreadyProcessedException $exception) {
            return back()
                ->withInput()
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('task-drafts.show', $taskDraft)
            ->with('success', 'Draft overridden and saved.');
    }
}
