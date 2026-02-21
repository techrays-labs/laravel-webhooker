@extends('webhooker::layout')

@section('content')
<div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">
    <h2>Event #{{ $webhookEvent->id }}</h2>
    <span class="badge badge-{{ $webhookEvent->status }}" style="font-size: 14px; padding: 5px 14px;">{{ strtoupper($webhookEvent->status) }}</span>
</div>

<div class="card">
    <div class="detail-row">
        <span class="detail-label">Event Name</span>
        <span class="detail-value">{{ $webhookEvent->event_name }}</span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Endpoint</span>
        <span class="detail-value">
            @if($webhookEvent->endpoint)
                <a href="{{ route('webhooker.endpoints.show', $webhookEvent->endpoint->id) }}" style="color: var(--color-info); text-decoration: none;">
                    {{ $webhookEvent->endpoint->name }}
                </a>
                <span class="text-muted">({{ $webhookEvent->endpoint->url }})</span>
            @else
                N/A
            @endif
        </span>
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
    @if($webhookEvent->idempotency_key)
    <div class="detail-row">
        <span class="detail-label">Idempotency Key</span>
        <span class="detail-value"><code>{{ $webhookEvent->idempotency_key }}</code></span>
    </div>
    @endif

    @if($webhookEvent->status === 'failed' || $webhookEvent->status === 'pending')
    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--color-border);">
        <form method="POST" action="{{ route('webhooker.events.bulk') }}" style="display: inline;">
            @csrf
            <input type="hidden" name="event_ids[]" value="{{ $webhookEvent->id }}">
            <button type="submit" name="action" value="replay" class="btn btn-primary" onclick="return confirm('Replay this event?')">Replay Event</button>
        </form>
    </div>
    @endif
</div>

<div class="card">
    <h3 style="margin-bottom: 10px;">Payload</h3>
    <pre>{{ json_encode($webhookEvent->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
</div>

@if($attempts->isNotEmpty())
<div class="card">
    <h3 style="margin-bottom: 15px;">Delivery Timeline</h3>
    <div class="timeline">
        @foreach($attempts->reverse() as $index => $attempt)
        @php
            $timelineClass = 'error';
            if ($attempt->response_status !== null) {
                $timelineClass = $attempt->isSuccessful() ? 'success' : 'failure';
            }
        @endphp
        <div class="timeline-item {{ $timelineClass }}">
            <div class="timeline-meta">
                Attempt #{{ $index + 1 }} &middot; {{ $attempt->attempted_at->toDateTimeString() }} &middot; {{ $attempt->duration_ms }}ms
            </div>
            <div class="timeline-content">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                    @if($attempt->response_status)
                        <span class="badge {{ $attempt->isSuccessful() ? 'badge-delivered' : 'badge-failed' }}">
                            HTTP {{ $attempt->response_status }}
                        </span>
                    @else
                        <span class="badge badge-failed">Connection Error</span>
                    @endif
                    <span class="text-muted" style="font-size: 12px;">{{ $attempt->duration_ms }}ms</span>
                </div>

                @if($attempt->error_message)
                <div style="margin-bottom: 8px;">
                    <strong style="font-size: 12px; color: var(--color-danger);">Error:</strong>
                    <span style="font-size: 13px;">{{ $attempt->error_message }}</span>
                </div>
                @endif

                @if($attempt->response_body && config('webhooks.store_response_body', true))
                <details>
                    <summary>Response Body</summary>
                    <pre style="margin-top: 8px; font-size: 12px;">{{ \Illuminate\Support\Str::limit($attempt->response_body, 2000) }}</pre>
                </details>
                @endif

                @if($attempt->request_headers && config('webhooks.log_request_headers', false))
                <details>
                    <summary>Request Headers</summary>
                    <pre style="margin-top: 8px; font-size: 12px;">@php
$headers = $attempt->request_headers;
if (is_array($headers)) {
    $masked = $headers;
    foreach (['Authorization', 'X-Webhook-Signature', 'Cookie'] as $sensitive) {
        if (isset($masked[$sensitive])) {
            $masked[$sensitive] = str_repeat('*', 8);
        }
    }
    echo json_encode($masked, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} else {
    echo e((string) $headers);
}
@endphp</pre>
                </details>
                @endif
            </div>
        </div>
        @endforeach
    </div>
</div>
@else
<div class="card">
    <p class="text-muted" style="text-align: center;">No delivery attempts yet.</p>
</div>
@endif

<a href="{{ route('webhooker.events.index') }}" class="btn btn-primary">&larr; Back to Events</a>
@endsection
