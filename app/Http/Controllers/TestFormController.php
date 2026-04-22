<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Placetopay\GooglepaySdk\Enums\GooglePayEnvironment;
use Placetopay\GooglepaySdk\Exceptions\GooglepaySdkException;
use Placetopay\GooglepaySdk\GooglePay;

class TestFormController extends Controller
{
    public function show(): View
    {
        return view('test-form', [
            'success' => session('success'),
            'error' => session('error'),
            'decrypted' => session('decrypted'),
            'token' => session('token'),
        ]);
    }

    public function decrypt(Request $request): View|RedirectResponse
    {
        $data = $request->validate(['token' => ['required', 'string']]);

        $merchantId = (string) config('googlepay.merchant_id');
        $privateKey = (string) config('googlepay.private_key');

        error_log('[TestFormController::decrypt] Using merchantId: ' . $merchantId);
        error_log('[TestFormController::decrypt] Private key first 50 chars: ' . substr($privateKey, 0, 50));

        $paymentMethodKeysUrl = (string) config('googlepay.payment_method_keys_url');
        $sanitizer =  config('googlepay.logger_sanitizer');

        if ($merchantId === '' || $privateKey === '') {
            $request->flash();

            return redirect('/')
                ->with('success', false)
                ->with('error', 'Configura GOOGLEPAY_MERCHANT_ID y GOOGLEPAY_PRIVATE_KEY en el .env antes de desencriptar.');
        }

        try {
            $google = new GooglePay([
                'merchantId' => $merchantId,
                'privateKey' => $privateKey,
                'logger' => logger(),
                'loggerSettings' => [
                    'sanitizer' => $sanitizer
                ],
                'httpLogger' => [
                    'enabled' =>  true,
                    'sanitizer' => false
                ],
            ]);

            $payload = $google->checkout($data['token'], GooglePayEnvironment::TEST);

            return redirect('/')->with([
                'decrypted' => [
                    'card' => $payload->card?->toArray(),
                    'brandToken' => $payload->brandToken?->toArray(),
                ],
                'token' => $data['token'],
                'success' => true,
            ]);

        } catch (GooglepaySdkException $exception) {
            $request->flash();

            return redirect('/')
                ->with('success', false)
                ->with('error', $exception->getMessage());
        }
    }
}
