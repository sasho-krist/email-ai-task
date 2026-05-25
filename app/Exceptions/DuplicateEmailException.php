<?php

namespace App\Exceptions;

use Exception;

class DuplicateEmailException extends Exception
{
    public function __construct(public readonly int $existingEmailId)
    {
        parent::__construct('An identical email has already been processed.');
    }
}
