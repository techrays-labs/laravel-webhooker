@extends('webhooker::layout')

@section('content')
<h2 style="margin-bottom: 20px;">Webhook Endpoints</h2>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>URL</th>
                <th>Direction</th>
                <th>Active</th>
                <th>Timeout</th>
                <th>Created</th>
            </tr>
        </thead>
        <tbody>
            @forelse($endpoints as $endpoint)
                <tr>
                    <td>{{ $endpoint->id }}</td>
                    <td>{{ $endpoint->name }}</td>
                    <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $endpoint->url }}</td>
                    <td><span class="badge badge-{{ $endpoint->direction }}">{{ $endpoint->direction }}</span></td>
                    <td>{{ $endpoint->is_active ? 'Yes' : 'No' }}</td>
                    <td>{{ $endpoint->timeout_seconds }}s</td>
                    <td>{{ $endpoint->created_at->diffForHumans() }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-muted" style="text-align: center;">No endpoints registered.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if($endpoints->hasPages())
        <div class="pagination">
            {{ $endpoints->withQueryString()->links('webhooker::pagination') }}
        </div>
    @endif
</div>
@endsection
