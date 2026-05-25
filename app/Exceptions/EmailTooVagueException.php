<?php

namespace App\Exceptions;

use Exception;

class EmailTooVagueException extends Exception
{
    public function __construct()
    {
        parent::__construct('Email content is too vague to produce a reliable task draft.');
    }
}
