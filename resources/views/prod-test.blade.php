<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Validación PROD - Google Pay</title>
    <style>
        :root { color-scheme: light; }
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background: #f8fafc;
            color: #0f172a;
        }
        .container {
            max-width: 980px;
            margin: 32px auto 64px;
            padding: 0 20px;
        }
        h1 { margin: 0 0 8px; font-size: 24px; }
        h2 { margin: 0 0 12px; font-size: 18px; }
        .nav { margin-bottom: 16px; }
        .nav a {
            color: #1d4ed8;
            text-decoration: none;
            font-size: 14px;
        }
        .nav a:hover { text-decoration: underline; }
        .card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
            margin-bottom: 16px;
        }
        textarea, input, select {
            width: 100%;
            border: 1px solid #cbd5f5;
            border-radius: 10px;
            padding: 12px;
            font-size: 14px;
            background: #ffffff;
            box-sizing: border-box;
        }
        textarea { min-height: 200px; font-family: monospace; font-size: 12px; }
        .row { margin-bottom: 16px; }
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .error { color: #b91c1c; }
        .success { color: #166534; }
        .muted { color: #64748b; font-size: 14px; }
        pre {
            background: #f1f5f9;
            padding: 12px;
            overflow: auto;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            white-space: pre-wrap;
            word-break: break-all;
        }
        .actions { display: flex; gap: 12px; flex-wrap: wrap; }
        .btn {
            border: 1px solid transparent;
            border-radius: 10px;
            padding: 10px 16px;
            font-size: 14px;
            cursor: pointer;
        }
        .btn-primary { background: #1d4ed8; color: #fff; }
        .btn-primary:hover { background: #1e40af; }
        .btn-secondary { background: #fff; border-color: #cbd5f5; color: #1f2937; }
        .btn-secondary:hover { background: #f1f5f9; }
        .pill {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            background: #fef3c7;
            color: #92400e;
            margin-bottom: 10px;
        }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 12px;
            margin-left: 6px;
        }
        .badge-pass { background: #dcfce7; color: #166534; }
        .badge-fail { background: #fee2e2; color: #991b1b; }
        label { display: block; font-weight: 600; margin-bottom: 6px; font-size: 14px; }
    </style>
</head>
<body>
<div class="container">
    <div class="nav">
        <a href="/">&larr; Volver al test con Google Pay button</a>
    </div>
    <h1>Validación de Decryption — Rotación de keys</h1>
    <p class="muted">Probar payloads PROD enviados por Google con key actual y nueva.</p>

    @if ($errors->any())
        <div id="error-block" class="card error">
            <strong>Errores:</strong>
            <ul>
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (isset($success) && $success === true)
        <div id="success-block" class="card success">
            <strong>PASS</strong>
            <span class="badge badge-pass">{{ $environment ?? '' }}</span>
            — Token desencriptado correctamente.
        </div>
    @endif

    @if (isset($success) && $success === false)
        <div id="error-message" class="card error">
            <strong>FAIL</strong>
            <span class="badge badge-fail">{{ $environment ?? '' }}</span>
            — {{ $error ?? 'No se pudo desencriptar el token.' }}
        </div>
    @endif

    <div class="card">
        <span class="pill">Decryption test</span>

        <form method="POST" action="{{ route('prod-test.decrypt') }}">
            @csrf

            <div class="row">
                <label for="environment">Environment</label>
                <select name="environment" id="environment" required>
                    <option value="PRODUCTION" {{ old('environment', 'PRODUCTION') === 'PRODUCTION' ? 'selected' : '' }}>PRODUCTION (usa GOOGLEPAY_PROD_PRIVATE_KEY)</option>
                    <option value="TEST" {{ old('environment') === 'TEST' ? 'selected' : '' }}>TEST (usa GOOGLEPAY_PRIVATE_KEY)</option>
                </select>
            </div>

            <div class="row">
                <label for="token">Payload (JSON con signedMessage)</label>
                <textarea id="token" name="token" required placeholder='{"signature":"...","intermediateSigningKey":{...},"protocolVersion":"ECv2","signedMessage":"..."}'>{{ old('token') }}</textarea>
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary">Desencriptar</button>
                <button type="button" id="clear-form" class="btn btn-secondary">Limpiar</button>
            </div>
        </form>
    </div>

    @if (! empty($decrypted) || ! empty($token))
        <div id="result-block" class="card">
            <h2>Resultado</h2>
            <p class="muted">Environment: <strong>{{ $environment ?? '' }}</strong></p>

            @if (! empty($token))
                <p class="muted">Payload original</p>
                <pre>{{ $token }}</pre>
            @endif

            @if (! empty($decrypted))
                <p class="muted">Payload desencriptado</p>
                <pre>{{ json_encode($decrypted, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            @endif
        </div>
    @endif

    <script>
        const clearButton = document.getElementById('clear-form');
        if (clearButton) {
            clearButton.addEventListener('click', function () {
                document.getElementById('token').value = '';
                ['error-block', 'success-block', 'error-message', 'result-block'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.style.display = 'none';
                });
            });
        }
    </script>
</div>
</body>
</html>

