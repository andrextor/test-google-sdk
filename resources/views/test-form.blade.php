<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Prueba de token Google Pay</title>
    <style>
        :root {
            color-scheme: light;
        }
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
        h1 {
            margin: 0 0 20px;
            font-size: 24px;
        }
        h2 {
            margin: 0 0 12px;
            font-size: 18px;
        }
        .card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
            margin-bottom: 16px;
        }
        textarea, input {
            width: 100%;
            max-width: 100%;
            border: 1px solid #cbd5f5;
            border-radius: 10px;
            padding: 12px;
            font-size: 14px;
            background: #ffffff;
        }
        textarea { min-height: 180px; }
        .row { margin-bottom: 16px; }
        .error { color: #b91c1c; }
        .success { color: #166534; }
        .muted { color: #64748b; font-size: 14px; }
        pre {
            background: #f1f5f9;
            padding: 12px;
            overflow: auto;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
        }
        #gpay-container { margin-bottom: 16px; }
        .actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .btn {
            border: 1px solid transparent;
            border-radius: 10px;
            padding: 10px 16px;
            font-size: 14px;
            cursor: pointer;
        }
        .btn-primary {
            background: #1d4ed8;
            color: #ffffff;
        }
        .btn-primary:hover { background: #1e40af; }
        .btn-secondary {
            background: #ffffff;
            border-color: #cbd5f5;
            color: #1f2937;
        }
        .btn-secondary:hover { background: #f1f5f9; }
        .pill {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            background: #eef2ff;
            color: #3730a3;
            margin-bottom: 10px;
        }
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 16px;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Prueba de token Google Pay</h1>
    <p class="muted">Genera un token con Google Pay y valida su desencriptacion. · <a href="{{ route('prod-test.show') }}">Validación PROD &rarr;</a></p>

    @if ($errors->any())
        <div id="error-block" class="card error">
            <strong>Errores:</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (isset($success) && $success === true)
        <div id="success-block" class="card success">
            Token desencriptado correctamente.
        </div>
    @endif

    @if (isset($success) && $success === false)
        <div id="error-message" class="card error">
            {{ $error ?? 'No se pudo desencriptar el token.' }}
        </div>
    @endif

    <div class="card">
        <span class="pill">Google Pay</span>
        <div id="gpay-container"></div>

        <form method="POST" action="{{ route('test-form.decrypt') }}">
            @csrf
            <div class="row">
                <label for="merchantId"><strong>Merchant ID</strong></label>
                <input id="merchantId" name="merchantId" required value="{{ old('merchantId', $merchantId ?? 'placetopay') }}" placeholder="Merchant ID" style="border: 2px solid #6366f1; background: #f5f3ff; color: #3730a3; font-weight: 600;">
            </div>
            <div class="row">
                <label for="token"><strong>Token (JSON con signedMessage)</strong></label>
                <textarea id="token" name="token" required>{{ old('token') }}</textarea>
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
            @if (! empty($token))
                <p class="muted">Token (JSON con signedMessage)</p>
                <pre>{{ $token }}</pre>
            @endif
            @if (! empty($decrypted))
                <p class="muted">Payload desencriptado</p>
                <pre>{{ json_encode($decrypted, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            @endif
        </div>
    @endif

    <script async src="https://pay.google.com/gp/p/js/pay.js" onload="onGooglePayLoaded()"></script>

    <script>
        const gatewayMerchantId = @json((string) config('googlepay.gateway_merchant_id'));
        // const merchantId = @json((string) config('googlepay.merchant_id'));

        // 1. Configuración Base según tu documentación
        const baseRequest = {
            apiVersion: 2,
            apiVersionMinor: 0
        };

        const tokenizationSpecification = {
            type: 'PAYMENT_GATEWAY',
            parameters: {
                gateway: 'placetopay',
                gatewayMerchantId: 'test'
            }
        };

        const baseCardPaymentMethod = {
            type: 'CARD',
            parameters: {
                allowedAuthMethods: ["PAN_ONLY", "CRYPTOGRAM_3DS"],
                allowedCardNetworks: ["AMEX", "DISCOVER", "INTERAC", "JCB", "MASTERCARD", "VISA"]
            }
        };

        const cardPaymentMethod = Object.assign(
            {},
            baseCardPaymentMethod,
            {
                tokenizationSpecification: tokenizationSpecification
            }
        );

        let paymentsClient = null;

        // 2. Inicializar Cliente y verificar si está listo para pagar
        function onGooglePayLoaded() {
            paymentsClient = new google.payments.api.PaymentsClient({
                environment: 'TEST'
            });

            const isReadyToPayRequest = Object.assign({}, baseRequest);
            isReadyToPayRequest.allowedPaymentMethods = [baseCardPaymentMethod];

            paymentsClient.isReadyToPay(isReadyToPayRequest)
                .then(function(response) {
                    if (response.result) {
                        addGooglePayButton();
                    }
                })
                .catch(function(err) {
                    console.error('Error en isReadyToPay:', err);
                });
        }

        // 3. Crear y montar el botón
        function addGooglePayButton() {
            const button = paymentsClient.createButton({
                onClick: onGooglePaymentButtonClicked,
                allowedPaymentMethods: [baseCardPaymentMethod],
                buttonType: 'pay',
                buttonTheme: 'dark',
                buttonLocale: 'es'
            });
            document.getElementById('gpay-container').appendChild(button);
        }

        // 4. Manejar el click y solicitar los datos de pago
        function onGooglePaymentButtonClicked() {
            const paymentDataRequest = Object.assign({}, baseRequest);
            paymentDataRequest.allowedPaymentMethods = [cardPaymentMethod];
            paymentDataRequest.transactionInfo = {
                totalPriceStatus: 'FINAL',
                totalPrice: '0.1', // Monto de prueba (debe ser formato moneda)
                currencyCode: 'USD',
                countryCode: 'US'
            };
            paymentDataRequest.merchantInfo = {
                merchantName: 'Example Merchant',
                merchantId: getMerchantIdInput()
            };

            paymentsClient.loadPaymentData(paymentDataRequest)
                .then(function(paymentData) {
                    // Extraer el token exactamente como lo entrega Google
                    const paymentToken = paymentData.paymentMethodData.tokenizationData.token;

                    // Llenar el textarea del formulario
                    const tokenTextarea = document.getElementById('token');
                    tokenTextarea.value = paymentToken;

                    // Pequeño feedback visual (opcional)
                    tokenTextarea.style.backgroundColor = '#dcfce7'; // verde claro
                    setTimeout(() => { tokenTextarea.style.backgroundColor = ''; }, 1000);

                    console.log('Token obtenido correctamente', paymentToken);
                })
                .catch(function(err) {
                    console.error('Error en loadPaymentData:', err);
                });
        }

        // Obtener el merchantId del input dinámicamente
        function getMerchantIdInput() {
            return document.getElementById('merchantId')?.value || 'placetopay';
        }

        const clearButton = document.getElementById('clear-form');
        if (clearButton) {
            clearButton.addEventListener('click', function () {
                const tokenTextarea = document.getElementById('token');
                if (tokenTextarea) {
                    tokenTextarea.value = '';
                    tokenTextarea.style.backgroundColor = '';
                }

                const errorBlock = document.getElementById('error-block');
                if (errorBlock) {
                    errorBlock.style.display = 'none';
                }

                const successBlock = document.getElementById('success-block');
                if (successBlock) {
                    successBlock.style.display = 'none';
                }

                const errorMessage = document.getElementById('error-message');
                if (errorMessage) {
                    errorMessage.style.display = 'none';
                }

                const resultBlock = document.getElementById('result-block');
                if (resultBlock) {
                    resultBlock.style.display = 'none';
                }
            });
        }
    </script>
</div>
</body>
</html>
