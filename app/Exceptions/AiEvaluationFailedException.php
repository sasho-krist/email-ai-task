<?php

namespace App\Exceptions;

use Exception;

class AiEvaluationFailedException extends Exception
{
    public function __construct(string $message = 'AI evaluation failed.')
    {
        parent::__construct($message);
    }
}
