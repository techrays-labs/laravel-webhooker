@extends('webhooker::layout')

@section('content')
<h2 style="margin-bottom: 20px;">Event #{{ $webhookEvent->id }}</h2>

<div class="card">
    <div class="detail-row">
        <span class="detail-label">Event Name</span>
        <span class="detail-value">{{ $webhookEvent->event_name }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Endpoint</span>
        <span class="detail-value">{{ $webhookEvent->endpoint->name ?? 'N/A' }} ({{ $webhookEvent->endpoint->url ?? '' }})</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Status</span>
        <span class="detail-value"><span class="badge badge-{{ $webhookEvent->status }}">{{ $webhookEvent->status }}</span></span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Attempts</span>
        <span class="detail-value">{{ $webhookEvent->attempts_count }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Created</span>
        <span class="detail-value">{{ $webhookEvent->created_at->toDateTimeString() }}</span>
    </div>
    @if($webhookEvent->last_attempt_at)
    <div class="detail-row">
        <span class="detail-label">Last Attempt</span>
        <span class="detail-value">{{ $webhookEvent->last_attempt_at->toDateTimeString() }}</span>
    </div>
    @endif
    @if($webhookEvent->next_retry_at)
    <div class="detail-row">
        <span class="detail-label">Next Retry</span>
        <span class="detail-value">{{ $webhookEvent->next_retry_at->toDateTimeString() }}</span>
    </div>
    @endif
</div>

<div class="card">
    <h3 style="margin-bottom: 10px;">Payload</h3>
    <pre>{{ json_encode($webhookEvent->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
</div>

@if($attempts->isNotEmpty())
<div class="card">
    <h3 style="margin-bottom: 10px;">Delivery Attempts</h3>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Status</th>
                <th>Duration</th>
                <th>Error</th>
                <th>Attempted At</th>
            </tr>
        </thead>
        <tbody>
            @foreach($attempts as $index => $attempt)
            <tr>
                <td>{{ $attempts->count() - $index }}</td>
                <td>
                    @if($attempt->response_status)
                        <span class="badge {{ $attempt->isSuccessful() ? 'badge-delivered' : 'badge-failed' }}">
                            {{ $attempt->response_status }}
                        </span>
                    @else
                        <span class="badge badge-failed">Error</span>
                    @endif
                </td>
                <td>{{ $attempt->duration_ms }}ms</td>
                <td>{{ $attempt->error_message ? \Illuminate\Support\Str::limit($attempt->error_message, 80) : '-' }}</td>
                <td>{{ $attempt->attempted_at->toDateTimeString() }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

<a href="{{ route('webhooker.events.index') }}" class="btn btn-primary">&larr; Back to Events</a>
@endsection
