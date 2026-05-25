# ZETA — Email-to-Task Assistant

**ZETA** is a small Laravel feature for Perspective Unity's internal PM workflow. Client emails arrive as unstructured text; the system uses AI to propose a **structured task draft**. A human operator always reviews the suggestion before anything is treated as final — the system never auto-creates tasks.

The app exposes both a **web UI** (for operators) and a **JSON API** (for integrations). AI is powered by **ChatGPT** today, but the architecture keeps provider logic behind an interface so it can be swapped without touching controllers.

---

## Repository

```bash
git clone git@github.com:sasho-krist/email-ai-task.git
cd email-ai-task
```

## Quick start

```bash
composer install
cp .env.example .env   # if needed
php artisan key:generate
```

Configure `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=email_ai
DB_USERNAME=root
DB_PASSWORD=

AI_PROVIDER=openai
OPENAI_API_KEY=sk-your-key-here
OPENAI_MODEL=gpt-4o-mini
```

Create the MySQL database (`email_ai`), then:

```bash
php artisan migrate
php artisan serve
```

| Surface | URL |
|---------|-----|
| Web UI | http://127.0.0.1:8000 |
| REST API | http://127.0.0.1:8000/api |

> **Note:** If port 8000 shows the default Laravel welcome page, another app is bound to that port. Run `php artisan serve` from `c:\wamp64\www\ai_email`, or use `--port=8001`.

Set `AI_PROVIDER=mock` to run without an OpenAI key (tests use this automatically).

---

## Architecture

Two HTTP surfaces share the same service layer. Controllers validate input and delegate; business rules live in services; AI is isolated behind an interface.

```
                    ┌─────────────────────────────────────┐
                    │           Web UI (Blade)            │
                    │  /  ·  /emails  ·  /task-drafts/*   │
                    └─────────────────┬───────────────────┘
                                      │
                    ┌─────────────────▼───────────────────┐
                    │         REST API (/api/*)           │
                    │  incoming-emails · task-drafts/*    │
                    └─────────────────┬───────────────────┘
                                      │
                                      ▼
                         IncomingEmailService
                                      │
              ┌───────────────────────┼───────────────────────┐
              ▼                       ▼                       ▼
       IncomingEmail          EmailToTaskEvaluator       AuditLogger
       (persist raw)              (interface)            (append-only)
              │                       │
              │              ┌────────┴────────┐
              │              ▼                 ▼
              │     OpenAiEmailToTaskEvaluator  MockEmailToTaskEvaluator
              │              (ChatGPT)           (heuristics / tests)
              ▼
         TaskDraft ◄── AiEvaluation
              │
              ▼
    TaskDraftReviewService
    approve · reject · override
              │
              ▼
    ApprovalDecision + AuditLog
```

**Key decisions**

| Decision | Why |
|----------|-----|
| Thin controllers | HTTP concerns stay at the edge; services own transactions and rules. |
| Shared services for web + API | Same behaviour regardless of how the operator interacts. |
| `EmailToTaskEvaluator` interface | Provider swap is a container binding change, not a controller rewrite. |
| Separate `ai_evaluations` table | Raw prompts/responses and timing are auditable without polluting the draft. |
| Append-only `approval_decisions` | Human actions are a history, not silent overwrites. |
| Content hash on emails | Exact duplicate detection before paying for AI. |
| No auto-approval | Drafts stay `pending_review` until a human acts. |

**Layer map**

| Layer | Responsibility |
|-------|----------------|
| `app/Http/Controllers/Web/*` | Blade UI, redirects, flash messages |
| `app/Http/Controllers/Api/*` | JSON responses, HTTP status codes |
| `app/Services/IncomingEmailService.php` | Ingest email, call AI, persist draft |
| `app/Services/TaskDraftReviewService.php` | Approve, reject, override with guards |
| `app/Contracts/EmailToTaskEvaluator.php` | AI abstraction |
| `app/Services/Ai/*` | Concrete providers |

---

## Data model

Five domain tables plus Laravel defaults (users, sessions, jobs, cache).

### Entity overview

