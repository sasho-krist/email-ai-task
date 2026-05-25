<?php

namespace Tests\Support;

use App\Enums\IncomingEmailStatus;
use App\Enums\TaskDraftStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskType;
use App\Models\IncomingEmail;
use App\Models\TaskDraft;

trait CreatesEmailFixtures
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeIncomingEmail(array $overrides = []): IncomingEmail
    {
        return new IncomingEmail(array_merge([
            'from' => 'client@acme.com',
            'subject' => 'Checkout bug on mobile',
            'body' => 'Project: Acme Store. The checkout button crashes on iOS. Steps: open cart, tap checkout.',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $emailOverrides
     * @param  array<string, mixed>  $draftOverrides
     */
    protected function createPendingDraft(array $emailOverrides = [], array $draftOverrides = []): TaskDraft
    {
        $email = IncomingEmail::query()->create(array_merge([
            'from' => 'client@acme.com',
            'subject' => 'Feature request',
            'body' => 'Project: Portal. Please add dark mode because users requested it.',
            'content_hash' => hash('sha256', uniqid('', true)),
            'status' => IncomingEmailStatus::Processed,
        ], $emailOverrides));

        return TaskDraft::query()->create(array_merge([
            'incoming_email_id' => $email->id,
            'type' => TaskType::FeatureRequest,
            'title' => 'Feature request',
            'summary' => 'Client asked for dark mode.',
            'priority' => TaskPriority::Medium,
            'suggested_project' => 'Portal',
            'suggested_team' => 'Product',
            'confidence' => 0.82,
            'missing_information' => [],
            'suggested_next_action' => 'Schedule product review.',
            'status' => TaskDraftStatus::PendingReview,
        ], $draftOverrides));
    }
}
