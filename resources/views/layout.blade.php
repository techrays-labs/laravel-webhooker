<!DOCTYPE html>
<html lang="en" data-theme="{{ request()->cookie('webhooker_theme', 'light') }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Webhooker Dashboard</title>
    <style>
        :root {
            --color-bg: #f5f5f5;
            --color-bg-card: #fff;
            --color-bg-code: #f8f9fa;
            --color-text: #333;
            --color-text-muted: #999;
            --color-text-secondary: #666;
            --color-border: #eee;
            --color-border-input: #ddd;
            --color-primary: #1a1a2e;
            --color-primary-text: #fff;
            --color-nav: #1a1a2e;
            --color-nav-link: #ccc;
            --color-shadow: rgba(0,0,0,0.1);
            --color-badge-pending-bg: #fff3cd;
            --color-badge-pending-text: #856404;
            --color-badge-processing-bg: #cce5ff;
            --color-badge-processing-text: #004085;
            --color-badge-delivered-bg: #d4edda;
            --color-badge-delivered-text: #155724;
            --color-badge-failed-bg: #f8d7da;
            --color-badge-failed-text: #721c24;
            --color-badge-inbound-bg: #d1ecf1;
            --color-badge-inbound-text: #0c5460;
            --color-badge-outbound-bg: #e2e3e5;
            --color-badge-outbound-text: #383d41;
            --color-badge-healthy-bg: #d4edda;
            --color-badge-healthy-text: #155724;
            --color-badge-degraded-bg: #fff3cd;
            --color-badge-degraded-text: #856404;
            --color-badge-failing-bg: #f8d7da;
            --color-badge-failing-text: #721c24;
            --color-badge-unknown-bg: #e2e3e5;
            --color-badge-unknown-text: #383d41;
            --color-stat-card-border: #e0e0e0;
            --color-success: #28a745;
            --color-warning: #ffc107;
            --color-danger: #dc3545;
            --color-info: #17a2b8;
        }

        [data-theme="dark"] {
            --color-bg: #0f0f1a;
            --color-bg-card: #1e1e30;
            --color-bg-code: #2a2a3d;
            --color-text: #e0e0e0;
            --color-text-muted: #888;
            --color-text-secondary: #aaa;
            --color-border: #333;
            --color-border-input: #444;
            --color-primary: #2a2a4a;
            --color-primary-text: #fff;
            --color-nav: #0a0a18;
            --color-nav-link: #aaa;
            --color-shadow: rgba(0,0,0,0.3);
            --color-badge-pending-bg: #4a3f1a;
            --color-badge-pending-text: #ffd966;
            --color-badge-processing-bg: #1a2e4a;
            --color-badge-processing-text: #7db8f0;
            --color-badge-delivered-bg: #1a3a2a;
            --color-badge-delivered-text: #6fd08c;
            --color-badge-failed-bg: #3a1a1a;
            --color-badge-failed-text: #f08080;
            --color-badge-inbound-bg: #1a3040;
            --color-badge-inbound-text: #6dc8d8;
            --color-badge-outbound-bg: #2a2a30;
            --color-badge-outbound-text: #b0b0b8;
            --color-badge-healthy-bg: #1a3a2a;
            --color-badge-healthy-text: #6fd08c;
            --color-badge-degraded-bg: #4a3f1a;
            --color-badge-degraded-text: #ffd966;
            --color-badge-failing-bg: #3a1a1a;
            --color-badge-failing-text: #f08080;
            --color-badge-unknown-bg: #2a2a30;
            --color-badge-unknown-text: #b0b0b8;
            --color-stat-card-border: #333;
            --color-success: #4caf50;
            --color-warning: #ff9800;
            --color-danger: #f44336;
            --color-info: #29b6f6;
        }

        @media (prefers-color-scheme: dark) {
            html:not([data-theme="light"]) {
                --color-bg: #0f0f1a;
                --color-bg-card: #1e1e30;
                --color-bg-code: #2a2a3d;
                --color-text: #e0e0e0;
                --color-text-muted: #888;
                --color-text-secondary: #aaa;
                --color-border: #333;
                --color-border-input: #444;
                --color-primary: #2a2a4a;
                --color-primary-text: #fff;
                --color-nav: #0a0a18;
                --color-nav-link: #aaa;
                --color-shadow: rgba(0,0,0,0.3);
                --color-badge-pending-bg: #4a3f1a;
                --color-badge-pending-text: #ffd966;
                --color-badge-processing-bg: #1a2e4a;
                --color-badge-processing-text: #7db8f0;
                --color-badge-delivered-bg: #1a3a2a;
                --color-badge-delivered-text: #6fd08c;
                --color-badge-failed-bg: #3a1a1a;
                --color-badge-failed-text: #f08080;
                --color-badge-inbound-bg: #1a3040;
                --color-badge-inbound-text: #6dc8d8;
                --color-badge-outbound-bg: #2a2a30;
                --color-badge-outbound-text: #b0b0b8;
                --color-badge-healthy-bg: #1a3a2a;
                --color-badge-healthy-text: #6fd08c;
                --color-badge-degraded-bg: #4a3f1a;
                --color-badge-degraded-text: #ffd966;
                --color-badge-failing-bg: #3a1a1a;
                --color-badge-failing-text: #f08080;
                --color-badge-unknown-bg: #2a2a30;
                --color-badge-unknown-text: #b0b0b8;
                --color-stat-card-border: #333;
                --color-success: #4caf50;
                --color-warning: #ff9800;
                --color-danger: #f44336;
                --color-info: #29b6f6;
            }
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: var(--color-bg); color: var(--color-text); line-height: 1.6; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        nav { background: var(--color-nav); color: var(--color-primary-text); padding: 15px 0; margin-bottom: 30px; }
        nav .container { display: flex; align-items: center; gap: 30px; }
        nav a { color: var(--color-nav-link); text-decoration: none; font-size: 14px; }
        nav a:hover, nav a.active { color: #fff; }
        nav .brand { font-weight: 700; font-size: 18px; color: #fff; }
        .theme-toggle { background: none; border: 1px solid var(--color-nav-link); color: var(--color-nav-link); padding: 4px 10px; border-radius: 4px; cursor: pointer; font-size: 14px; margin-left: auto; }
        .theme-toggle:hover { color: #fff; border-color: #fff; }
        .card { background: var(--color-bg-card); border-radius: 8px; box-shadow: 0 1px 3px var(--color-shadow); padding: 20px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid var(--color-border); font-size: 14px; }
        th { font-weight: 600; color: var(--color-text-secondary); font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .badge-pending { background: var(--color-badge-pending-bg); color: var(--color-badge-pending-text); }
        .badge-processing { background: var(--color-badge-processing-bg); color: var(--color-badge-processing-text); }
        .badge-delivered { background: var(--color-badge-delivered-bg); color: var(--color-badge-delivered-text); }
        .badge-failed { background: var(--color-badge-failed-bg); color: var(--color-badge-failed-text); }
        .badge-inbound { background: var(--color-badge-inbound-bg); color: var(--color-badge-inbound-text); }
        .badge-outbound { background: var(--color-badge-outbound-bg); color: var(--color-badge-outbound-text); }
        .badge-healthy { background: var(--color-badge-healthy-bg); color: var(--color-badge-healthy-text); }
        .badge-degraded { background: var(--color-badge-degraded-bg); color: var(--color-badge-degraded-text); }
        .badge-failing { background: var(--color-badge-failing-bg); color: var(--color-badge-failing-text); }
        .badge-unknown { background: var(--color-badge-unknown-bg); color: var(--color-badge-unknown-text); }
        .badge-active { background: var(--color-badge-delivered-bg); color: var(--color-badge-delivered-text); }
        .badge-inactive { background: var(--color-badge-outbound-bg); color: var(--color-badge-outbound-text); }
        .badge-disabled { background: var(--color-badge-failed-bg); color: var(--color-badge-failed-text); }
        .pagination { display: flex; gap: 5px; justify-content: center; margin-top: 20px; }
        .pagination a, .pagination span { padding: 6px 12px; border: 1px solid var(--color-border-input); border-radius: 4px; font-size: 13px; text-decoration: none; color: var(--color-text); }
        .pagination span.current { background: var(--color-primary); color: var(--color-primary-text); border-color: var(--color-primary); }
        .filter-form { display: flex; gap: 10px; margin-bottom: 20px; align-items: flex-end; flex-wrap: wrap; }
        .filter-form label { font-size: 12px; color: var(--color-text-secondary); display: block; margin-bottom: 4px; }
        .filter-form select, .filter-form input { padding: 6px 10px; border: 1px solid var(--color-border-input); border-radius: 4px; font-size: 14px; background: var(--color-bg-card); color: var(--color-text); }
        .filter-form button { padding: 6px 16px; background: var(--color-primary); color: var(--color-primary-text); border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
        .btn { display: inline-block; padding: 6px 16px; border-radius: 4px; font-size: 13px; text-decoration: none; cursor: pointer; border: none; }
        .btn-sm { padding: 4px 10px; font-size: 12px; }
        .btn-primary { background: var(--color-primary); color: var(--color-primary-text); }
        .btn-danger { background: var(--color-danger); color: #fff; }
        .btn-success { background: var(--color-success); color: #fff; }
        .detail-row { display: flex; gap: 10px; margin-bottom: 8px; }
        .detail-label { font-weight: 600; min-width: 140px; color: var(--color-text-secondary); font-size: 14px; }
        .detail-value { font-size: 14px; }
        pre { background: var(--color-bg-code); padding: 12px; border-radius: 4px; overflow-x: auto; font-size: 13px; color: var(--color-text); }
        .text-muted { color: var(--color-text-muted); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .stat-card { background: var(--color-bg-card); border: 1px solid var(--color-stat-card-border); border-radius: 8px; padding: 15px; text-align: center; }
        .stat-card .stat-value { font-size: 28px; font-weight: 700; line-height: 1.2; }
        .stat-card .stat-label { font-size: 12px; color: var(--color-text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-top: 4px; }
        .timeline { position: relative; padding-left: 30px; }
        .timeline::before { content: ''; position: absolute; left: 10px; top: 0; bottom: 0; width: 2px; background: var(--color-border); }
        .timeline-item { position: relative; margin-bottom: 20px; }
        .timeline-item::before { content: ''; position: absolute; left: -24px; top: 6px; width: 12px; height: 12px; border-radius: 50%; border: 2px solid var(--color-border); background: var(--color-bg-card); }
        .timeline-item.success::before { border-color: var(--color-success); background: var(--color-success); }
        .timeline-item.failure::before { border-color: var(--color-danger); background: var(--color-danger); }
        .timeline-item.error::before { border-color: var(--color-warning); background: var(--color-warning); }
        .timeline-meta { font-size: 12px; color: var(--color-text-muted); margin-bottom: 4px; }
        .timeline-content { background: var(--color-bg-code); border-radius: 6px; padding: 12px; }
        details summary { cursor: pointer; font-size: 13px; color: var(--color-text-secondary); padding: 4px 0; }
        details summary:hover { color: var(--color-text); }
        .bulk-bar { background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: 6px; padding: 10px 15px; margin-bottom: 15px; display: none; align-items: center; gap: 10px; }
        .bulk-bar.visible { display: flex; }
        .sparkline { display: flex; align-items: flex-end; gap: 2px; height: 30px; }
        .sparkline-bar { width: 8px; border-radius: 2px 2px 0 0; min-height: 2px; }
        .flash-message { padding: 10px 15px; border-radius: 6px; margin-bottom: 15px; font-size: 14px; }
        .flash-success { background: var(--color-badge-delivered-bg); color: var(--color-badge-delivered-text); }
        .flash-error { background: var(--color-badge-failed-bg); color: var(--color-badge-failed-text); }
    </style>
</head>
<body>
    <nav>
        <div class="container">
            <span class="brand">Webhooker</span>
            <a href="{{ route('webhooker.events.index') }}" class="{{ request()->routeIs('webhooker.events.*') ? 'active' : '' }}">Events</a>
            <a href="{{ route('webhooker.endpoints.index') }}" class="{{ request()->routeIs('webhooker.endpoints.*') ? 'active' : '' }}">Endpoints</a>
            <button class="theme-toggle" onclick="toggleTheme()" title="Toggle dark mode" id="themeToggle">
                <span id="themeIcon">&#9790;</span>
            </button>
        </div>
    </nav>
    <div class="container">
        @if(session('success'))
            <div class="flash-message flash-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="flash-message flash-error">{{ session('error') }}</div>
        @endif
        @yield('content')
    </div>
    <script>
        function getTheme() {
            var c = document.cookie.split(';');
            for (var i = 0; i < c.length; i++) {
                var t = c[i].trim();
                if (t.indexOf('webhooker_theme=') === 0) return t.substring(16);
            }
            return null;
        }
        function toggleTheme() {
            var html = document.documentElement;
            var current = html.getAttribute('data-theme');
            var next = current === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', next);
            document.cookie = 'webhooker_theme=' + next + ';path=/;max-age=31536000;SameSite=Lax';
            updateIcon(next);
        }
        function updateIcon(theme) {
            var icon = document.getElementById('themeIcon');
            if (icon) icon.textContent = theme === 'dark' ? '\u2600' : '\u263E';
        }
        (function() {
            var saved = getTheme();
            if (saved) {
                document.documentElement.setAttribute('data-theme', saved);
                updateIcon(saved);
            } else {
                var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                updateIcon(prefersDark ? 'dark' : 'light');
            }
        })();
    </script>
</body>
</html>
