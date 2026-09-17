@props(['title' => 'Sign in'])
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $title }} · PEGWISE</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        :root{--ink:#171717;--muted:#6b7280;--line:#e5e7eb;--orange:#ea580c}*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#f8fafc;color:var(--ink);font:14px/1.5 Inter,system-ui,sans-serif}.auth-shell{width:min(440px,100%);background:#fff;border:1px solid var(--line)}.auth-brand{padding:24px 26px;background:#171717;color:#fff;font-size:22px;font-weight:700;letter-spacing:-.055em}.auth-brand span{color:#fb923c}.auth-body{padding:28px 26px}.auth-body h1{margin:0;font-size:26px;letter-spacing:-.04em}.auth-body p{color:var(--muted);margin:8px 0 22px}.auth-form{display:grid;gap:16px}.auth-form label{display:grid;gap:6px;color:#52525b;font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase}.auth-form input{width:100%;min-height:46px;border:1px solid #d4d4d8;border-radius:0;padding:10px 12px;font:15px Inter,sans-serif}.auth-form input:focus{outline:2px solid #fed7aa;border-color:#f97316}.auth-check{display:flex!important;grid-template-columns:none!important;align-items:center;grid-auto-flow:column;justify-content:start;text-transform:none!important;font-size:13px!important;font-weight:500!important;letter-spacing:0!important}.auth-check input{width:16px;min-height:16px}.auth-button{min-height:46px;border:1px solid var(--orange);background:var(--orange);color:#fff;font:700 12px Inter,sans-serif;text-transform:uppercase;cursor:pointer}.auth-button:hover{background:#c2410c}.auth-link{color:#c2410c;text-decoration:none;font-weight:600}.auth-errors{margin:0 0 18px;padding:12px 14px;border-left:4px solid #dc2626;background:#fff7f5;color:#991b1b}.auth-status{margin:0 0 18px;padding:12px 14px;border-left:4px solid #16a34a;background:#f0fdf4;color:#166534}@media(max-width:480px){body{padding:0;background:#fff;place-items:stretch}.auth-shell{border:0;min-height:100vh}.auth-brand{padding:max(20px,env(safe-area-inset-top)) 20px 20px}.auth-body{padding:26px 20px}}
    </style>
</head>
<body><main class="auth-shell"><div class="auth-brand"><span>PEG</span>WISE</div><div class="auth-body">{{ $slot }}</div></main></body>
</html>
