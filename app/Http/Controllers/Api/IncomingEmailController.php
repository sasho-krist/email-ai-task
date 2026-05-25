<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\HandlesEmailToTaskExceptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIncomingEmailRequest;
use App\Http\Resources\TaskDraftResource;
use App\Services\IncomingEmailService;
use Illuminate\Http\JsonResponse;

class IncomingEmailController extends Controller
{
    use HandlesEmailToTaskExceptions;

    public function __construct(
        private readonly IncomingEmailService $incomingEmailService,
    ) {}

    public function store(StoreIncomingEmailRequest $request): JsonResponse
    {
        $result = $this->handleIncomingEmailAction(function () use ($request) {
            $draft = $this->incomingEmailService->process($request->validated());

            return (new TaskDraftResource($draft))
                ->response()
                ->setStatusCode(201);
        }, json: true);

        return $result instanceof JsonResponse ? $result : response()->json(['message' => 'Unexpected response.'], 500);
    }
}
