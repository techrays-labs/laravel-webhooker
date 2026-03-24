@extends('webhooker::layout')

@section('content')
<h2 style="margin-bottom: 20px;">Analytics Dashboard</h2>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Total Events</div>
        <div class="stat-value">{{ number_format($stats['total_events']) }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Delivered</div>
        <div class="stat-value" style="color: var(--color-success);">{{ number_format($stats['delivered']) }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Failed</div>
        <div class="stat-value" style="color: var(--color-error);">{{ number_format($stats['failed']) }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Success Rate</div>
        <div class="stat-value">{{ $stats['success_rate'] }}%</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Active Endpoints</div>
        <div class="stat-value">{{ $stats['active_endpoints'] }} / {{ $stats['total_endpoints'] }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Avg Response Time</div>
        <div class="stat-value">{{ $stats['average_response_time'] }}ms</div>
    </div>
</div>

<h3 style="margin-top: 30px; margin-bottom: 15px;">Events Over Time</h3>
<div class="card">
    <div class="chart-container">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Delivered</th>
                    <th>Failed</th>
                    <th>Pending</th>
                </tr>
            </thead>
            <tbody>
                @forelse($eventsOverTime as $date => $data)
                    <tr>
                        <td>{{ $date }}</td>
                        <td style="color: var(--color-success);">{{ $data['delivered'] ?? 0 }}</td>
                        <td style="color: var(--color-error);">{{ $data['failed'] ?? 0 }}</td>
                        <td>{{ $data['pending'] ?? 0 }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-muted" style="text-align: center;">No data available</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<h3 style="margin-top: 30px; margin-bottom: 15px;">Top Endpoints</h3>
<div class="card">
    <table>
        <thead>
            <tr>
                <th>Endpoint</th>
                <th>Total Events</th>
                <th>Delivered</th>
                <th>Success Rate</th>
            </tr>
        </thead>
        <tbody>
            @forelse($topEndpoints as $endpoint)
                <tr>
                    <td>{{ $endpoint['name'] }}</td>
                    <td>{{ number_format($endpoint['total']) }}</td>
                    <td>{{ number_format($endpoint['delivered']) }}</td>
                    <td>{{ $endpoint['total'] > 0 ? round(($endpoint['delivered'] / $endpoint['total']) * 100, 1) : 0 }}%</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="text-muted" style="text-align: center;">No endpoints yet</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if(config('webhooks.websocket.enabled'))
<h3 style="margin-top: 30px; margin-bottom: 15px;">Real-time Delivery</h3>
<div class="card">
    <p>Real-time delivery monitoring is <span style="color: var(--color-success);">enabled</span>.</p>
    <p>Listen to channel: <code>webhooks.delivery</code></p>
</div>
@endif
@endsection
