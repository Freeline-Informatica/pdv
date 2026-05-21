<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simples PDV</title>
    <link rel="icon" type="image/png" href="/logo.png">
    <link rel="shortcut icon" type="image/png" href="/logo.png">
    <link rel="apple-touch-icon" href="/logo.png">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $pdvRuntime = [
            'mode' => config('pdv.mode', 'standalone'),
            'integrated' => config('pdv.mode', 'standalone') === 'erp' || (bool) config('pdv.web_session_auth', false),
            'erp_home_url' => url('/dashboard'),
            'erp_login_url' => url('/login'),
            'erp_logout_url' => url('/logout'),
            'csrf_token' => csrf_token(),
        ];
    @endphp
    <script>
        window.__SIMPLS_PDV_RUNTIME__ = @json($pdvRuntime);
    </script>
    @if(! empty($bootstrap))
        <script>
            window.__SIMPLS_PDV_BOOTSTRAP__ = @json($bootstrap);
        </script>
    @endif
    @vite(config('pdv.vite_inputs', ['resources/css/app.css', 'resources/js/app.js']))
</head>
<body>
<div id="app"></div>
</body>
</html>