| Table | Purpose |
|-------|---------|
| `incoming_emails` | Raw email payload, processing status, duplicate hash |
| `task_drafts` | AI-suggested structured fields awaiting human review |
| `ai_evaluations` | Provider, prompt version, raw request/response, latency |
| `approval_decisions` | Operator action, notes, override payload |
| `audit_logs` | Cross-entity audit trail |

### Relationships

```
IncomingEmail 1──1 TaskDraft 1──* ApprovalDecision
      │                │
      └──* AiEvaluation ┘
```

### `incoming_emails`

| Column | Notes |
|--------|-------|
| `from`, `subject`, `body` | Email-like input |
| `content_hash` | SHA-256 of normalized from + subject + body (unique) |
| `status` | `received` → `processing` → `processed` \| `failed` |
| `failure_reason` | Set when AI or validation fails |

### `task_drafts`

| Column | Notes |
|--------|-------|
| `type` | `bug`, `feature_request`, `question`, `feedback`, `mixed`, `unknown` |
| `title`, `summary` | Human-readable task headline and context |
| `priority` | `low`, `medium`, `high`, `critical` |
| `suggested_project`, `suggested_team` | Routing hints (nullable) |
| `confidence` | 0.0–1.0 — how sure the AI is |
| `missing_information` | JSON array of gaps to clarify with the sender |
| `suggested_next_action` | Recommended PM step |
| `status` | `pending_review` → `approved` \| `rejected` \| `overridden` |

### `ai_evaluations`

Stores one row per evaluation attempt. Successful runs link to both `incoming_email_id` and `task_draft_id`. Failed runs may exist without a draft.

### `approval_decisions`

| Column | Notes |
|--------|-------|
| `action` | `approved`, `rejected`, `overridden` |
| `operator_name` | Who acted (no auth yet — plain string) |
| `note` | Optional comment |
| `override_fields` | JSON diff applied on override |
| `override_reason` | Required when fields change |

### `audit_logs`

Polymorphic-style log: `entity_type`, `entity_id`, `action`, `actor`, `payload` (JSON).

---

## AI abstraction

All AI logic lives behind one interface. Controllers and services never call OpenAI directly.

```php
interface EmailToTaskEvaluator
{
    public function evaluate(IncomingEmail $email): AiEvaluationResult;
}
```

`AiEvaluationResult` wraps a `TaskDraftSuggestion` DTO plus provider metadata (`provider`, `promptVersion`, `rawRequest`, `rawResponse`, `processingTimeMs`).

### Provider binding

Configured in `config/ai.php` and bound in `AppServiceProvider`:

```php
// .env: AI_PROVIDER=openai | mock
match (config('ai.provider')) {
    'openai' => OpenAiEmailToTaskEvaluator::class,
    'mock'   => MockEmailToTaskEvaluator::class,
};
```

### OpenAI (production default)

`OpenAiEmailToTaskEvaluator` calls the Chat Completions API with `response_format: json_object`, parses the JSON into `TaskDraftSuggestion`, and throws domain exceptions when the model flags `too_vague: true` or when the API fails.

Config via `.env`:

```env
AI_PROVIDER=openai
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4o-mini
```

### Mock (tests and offline dev)

`MockEmailToTaskEvaluator` uses keyword heuristics. Useful for CI and failure-scenario demos without API cost:

| Input signal | Result |
|--------------|--------|
| Subject contains `[AI_FAIL]` | Simulated provider failure (502) |
| Body &lt; 15 chars or `help` / `???` | Email too vague (422) |
| Identical from + subject + body | Duplicate (409) |

### Adding another provider

1. Implement `EmailToTaskEvaluator`.
2. Register in `AppServiceProvider` match expression.
3. Add config block in `config/ai.php`.

No controller changes required.

---

## Human approval flow

The system produces a **draft**, not a task. An operator must explicitly decide.

```
Email submitted
      │
      ▼
AI evaluation ──fail──► incoming_email.status = failed
      │
      ▼
task_draft.status = pending_review
      │
      ├── Approve ──► status = approved
      ├── Reject  ──► status = rejected
      └── Override ──► edit fields + reason ──► status = overridden
      │
      ▼
approval_decisions row + audit_logs entry
```

