<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Webhooker Dashboard</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; color: #333; line-height: 1.6; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        nav { background: #1a1a2e; color: #fff; padding: 15px 0; margin-bottom: 30px; }
        nav .container { display: flex; align-items: center; gap: 30px; }
        nav a { color: #ccc; text-decoration: none; font-size: 14px; }
        nav a:hover, nav a.active { color: #fff; }
        nav .brand { font-weight: 700; font-size: 18px; color: #fff; }
        .card { background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 20px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #eee; font-size: 14px; }
        th { font-weight: 600; color: #666; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-processing { background: #cce5ff; color: #004085; }
        .badge-delivered { background: #d4edda; color: #155724; }
        .badge-failed { background: #f8d7da; color: #721c24; }
        .badge-inbound { background: #d1ecf1; color: #0c5460; }
        .badge-outbound { background: #e2e3e5; color: #383d41; }
        .pagination { display: flex; gap: 5px; justify-content: center; margin-top: 20px; }
        .pagination a, .pagination span { padding: 6px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; text-decoration: none; color: #333; }
        .pagination span.current { background: #1a1a2e; color: #fff; border-color: #1a1a2e; }
        .filter-form { display: flex; gap: 10px; margin-bottom: 20px; align-items: flex-end; flex-wrap: wrap; }
        .filter-form label { font-size: 12px; color: #666; display: block; margin-bottom: 4px; }
        .filter-form select, .filter-form input { padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
        .filter-form button { padding: 6px 16px; background: #1a1a2e; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
        .btn { display: inline-block; padding: 6px 16px; border-radius: 4px; font-size: 13px; text-decoration: none; cursor: pointer; border: none; }
        .btn-sm { padding: 4px 10px; font-size: 12px; }
        .btn-primary { background: #1a1a2e; color: #fff; }
        .detail-row { display: flex; gap: 10px; margin-bottom: 8px; }
        .detail-label { font-weight: 600; min-width: 140px; color: #666; font-size: 14px; }
        .detail-value { font-size: 14px; }
        pre { background: #f8f9fa; padding: 12px; border-radius: 4px; overflow-x: auto; font-size: 13px; }
        .text-muted { color: #999; }
    </style>
</head>
<body>
    <nav>
        <div class="container">
            <span class="brand">Webhooker</span>
            <a href="{{ route('webhooker.events.index') }}" class="{{ request()->routeIs('webhooker.events.*') ? 'active' : '' }}">Events</a>
            <a href="{{ route('webhooker.endpoints.index') }}" class="{{ request()->routeIs('webhooker.endpoints.*') ? 'active' : '' }}">Endpoints</a>
        </div>
    </nav>
    <div class="container">
        @yield('content')
    </div>
</body>
</html>
