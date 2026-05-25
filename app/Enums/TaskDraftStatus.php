<?php

namespace App\Enums;

enum TaskDraftStatus: string
{
    case PendingReview = 'pending_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Overridden = 'overridden';
}
