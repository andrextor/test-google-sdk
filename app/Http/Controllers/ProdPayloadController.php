<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Placetopay\GooglepaySdk\Enums\GooglePayEnvironment;
use Placetopay\GooglepaySdk\Exceptions\GooglepaySdkException;
use Placetopay\GooglepaySdk\GooglePay;

class ProdPayloadController extends Controller
{
    public function show(): View
    {
        return view('prod-test', [
            'success' => session('success'),
            'error' => session('error'),
            'decrypted' => session('decrypted'),
            'token' => session('token'),
            'environment' => session('environment'),
        ]);
    }

    public function decrypt(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'environment' => ['required', 'in:TEST,PRODUCTION'],
        ]);

        $environment = $data['environment'];
        $isProd = $environment === 'PRODUCTION';

        $merchantId = $isProd
            ? (string) config('googlepay.prod_merchant_id')
            : (string) config('googlepay.merchant_id');

        $privateKey = $isProd
            ? (string) config('googlepay.prod_private_key')
            : (string) config('googlepay.private_key');

        $paymentMethodKeysUrl = $isProd
            ? (string) config('googlepay.prod_payment_method_keys_url')
            : (string) config('googlepay.payment_method_keys_url');

        $sanitizer = config('googlepay.logger_sanitizer');

        if ($merchantId === '' || $privateKey === '') {
            $missing = [];
            if ($merchantId === '') {
                $missing[] = $isProd ? 'GOOGLEPAY_PROD_MERCHANT_ID' : 'GOOGLEPAY_MERCHANT_ID';
            }
            if ($privateKey === '') {
                $missing[] = $isProd ? 'GOOGLEPAY_PROD_PRIVATE_KEY' : 'GOOGLEPAY_PRIVATE_KEY';
            }

            $request->flash();

            return redirect()->route('prod-test.show')
                ->with('success', false)
                ->with('environment', $environment)
                ->with('error', 'Falta configurar en .env: ' . implode(', ', $missing) . '. Recuerda que la private key debe ir en una sola línea con \\n.');
        }

        try {
            $google = new GooglePay([
                'merchantId' => $merchantId,
                'privateKey' => $privateKey,
                'paymentMethodKeysUrl' => $paymentMethodKeysUrl,
                'logger' => logger(),
                'loggerSettings' => [
                    'sanitizer' => $sanitizer,
                ],
                'httpLogger' => [
                    'enabled' => true,
                    'sanitizer' => false,
                ],
            ]);

            $envEnum = $isProd
                ? GooglePayEnvironment::PRODUCTION
                : GooglePayEnvironment::TEST;

            $payload = $google->checkout($data['token'], $envEnum);

            return redirect()->route('prod-test.show')->with([
                'decrypted' => [
                    'card' => $payload->card?->toArray(),
                    'brandToken' => $payload->brandToken?->toArray(),
                ],
                'token' => $data['token'],
                'environment' => $environment,
                'success' => true,
            ]);

        } catch (GooglepaySdkException $exception) {
            $request->flash();

            return redirect()->route('prod-test.show')
                ->with('success', false)
                ->with('environment', $environment)
                ->with('error', $exception->getMessage());
        }
    }
}

