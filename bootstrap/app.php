<?php

use App\Exceptions\AiEvaluationFailedException;
use App\Exceptions\DuplicateEmailException;
use App\Exceptions\EmailTooVagueException;
use App\Exceptions\OverrideRequiresReasonException;
use App\Exceptions\TaskDraftAlreadyProcessedException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (DuplicateEmailException $exception, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => $exception->getMessage(),
                    'existing_email_id' => $exception->existingEmailId,
                ], 409);
            }
        });

        $exceptions->render(function (AiEvaluationFailedException $exception, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => $exception->getMessage(),
                ], 502);
            }
        });

        $exceptions->render(function (EmailTooVagueException $exception, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => $exception->getMessage(),
                ], 422);
            }
        });

        $exceptions->render(function (TaskDraftAlreadyProcessedException $exception, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => $exception->getMessage(),
                    'current_status' => $exception->currentStatus->value,
                ], 409);
            }
        });

        $exceptions->render(function (OverrideRequiresReasonException $exception, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => $exception->getMessage(),
                ], 422);
            }
        });
    })->create();
