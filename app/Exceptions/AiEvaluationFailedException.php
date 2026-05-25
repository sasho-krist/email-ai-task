<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class AiEvaluationFailedException extends Exception
{
    public function __construct(string $message = 'AI evaluation failed.', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
