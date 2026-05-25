<?php

namespace App\Exceptions;

use App\Enums\TaskDraftStatus;
use Exception;

class TaskDraftAlreadyProcessedException extends Exception
{
    public function __construct(public readonly TaskDraftStatus $currentStatus)
    {
        parent::__construct("Task draft has already been {$currentStatus->value}.");
    }
}
