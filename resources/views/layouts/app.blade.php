<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'ZETA Email-to-Task')</title>
    <style>
        :root {
            --bg: #0f172a;
            --panel: #1e293b;
            --panel-border: #334155;
            --text: #e2e8f0;
            --muted: #94a3b8;
            --accent: #38bdf8;
            --accent-hover: #0ea5e9;
            --success: #4ade80;
            --warning: #fbbf24;
            --danger: #f87171;
            --chip-bg: #334155;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: "Segoe UI", system-ui, sans-serif;
            background: linear-gradient(160deg, #0f172a 0%, #1e293b 100%);
            color: var(--text);
            min-height: 100vh;
        }

        a { color: var(--accent); text-decoration: none; }
        a:hover { color: var(--accent-hover); }

        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 2rem 1.25rem 4rem;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .brand h1 {
            margin: 0;
            font-size: 1.5rem;
            letter-spacing: 0.04em;
        }

        .brand p {
            margin: 0.25rem 0 0;
            color: var(--muted);
            font-size: 0.95rem;
        }

        .badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            background: var(--chip-bg);
        }

        .badge-openai { background: #14532d; color: #bbf7d0; }
        .badge-pending { background: #713f12; color: #fde68a; }
        .badge-approved { background: #14532d; color: #bbf7d0; }
        .badge-rejected { background: #7f1d1d; color: #fecaca; }
        .badge-overridden { background: #1e3a8a; color: #bfdbfe; }

        .alert {
            padding: 0.875rem 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.25rem;
            border: 1px solid transparent;
        }

        .alert-success {
            background: rgba(74, 222, 128, 0.12);
            border-color: rgba(74, 222, 128, 0.35);
            color: #bbf7d0;
        }

        .alert-error {
            background: rgba(248, 113, 113, 0.12);
            border-color: rgba(248, 113, 113, 0.35);
            color: #fecaca;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            gap: 1.5rem;
        }

        @media (max-width: 900px) {
            .grid { grid-template-columns: 1fr; }
        }

        .card {
            background: rgba(30, 41, 59, 0.92);
            border: 1px solid var(--panel-border);
            border-radius: 1rem;
            padding: 1.25rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
        }

        .card h2 {
            margin: 0 0 1rem;
            font-size: 1.1rem;
        }

        label {
            display: block;
            margin-bottom: 0.35rem;
            color: var(--muted);
            font-size: 0.875rem;
        }

        input[type="text"],
        input[type="email"],
        textarea,
        select {
            width: 100%;
            padding: 0.7rem 0.85rem;
            margin-bottom: 1rem;
            border-radius: 0.6rem;
            border: 1px solid var(--panel-border);
            background: #0b1220;
            color: var(--text);
            font: inherit;
        }

        textarea { min-height: 140px; resize: vertical; }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            padding: 0.65rem 1rem;
            border: none;
            border-radius: 0.6rem;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.15s ease, background 0.15s ease;
        }

        .btn:hover { transform: translateY(-1px); }

        .btn-primary { background: var(--accent); color: #082f49; }
        .btn-primary:hover { background: var(--accent-hover); }

        .btn-success { background: #16a34a; color: white; }
        .btn-danger { background: #dc2626; color: white; }
        .btn-secondary { background: #475569; color: white; }

        .btn-block { width: 100%; }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.92rem;
        }

        th, td {
            padding: 0.75rem 0.5rem;
            border-bottom: 1px solid var(--panel-border);
            text-align: left;
            vertical-align: top;
        }

        th { color: var(--muted); font-weight: 600; }

        .field-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
        }

        .field-grid .full { grid-column: 1 / -1; }

        .field-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--muted);
            margin-bottom: 0.25rem;
        }

        .field-value {
            font-size: 0.98rem;
            line-height: 1.5;
        }

        .confidence-bar {
            height: 8px;
            background: #334155;
            border-radius: 999px;
            overflow: hidden;
            margin-top: 0.35rem;
        }

        .confidence-bar span {
            display: block;
            height: 100%;
            background: linear-gradient(90deg, #fbbf24, #4ade80);
        }

        .pill-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            padding: 0;
            margin: 0;
            list-style: none;
        }

        .pill-list li {
            background: #334155;
            padding: 0.35rem 0.65rem;
            border-radius: 999px;
            font-size: 0.85rem;
        }

        .actions {
            display: grid;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .muted { color: var(--muted); }
        .small { font-size: 0.85rem; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="brand">
                <h1>ZETA</h1>
                <p>Email-to-Task Assistant — AI suggests, you decide</p>
            </div>
            <span class="badge badge-openai">ChatGPT · {{ config('ai.openai.model') }}</span>
        </header>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if (session('error'))
            <div class="alert alert-error">{{ session('error') }}</div>
        @endif

        @yield('content')
    </div>
</body>
</html>
