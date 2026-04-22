<?php

return [
    'merchant_id' => env('GOOGLEPAY_MERCHANT_ID', ''),
    'prod_merchant_id' => env('GOOGLEPAY_PROD_MERCHANT_ID', ''),
    'gateway_merchant_id' => env('GOOGLEPAY_GATEWAY_MERCHANT_ID', ''),
    'private_key' => str_replace('\n', "\n", env('GOOGLEPAY_PRIVATE_KEY', '')),
    'prod_private_key' => str_replace('\n', "\n", env('GOOGLEPAY_PROD_PRIVATE_KEY', '')),
    'payment_method_keys_url' => env('GOOGLE_PAY_PAYMENT_METHOD_KEYS_URL'),
    'prod_payment_method_keys_url' => env('GOOGLE_PAY_PAYMENT_METHOD_KEYS_URL_PROD', 'https://payments.developers.google.com/paymentmethodtoken/keys.json'),
    'logger_sanitizer' => env('GOOGLE_PAY_LOGGER_SANITIZER'),
];
