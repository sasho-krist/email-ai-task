<?php

namespace App\Enums;

enum TaskType: string
{
    case Bug = 'bug';
    case FeatureRequest = 'feature_request';
    case Question = 'question';
    case Feedback = 'feedback';
    case Mixed = 'mixed';
    case Unknown = 'unknown';
}
