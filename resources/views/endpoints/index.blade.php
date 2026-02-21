@extends('webhooker::layout')

@section('content')
<h2 style="margin-bottom: 20px;">Webhook Endpoints</h2>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>Token</th>
                <th>Name</th>
                <th>URL</th>
                <th>Direction</th>
                <th>Status</th>
                <th>Tags</th>
                <th>Timeout</th>
                <th>Created</th>
            </tr>
        </thead>
        <tbody>
            @forelse($endpoints as $endpoint)
                <tr>
                    <td><code>{{ $endpoint->route_token }}</code></td>
                    <td>{{ $endpoint->name }}</td>
                    <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $endpoint->url }}</td>
                    <td><span class="badge badge-{{ $endpoint->direction }}">{{ $endpoint->direction }}</span></td>
                    <td>
                        @if($endpoint->isDisabled())
                            <span class="badge" style="background: #dc3545; color: #fff;">Disabled</span>
                            @if($endpoint->disabled_reason)
                                <br><small style="color: #999;">{{ $endpoint->disabled_reason }}</small>
                            @endif
                        @elseif($endpoint->is_active)
                            <span class="badge" style="background: #28a745; color: #fff;">Active</span>
                        @else
                            <span class="badge" style="background: #6c757d; color: #fff;">Inactive</span>
                        @endif
                    </td>
                    <td>
                        @foreach($endpoint->tags as $tag)
                            <span class="badge" style="background: #e9ecef; color: #333; margin: 1px;">{{ $tag->tag }}</span>
                        @endforeach
                    </td>
                    <td>{{ $endpoint->timeout_seconds }}s</td>
                    <td>{{ $endpoint->created_at->diffForHumans() }}</td>
                </tr>
                @if($endpoint->isInbound())
                <tr>
                    <td colspan="8" style="padding: 4px 12px; background: #f8f9fa; font-size: 0.85em;">
                        Inbound URL: <code>/api/webhooks/inbound/{{ $endpoint->route_token }}</code>
                    </td>
                </tr>
                @endif
            @empty
                <tr>
                    <td colspan="8" class="text-muted" style="text-align: center;">No endpoints registered.</td>
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
