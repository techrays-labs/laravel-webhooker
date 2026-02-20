@extends('webhooker::layout')

@section('content')
<h2 style="margin-bottom: 20px;">Webhook Events</h2>

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
        <button type="submit">Filter</button>
    </form>
</div>

<div class="card">
    <table>
        <thead>
            <tr>
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
                    <td colspan="7" class="text-muted" style="text-align: center;">No events found.</td>
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
@endsection
