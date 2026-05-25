<?php

namespace Tests\Feature;

use App\Models\TaskDraft;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailToTaskApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_incoming_email_creates_task_draft(): void
    {
        $response = $this->postJson('/api/incoming-emails', [
            'from' => 'client@acme.com',
            'subject' => 'Checkout bug on mobile',
            'body' => 'Project: Acme Store. The checkout button crashes on iOS. Steps: open cart, tap checkout.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending_review')
            ->assertJsonPath('data.type', 'bug');

        $this->assertDatabaseCount('incoming_emails', 1);
        $this->assertDatabaseCount('task_drafts', 1);
        $this->assertDatabaseCount('ai_evaluations', 1);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $payload = [
            'from' => 'client@acme.com',
            'subject' => 'Duplicate test',
            'body' => 'Same content every time for duplicate detection.',
        ];

        $this->postJson('/api/incoming-emails', $payload)->assertCreated();
        $this->postJson('/api/incoming-emails', $payload)
            ->assertStatus(409)
            ->assertJsonStructure(['existing_email_id']);
    }

    public function test_vague_email_is_rejected(): void
    {
        $this->postJson('/api/incoming-emails', [
            'from' => 'client@acme.com',
            'subject' => 'Help',
            'body' => '???',
        ])->assertStatus(422);
    }

    public function test_ai_failure_is_handled(): void
    {
        $this->postJson('/api/incoming-emails', [
            'from' => 'client@acme.com',
            'subject' => '[AI_FAIL] Provider outage',
            'body' => 'This should trigger a simulated AI failure response.',
        ])->assertStatus(502);
    }

    public function test_review_lifecycle(): void
    {
        $create = $this->postJson('/api/incoming-emails', [
            'from' => 'client@acme.com',
            'subject' => 'Question about billing',
            'body' => 'How do I change the invoice address on my account?',
        ]);

        $draftId = $create->json('data.id');

        $this->getJson("/api/task-drafts/{$draftId}")
            ->assertOk()
            ->assertJsonPath('data.incoming_email.from', 'client@acme.com');

        $this->postJson("/api/task-drafts/{$draftId}/override", [
            'operator_name' => 'Alex PM',
            'fields' => ['priority' => 'high'],
        ])->assertStatus(422);

        $this->postJson("/api/task-drafts/{$draftId}/override", [
            'operator_name' => 'Alex PM',
            'override_reason' => 'Client is enterprise tier.',
            'fields' => ['priority' => 'high'],
            'note' => 'Escalated after call.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'overridden')
            ->assertJsonPath('data.priority', 'high');

        $this->postJson("/api/task-drafts/{$draftId}/approve", [
            'operator_name' => 'Alex PM',
        ])->assertStatus(409);

        $this->assertSame(1, TaskDraft::query()->count());
        $this->assertDatabaseCount('approval_decisions', 1);
        $this->assertDatabaseCount('audit_logs', 3);
    }

    public function test_approve_and_reject_flow(): void
    {
        $draftId = $this->postJson('/api/incoming-emails', [
            'from' => 'pm@unity.dev',
            'subject' => 'Feature: dark mode',
            'body' => 'Project: Portal. Please implement dark mode because users requested it.',
        ])->json('data.id');

        $this->postJson("/api/task-drafts/{$draftId}/approve", [
            'operator_name' => 'Sam PM',
            'note' => 'Ship it.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->postJson("/api/task-drafts/{$draftId}/reject", [
            'operator_name' => 'Sam PM',
        ])->assertStatus(409);
    }
}
