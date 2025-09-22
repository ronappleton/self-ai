<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'SELF') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="stylesheet" href="https://fonts.bunny.net/css?family=inter:400,500,600&display=swap">
    <style>
        :root {
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            color: #1f2937;
        }

        body {
            margin: 0;
            background-color: #f1f5f9;
        }

        a {
            color: #2563eb;
            text-decoration: none;
        }

        textarea,
        input,
        button {
            font-family: inherit;
        }
    </style>
</head>
<body>
    <nav style="background:#fff;box-shadow:0 1px 3px rgba(15,23,42,0.1);">
        <div style="max-width:960px;margin:0 auto;padding:16px 24px;display:flex;justify-content:space-between;align-items:center;">
            <div style="font-size:20px;font-weight:600;">SELF Review</div>
            <div style="font-size:14px;color:#64748b;">{{ now()->toDayDateTimeString() }}</div>
        </div>
    </nav>
    <main>
        @yield('content')
    </main>
</body>
</html>
