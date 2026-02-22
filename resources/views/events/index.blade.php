@extends('webhooker::layout')

@section('content')
<h2 style="margin-bottom: 20px;">Webhook Events</h2>

@if(isset($stats))
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value">{{ $stats->totalEvents }}</div>
        <div class="stat-label">Events (24h)</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color: var(--color-success);">{{ $stats->successfulCount }}</div>
        <div class="stat-label">Delivered</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color: var(--color-danger);">{{ $stats->failedCount }}</div>
        <div class="stat-label">Failed</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color: var(--color-warning);">{{ $stats->pendingCount }}</div>
        <div class="stat-label">Pending</div>
    </div>
    <div class="stat-card">
        <div class="stat-value">{{ $successRate ?? 100 }}%</div>
        <div class="stat-label">Success Rate</div>
    </div>
    <div class="stat-card">
        <div class="stat-value">{{ round($stats->averageResponseTimeMs) }}ms</div>
        <div class="stat-label">Avg Response</div>
    </div>
    @if(isset($healthCounts))
    <div class="stat-card">
        <div style="font-size: 14px; margin-bottom: 4px;">
            <span class="badge badge-healthy">{{ $healthCounts['healthy'] }}</span>
            <span class="badge badge-degraded">{{ $healthCounts['degraded'] }}</span>
            <span class="badge badge-failing">{{ $healthCounts['failing'] }}</span>
        </div>
        <div class="stat-label">Endpoint Health</div>
    </div>
    @endif
    @if(isset($openCircuits) && $openCircuits > 0)
    <div class="stat-card">
        <div class="stat-value" style="color: var(--color-danger);">{{ $openCircuits }}</div>
        <div class="stat-label">Open Circuits</div>
    </div>
    @endif
</div>
@endif

<div class="card">
    <form method="GET" action="{{ route('webhooker.events.index') }}" class="filter-form">
        <div>
            <label for="status">Status</label>
            <select name="status" id="status">
                <option value="">All</option>
                <option value="pending" {{ ($filters['status'] ?? '') === 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="processing" {{ ($filters['status'] ?? '') === 'processing' ? 'selected' : '' }}>Processing</option>
                <option value="delivered" {{ ($filters['status'] ?? '') === 'delivered' ? 'selected' : '' }}>Delivered</option>
                <option value="failed" {{ ($filters['status'] ?? '') === 'failed' ? 'selected' : '' }}>Failed</option>
            </select>
        </div>
        <div>
            <label for="endpoint_id">Endpoint</label>
            <select name="endpoint_id" id="endpoint_id">
                <option value="">All</option>
                @foreach($endpoints as $endpoint)
                    <option value="{{ $endpoint->id }}" {{ ($filters['endpoint_id'] ?? '') == $endpoint->id ? 'selected' : '' }}>
                        {{ $endpoint->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="event_name">Event Name</label>
            <input type="text" name="event_name" id="event_name" value="{{ $filters['event_name'] ?? '' }}" placeholder="Search...">
        </div>
        @if(isset($tags) && $tags->isNotEmpty())
        <div>
            <label for="tag">Tag</label>
            <select name="tag" id="tag">
                <option value="">All</option>
                @foreach($tags as $tag)
                    <option value="{{ $tag }}" {{ ($filters['tag'] ?? '') === $tag ? 'selected' : '' }}>
                        {{ $tag }}
                    </option>
                @endforeach
            </select>
        </div>
        @endif
        <button type="submit">Filter</button>
    </form>
</div>

<form method="POST" action="{{ route('webhooker.events.bulk') }}" id="bulkForm">
    @csrf
    <div class="bulk-bar" id="bulkBar">
        <span id="selectedCount">0</span> selected
        <button type="submit" name="action" value="replay" class="btn btn-sm btn-primary" onclick="return confirm('Replay selected events?')">Replay Selected</button>
        <button type="submit" name="action" value="delete" class="btn btn-sm btn-danger" onclick="return confirm('Delete selected events? This cannot be undone.')">Delete Selected</button>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th style="width: 30px;"><input type="checkbox" id="selectAll" onchange="toggleAll(this)"></th>
                    <th>ID</th>
                    <th>Endpoint</th>
                    <th>Event</th>
                    <th>Status</th>
                    <th>Attempts</th>
                    <th>Created</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($events as $event)
                    <tr>
                        <td><input type="checkbox" name="event_ids[]" value="{{ $event->id }}" class="event-checkbox" onchange="updateBulkBar()"></td>
                        <td>{{ $event->id }}</td>
                        <td>{{ $event->endpoint->name ?? 'N/A' }}</td>
                        <td>{{ $event->event_name }}</td>
                        <td><span class="badge badge-{{ $event->status }}">{{ $event->status }}</span></td>
                        <td>{{ $event->attempts_count }}</td>
                        <td>{{ $event->created_at->diffForHumans() }}</td>
                        <td><a href="{{ route('webhooker.events.show', $event->id) }}" class="btn btn-sm btn-primary">View</a></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-muted" style="text-align: center;">No events found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if($events->hasPages())
            <div class="pagination">
                {{ $events->withQueryString()->links('webhooker::pagination') }}
            </div>
        @endif
    </div>
</form>

<script>
function toggleAll(source) {
    var checkboxes = document.querySelectorAll('.event-checkbox');
    for (var i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = source.checked;
    }
    updateBulkBar();
}
function updateBulkBar() {
    var checked = document.querySelectorAll('.event-checkbox:checked');
    var bar = document.getElementById('bulkBar');
    var count = document.getElementById('selectedCount');
    count.textContent = checked.length;
    if (checked.length > 0) {
        bar.classList.add('visible');
    } else {
        bar.classList.remove('visible');
    }
}
</script>
@endsection
