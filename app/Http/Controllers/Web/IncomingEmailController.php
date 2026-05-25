<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\AiEvaluationFailedException;
use App\Exceptions\DuplicateEmailException;
use App\Exceptions\EmailTooVagueException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIncomingEmailRequest;
use App\Services\IncomingEmailService;
use Illuminate\Http\RedirectResponse;

class IncomingEmailController extends Controller
{
    public function __construct(
        private readonly IncomingEmailService $incomingEmailService,
    ) {}

    public function store(StoreIncomingEmailRequest $request): RedirectResponse
    {
        try {
            $draft = $this->incomingEmailService->process($request->validated());
        } catch (DuplicateEmailException $exception) {
            return back()
                ->withInput()
                ->with('error', $exception->getMessage().' (email #'.$exception->existingEmailId.')');
        } catch (EmailTooVagueException $exception) {
            return back()
                ->withInput()
                ->with('error', $exception->getMessage());
        } catch (AiEvaluationFailedException $exception) {
            return back()
                ->withInput()
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('task-drafts.show', $draft)
            ->with('success', 'AI draft created. Please review before approving.');
    }
}