### Web UI flow

1. Open `/` — compose email form + list of recent drafts.
2. Submit → redirected to `/task-drafts/{id}` with full structured output.
3. Review source email alongside AI fields.
4. Choose **Approve**, **Reject**, or **Override** (with `override_reason` when changing fields).
5. Once decided, review forms are hidden; decision is shown read-only.

### API flow

Same rules via JSON endpoints:

| Method | Endpoint |
|--------|----------|
| `POST` | `/api/incoming-emails` |
| `GET` | `/api/task-drafts/{id}` |
| `POST` | `/api/task-drafts/{id}/approve` |
| `POST` | `/api/task-drafts/{id}/reject` |
| `POST` | `/api/task-drafts/{id}/override` |

Attempting a second action on a non-pending draft returns **409** with `current_status`.

---

## Failure handling

| Scenario | HTTP | Behaviour |
|----------|------|-----------|
| Missing/invalid fields | 422 | Form request validation |
| Duplicate email | 409 | `existing_email_id` returned (API) |
| OpenAI / network failure | 502 | Email marked `failed`; failed `ai_evaluation` stored |
| Email too vague (AI flag) | 422 | Email marked `failed` with reason |
| Approve/reject/override twice | 409 | `TaskDraftAlreadyProcessedException` |
| Override fields without reason | 422 | `OverrideRequiresReasonException` |
| Missing `OPENAI_API_KEY` | 502 | Clear config error before API call |

Web UI surfaces the same errors as flash messages and preserves form input.

---

## Trade-offs & simplifications

Deliberate scope cuts for a 2–3 hour exercise focused on **decisions**, not completeness:

- **No authentication** — `operator_name` is a free-text field. Production needs Sanctum/SSO and user FK on decisions.
- **Synchronous AI** — ChatGPT runs inline in the request. Acceptable for low volume; high volume needs queued jobs with retries and idempotency keys.
- **Exact duplicate detection** — hash of from + subject + body. No email-thread or fuzzy dedup.
- **One draft per email** — no re-evaluation endpoint; a bad AI run requires a new email or manual override.
- **Basic Blade UI** — functional operator console, not a polished product surface. API remains the integration point.
- **Prompt in code** — OpenAI system prompt lives in the evaluator class, not external templates or a prompt registry.
- **MySQL on WAMP** — local dev target; migrations adjusted for WAMP index length limits (`Schema::defaultStringLength(191)`).
- **No outbound actions** — approving a draft does not create a Jira/ClickUp ticket; it only records the decision.

---

## What to improve next

1. **Queued AI evaluation** — async job with retry/backoff, dead-letter queue, and webhook on completion.
2. **Prompt management** — versioned prompts in DB or config; A/B testing across `prompt_version` values.
3. **Operator auth** — login, RBAC, tie `approval_decisions` to real user IDs.
4. **Inbound ingestion** — IMAP polling or webhook instead of manual form/API POST.
5. **Re-run AI** — new evaluation row while preserving history; operator picks which suggestion to keep.
6. **Task system integration** — on approve, push to Jira/Linear/ClickUp with mapped fields.
7. **OpenAPI spec** — machine-readable contract for the REST API.
8. **Richer UI** — draft diff view (AI vs override), filters, pagination, audit timeline.
9. **Observability** — metrics on confidence distribution, AI latency, override rate, vague-email rate.

---

## Example API session

```bash
# 1. Submit email
curl -s -X POST http://127.0.0.1:8000/api/incoming-emails \
  -H "Content-Type: application/json" \
  -d "{\"from\":\"dev@client.com\",\"subject\":\"Feature: export CSV\",\"body\":\"Project: Reporting\\nPlease add CSV export to the dashboard. We need this for monthly board reports.\"}"

# 2. Approve (replace {id})
curl -s -X POST http://127.0.0.1:8000/api/task-drafts/1/approve \
  -H "Content-Type: application/json" \
  -d "{\"operator_name\":\"Sam PM\",\"note\":\"Approved for sprint planning.\"}"
```

---

Built for Perspective Unity — **AI suggests, humans decide.**
