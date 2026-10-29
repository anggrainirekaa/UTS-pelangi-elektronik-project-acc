<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · Pelangi Elektronik</title>
    <style>
        :root{--bg:#F4F7FC;--card:#fff;--text:#172B4D;--muted:#738198;--primary:#1769E0;--border:#E3EAF5}
        @media (prefers-color-scheme:dark){:root{--bg:#0B1626;--card:#11233D;--text:#E6EEF9;--muted:#93A4BD;--border:#1E3556}}
        *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:20px;background:var(--bg);color:var(--text);font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
        .box{max-width:440px;width:100%;text-align:center;background:var(--card);border:1px solid var(--border);border-radius:22px;padding:36px 28px;box-shadow:0 8px 30px rgba(18,49,90,.1)}
        .code{font-size:56px;font-weight:700;letter-spacing:-.03em;color:var(--primary);line-height:1}
        h1{font-size:20px;margin:14px 0 6px}p{color:var(--muted);line-height:1.55;margin:0 0 22px;font-size:14.5px}
        .btn{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:0 20px;border-radius:13px;background:var(--primary);color:#fff;text-decoration:none;font-weight:600;font-size:14px;margin:4px}
        .btn.ghost{background:transparent;color:var(--primary);border:1px solid var(--border)}
    </style>
</head>
<body>
    <main class="box">
        <div class="code">@yield('code')</div>
        <h1>@yield('heading')</h1>
        <p>@yield('message')</p>
        <a class="btn" href="{{ url('/dashboard') }}">Ke Dashboard</a>
        <a class="btn ghost" href="javascript:history.back()">Kembali</a>
    </main>
</body>
</html>
