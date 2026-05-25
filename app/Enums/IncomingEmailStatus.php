<?php

namespace App\Enums;

enum IncomingEmailStatus: string
{
    case Received = 'received';
    case Processing = 'processing';
    case Processed = 'processed';
    case Failed = 'failed';
}
