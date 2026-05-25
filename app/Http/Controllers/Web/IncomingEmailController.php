<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\HandlesEmailToTaskExceptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIncomingEmailRequest;
use App\Services\IncomingEmailService;
use Illuminate\Http\RedirectResponse;

class IncomingEmailController extends Controller
{
    use HandlesEmailToTaskExceptions;

    public function __construct(
        private readonly IncomingEmailService $incomingEmailService,
    ) {}

    public function store(StoreIncomingEmailRequest $request): RedirectResponse
    {
        $result = $this->handleIncomingEmailAction(function () use ($request) {
            $draft = $this->incomingEmailService->process($request->validated());

            return redirect()
                ->route('task-drafts.show', $draft)
                ->with('success', 'AI draft created. Please review before approving.');
        });

        return $result instanceof RedirectResponse ? $result : back()->with('error', 'Unexpected error.');
    }
}
