<?php

namespace App\Exceptions;

use Exception;

class OverrideRequiresReasonException extends Exception
{
    public function __construct()
    {
        parent::__construct('Override requires a reason when changing draft fields.');
    }
}
