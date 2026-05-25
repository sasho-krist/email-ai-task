@extends('layouts.app')

@section('title', 'Draft #'.$draft->id.' — ZETA')

@section('content')
<p class="small"><a href="{{ route('dashboard') }}">← Back to dashboard</a></p>

<div class="grid">
    <section class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;margin-bottom:1rem;">
            <h2 style="margin:0;">AI task draft</h2>
            <span class="badge badge-{{ $draft->status->value === 'pending_review' ? 'pending' : $draft->status->value }}">
                {{ str_replace('_', ' ', $draft->status->value) }}
            </span>
        </div>

        <div class="field-grid">
            <div>
                <div class="field-label">Type</div>
                <div class="field-value">{{ str_replace('_', ' ', $draft->type->value) }}</div>
            </div>
            <div>
                <div class="field-label">Priority</div>
                <div class="field-value">{{ ucfirst($draft->priority->value) }}</div>
            </div>
            <div class="full">
                <div class="field-label">Title</div>
                <div class="field-value">{{ $draft->title }}</div>
            </div>
            <div class="full">
                <div class="field-label">Summary</div>
                <div class="field-value">{{ $draft->summary }}</div>
            </div>
            <div>
                <div class="field-label">Suggested project</div>
                <div class="field-value">{{ $draft->suggested_project ?? '—' }}</div>
            </div>
            <div>
                <div class="field-label">Suggested team</div>
                <div class="field-value">{{ $draft->suggested_team ?? '—' }}</div>
            </div>
            <div class="full">
                <div class="field-label">Confidence</div>
                <div class="field-value">{{ number_format($draft->confidence * 100, 1) }}%</div>
                <div class="confidence-bar"><span style="width: {{ $draft->confidence * 100 }}%;"></span></div>
            </div>
            <div class="full">
                <div class="field-label">Missing information</div>
                @if (empty($draft->missing_information))
                    <p class="muted">None flagged by AI.</p>
                @else
                    <ul class="pill-list">
                        @foreach ($draft->missing_information as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
            <div class="full">
                <div class="field-label">Suggested next action</div>
                <div class="field-value">{{ $draft->suggested_next_action }}</div>
            </div>
        </div>

        @if ($draft->aiEvaluation)
            <p class="small muted" style="margin-top:1.25rem;">
                Provider: {{ $draft->aiEvaluation->provider }}
                · {{ $draft->aiEvaluation->processing_time_ms }} ms
            </p>
        @endif
    </section>

    <div>
        <section class="card" style="margin-bottom:1.5rem;">
            <h2>Source email</h2>
            <div class="field-grid">
                <div class="full">
                    <div class="field-label">From</div>
                    <div class="field-value">{{ $draft->incomingEmail->from }}</div>
                </div>
                <div class="full">
                    <div class="field-label">Subject</div>
                    <div class="field-value">{{ $draft->incomingEmail->subject }}</div>
                </div>
                <div class="full">
                    <div class="field-label">Body</div>
                    <div class="field-value" style="white-space:pre-wrap;">{{ $draft->incomingEmail->body }}</div>
                </div>
            </div>
        </section>

        @if ($draft->isPendingReview())
            <section class="card actions">
                <h2>Human review</h2>

                <form method="POST" action="{{ route('task-drafts.approve', $draft) }}">
                    @csrf
                    <label for="approve_operator">Your name</label>
                    <input type="text" id="approve_operator" name="operator_name" value="{{ old('operator_name', 'PM Operator') }}" required>
                    <label for="approve_note">Note (optional)</label>
                    <textarea id="approve_note" name="note" rows="2">{{ old('note') }}</textarea>
                    <button type="submit" class="btn btn-success btn-block">Approve draft</button>
                </form>

                <form method="POST" action="{{ route('task-drafts.reject', $draft) }}">
                    @csrf
                    <label for="reject_operator">Your name</label>
                    <input type="text" id="reject_operator" name="operator_name" value="{{ old('operator_name', 'PM Operator') }}" required>
                    <label for="reject_note">Rejection note</label>
                    <textarea id="reject_note" name="note" rows="2">{{ old('note') }}</textarea>
                    <button type="submit" class="btn btn-danger btn-block">Reject draft</button>
                </form>

                <form method="POST" action="{{ route('task-drafts.override', $draft) }}">
                    @csrf
                    <h3 style="margin:0 0 0.75rem;font-size:1rem;">Override fields</h3>

                    <label for="override_operator">Your name</label>
                    <input type="text" id="override_operator" name="operator_name" value="{{ old('operator_name', 'PM Operator') }}" required>

                    <div class="field-grid">
                        <div>
                            <label for="fields_type">Type</label>
                            <select id="fields_type" name="fields[type]">
                                <option value="">Keep AI value</option>
                                @foreach (['bug','feature_request','question','feedback','mixed','unknown'] as $type)
                                    <option value="{{ $type }}" @selected(old('fields.type') === $type)>{{ str_replace('_', ' ', $type) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="fields_priority">Priority</label>
                            <select id="fields_priority" name="fields[priority]">
                                <option value="">Keep AI value</option>
                                @foreach (['low','medium','high','critical'] as $priority)
                                    <option value="{{ $priority }}" @selected(old('fields.priority') === $priority)>{{ ucfirst($priority) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="full">
                            <label for="fields_title">Title</label>
                            <input type="text" id="fields_title" name="fields[title]" value="{{ old('fields.title') }}" placeholder="{{ $draft->title }}">
                        </div>
                        <div class="full">
                            <label for="fields_summary">Summary</label>
                            <textarea id="fields_summary" name="fields[summary]" rows="3" placeholder="{{ Str::limit($draft->summary, 80) }}">{{ old('fields.summary') }}</textarea>
                        </div>
                        <div>
                            <label for="fields_project">Suggested project</label>
                            <input type="text" id="fields_project" name="fields[suggested_project]" value="{{ old('fields.suggested_project') }}">
                        </div>
                        <div>
                            <label for="fields_team">Suggested team</label>
                            <input type="text" id="fields_team" name="fields[suggested_team]" value="{{ old('fields.suggested_team') }}">
                        </div>
                        <div class="full">
                            <label for="fields_next_action">Suggested next action</label>
                            <textarea id="fields_next_action" name="fields[suggested_next_action]" rows="2">{{ old('fields.suggested_next_action') }}</textarea>
                        </div>
                    </div>

                    <label for="override_reason">Override reason (required if changing fields)</label>
                    <textarea id="override_reason" name="override_reason" rows="2">{{ old('override_reason') }}</textarea>

                    <label for="override_note">Note (optional)</label>
                    <textarea id="override_note" name="note" rows="2">{{ old('note') }}</textarea>

                    <button type="submit" class="btn btn-secondary btn-block">Save override</button>
                </form>
            </section>
        @else
            <section class="card">
                <h2>Decision recorded</h2>
                @if ($draft->latestApprovalDecision)
                    <p><strong>{{ ucfirst($draft->latestApprovalDecision->action->value) }}</strong> by {{ $draft->latestApprovalDecision->operator_name }}</p>
                    @if ($draft->latestApprovalDecision->note)
                        <p class="muted">{{ $draft->latestApprovalDecision->note }}</p>
                    @endif
                    @if ($draft->latestApprovalDecision->override_reason)
                        <p class="small"><strong>Reason:</strong> {{ $draft->latestApprovalDecision->override_reason }}</p>
                    @endif
                @endif
            </section>
        @endif
    </div>
</div>
@endsection
