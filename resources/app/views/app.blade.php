<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simples PDV</title>
    <link rel="icon" type="image/png" href="/logo.png">
    <link rel="shortcut icon" type="image/png" href="/logo.png">
    <link rel="apple-touch-icon" href="/logo.png">
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
