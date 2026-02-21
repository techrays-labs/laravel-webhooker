@extends('webhooker::layout')

@section('content')
<div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">
    <h2>{{ $endpointModel->name }}</h2>
    <span class="badge badge-{{ $health->status }}">{{ strtoupper($health->status) }}</span>
    @if($endpointModel->isDisabled())
        <span class="badge badge-disabled">DISABLED</span>
    @elseif($endpointModel->is_active)
        <span class="badge badge-active">ACTIVE</span>
    @else
        <span class="badge badge-inactive">INACTIVE</span>
    @endif
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
    <div class="card">
        <h3 style="margin-bottom: 15px;">Configuration</h3>
        <div class="detail-row">
            <span class="detail-label">Route Token</span>
            <span class="detail-value"><code>{{ $endpointModel->route_token }}</code></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">URL</span>
            <span class="detail-value" style="word-break: break-all;">{{ $endpointModel->url }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Direction</span>
            <span class="detail-value"><span class="badge badge-{{ $endpointModel->direction }}">{{ $endpointModel->direction }}</span></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Timeout</span>
            <span class="detail-value">{{ $endpointModel->timeout_seconds }}s</span>
        </div>
        @if($endpointModel->max_retries !== null)
        <div class="detail-row">
            <span class="detail-label">Max Retries</span>
            <span class="detail-value">{{ $endpointModel->max_retries }}</span>
        </div>
        @endif
        @if($endpointModel->rate_limit_per_minute !== null)
        <div class="detail-row">
            <span class="detail-label">Rate Limit</span>
            <span class="detail-value">{{ $endpointModel->rate_limit_per_minute }}/min</span>
        </div>
        @endif
        @if($endpointModel->tags->count() > 0)
        <div class="detail-row">
            <span class="detail-label">Tags</span>
            <span class="detail-value">
                @foreach($endpointModel->tags as $tag)
                    <span class="badge badge-inbound">{{ $tag->tag }}</span>
                @endforeach
            </span>
        </div>
        @endif
        @if($endpointModel->isDisabled() && $endpointModel->disabled_reason)
        <div class="detail-row">
            <span class="detail-label">Disabled Reason</span>
            <span class="detail-value">{{ $endpointModel->disabled_reason }}</span>
        </div>
        @endif
        @if($endpointModel->isInbound())
        <div class="detail-row">
            <span class="detail-label">Inbound URL</span>
            <span class="detail-value" style="word-break: break-all;"><code>{{ url('/api/webhooks/inbound/' . $endpointModel->route_token) }}</code></span>
        </div>
        @endif
        <div class="detail-row">
            <span class="detail-label">Created</span>
            <span class="detail-value">{{ $endpointModel->created_at->toDateTimeString() }}</span>
        </div>
    </div>

    <div class="card">
        <h3 style="margin-bottom: 15px;">Health & Status</h3>
        <div class="detail-row">
            <span class="detail-label">Health Status</span>
            <span class="detail-value"><span class="badge badge-{{ $health->status }}">{{ $health->status }}</span></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Success Rate</span>
            <span class="detail-value">{{ round($health->successRate, 1) }}%</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Avg Response</span>
            <span class="detail-value">{{ round($health->averageResponseTimeMs) }}ms</span>
        </div>
        @if($health->lastSuccessAt)
        <div class="detail-row">
            <span class="detail-label">Last Success</span>
            <span class="detail-value">{{ $health->lastSuccessAt->diffForHumans() }}</span>
        </div>
        @endif
        @if($health->lastFailureAt)
        <div class="detail-row">
            <span class="detail-label">Last Failure</span>
            <span class="detail-value">{{ $health->lastFailureAt->diffForHumans() }}</span>
        </div>
        @endif
        <div class="detail-row">
            <span class="detail-label">Circuit Breaker</span>
            <span class="detail-value">
                @php $stateName = $circuitState->value; @endphp
                <span class="badge badge-{{ $stateName === 'closed' ? 'delivered' : ($stateName === 'open' ? 'failed' : 'pending') }}">
                    {{ strtoupper($stateName) }}
                </span>
            </span>
        </div>

        @if($endpointModel->secret_rotated_at)
        @php
            $graceHours = (int) config('webhooks.secret_rotation.grace_period_hours', 24);
            $graceEnd = $endpointModel->secret_rotated_at->copy()->addHours($graceHours);
            $inGrace = $graceEnd->isFuture();
        @endphp
        <div class="detail-row">
            <span class="detail-label">Secret Rotation</span>
            <span class="detail-value">
                @if($inGrace)
                    <span class="badge badge-pending">Grace period until {{ $graceEnd->toDateTimeString() }}</span>
                @else
                    Rotated {{ $endpointModel->secret_rotated_at->diffForHumans() }}
                @endif
            </span>
        </div>
        @endif

        <h4 style="margin-top: 20px; margin-bottom: 10px; font-size: 13px; color: var(--color-text-secondary);">SUCCESS RATE (LAST 7 DAYS)</h4>
        <div class="sparkline">
            @foreach($sparkline as $day)
            <div style="display: flex; flex-direction: column; align-items: center; gap: 2px;">
                <div class="sparkline-bar" style="height: {{ max($day['rate'] * 0.3, 2) }}px; background: {{ $day['rate'] >= 95 ? 'var(--color-success)' : ($day['rate'] >= 70 ? 'var(--color-warning)' : 'var(--color-danger)') }};" title="{{ $day['date'] }}: {{ $day['rate'] }}% ({{ $day['success'] }}/{{ $day['total'] }})"></div>
                <span style="font-size: 9px; color: var(--color-text-muted);">{{ substr($day['date'], 4) }}</span>
            </div>
            @endforeach
        </div>
    </div>
</div>

<div class="card">
    <h3 style="margin-bottom: 15px;">Recent Events</h3>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Event</th>
                <th>Status</th>
                <th>Attempts</th>
                <th>Created</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($recentEvents as $event)
                <tr>
                    <td>{{ $event->id }}</td>
                    <td>{{ $event->event_name }}</td>
                    <td><span class="badge badge-{{ $event->status }}">{{ $event->status }}</span></td>
                    <td>{{ $event->attempts_count }}</td>
                    <td>{{ $event->created_at->diffForHumans() }}</td>
                    <td><a href="{{ route('webhooker.events.show', $event->id) }}" class="btn btn-sm btn-primary">View</a></td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-muted" style="text-align: center;">No events for this endpoint.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if($recentEvents->hasPages())
        <div class="pagination">
            {{ $recentEvents->links('webhooker::pagination') }}
        </div>
    @endif
</div>

<a href="{{ route('webhooker.endpoints.index') }}" class="btn btn-primary">&larr; Back to Endpoints</a>
@endsection
