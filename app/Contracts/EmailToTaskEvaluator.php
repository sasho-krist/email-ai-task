<?php

namespace App\Contracts;

use App\DTOs\AiEvaluationResult;
use App\Models\IncomingEmail;

interface EmailToTaskEvaluator
{
    public function evaluate(IncomingEmail $email): AiEvaluationResult;
}
