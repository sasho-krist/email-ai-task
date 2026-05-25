@extends('layouts.app')

@section('title', 'ZETA — Dashboard')

@section('content')
<div class="grid">
    <section class="card">
        <h2>New incoming email</h2>
        <form method="POST" action="{{ route('emails.store') }}">
            @csrf

            <label for="from">From</label>
            <input type="email" id="from" name="from" value="{{ old('from', 'client@acme.com') }}" required>

            @error('from')
                <p class="alert alert-error small">{{ $message }}</p>
            @enderror

            <label for="subject">Subject</label>
            <input type="text" id="subject" name="subject" value="{{ old('subject') }}" required placeholder="Checkout bug on mobile">

            @error('subject')
                <p class="alert alert-error small">{{ $message }}</p>
            @enderror

            <label for="body">Body</label>
            <textarea id="body" name="body" required placeholder="Project: Acme Store&#10;Describe the issue or request...">{{ old('body') }}</textarea>

            @error('body')
                <p class="alert alert-error small">{{ $message }}</p>
            @enderror

            <button type="submit" class="btn btn-primary btn-block">Analyze with ChatGPT</button>
        </form>
    </section>

    <section class="card">
        <h2>Recent task drafts</h2>

        @if ($drafts->isEmpty())
            <p class="muted">No drafts yet. Submit an email to generate the first AI suggestion.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($drafts as $draft)
                        <tr>
                            <td>#{{ $draft->id }}</td>
                            <td>{{ Str::limit($draft->title, 40) }}</td>
                            <td>{{ str_replace('_', ' ', $draft->type->value) }}</td>
                            <td>
                                <span class="badge badge-{{ $draft->status->value === 'pending_review' ? 'pending' : $draft->status->value }}">
                                    {{ str_replace('_', ' ', $draft->status->value) }}
                                </span>
                            </td>
                            <td><a href="{{ route('task-drafts.show', $draft) }}">Review →</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>
</div>
@endsection
