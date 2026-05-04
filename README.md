
# Google Pay SDK Test Harness

A small Laravel app to test [Google Pay's PAYMENT_GATEWAY](https://developers.google.com/pay/api/web/guides/tutorial) tokenization end-to-end with the [`placetopay/googlepay-sdk`](https://github.com/placetopay-org/google-pay-php-sdk) PHP library.

It provides two pages:

- `/` — Generate a TEST token using the official Google Pay JS button and decrypt it locally.
- `/prod-test` — Paste a payload (TEST or PROD) and decrypt it using the corresponding configured private key. Useful for **key-rotation validation** when Google sends you encrypted PROD payloads.

---

## Requirements

- PHP 8.2+
- Composer
- Node.js 18+ (only if you plan to recompile assets)
- A web server (Valet, Herd, `php artisan serve`, etc.)
- OpenSSL extension enabled (used by the SDK to verify ECDSA signatures)

---

## Installation

```bash
git clone <this-repo>
cd test-google-sdk

composer install
cp .env.example .env
php artisan key:generate

# SQLite is used by default
touch database/database.sqlite
php artisan migrate
```

Start the app:

```bash
php artisan serve
# or, with Valet/Herd
# open http://test-google-sdk.test
```

---

## Configuration

All Google Pay options live in `config/googlepay.php` and are populated from `.env`.

### `.env` keys

```dotenv
# ============================================
# GooglePay SDK
# ============================================

# TEST: gateway name registered with Google (used as recipientId for PAYMENT_GATEWAY)
GOOGLEPAY_MERCHANT_ID=placetopay

# TEST private key (one line, use \n for line breaks inside the PEM)
GOOGLEPAY_PRIVATE_KEY="-----BEGIN EC PRIVATE KEY-----\nMHcCAQEE...\n-----END EC PRIVATE KEY-----"

# Public-key endpoints used by the SDK to verify Google's signatures
GOOGLE_PAY_PAYMENT_METHOD_KEYS_URL=https://payments.developers.google.com/paymentmethodtoken/test/keys.json
GOOGLE_PAY_PAYMENT_METHOD_KEYS_URL_PROD=https://payments.developers.google.com/paymentmethodtoken/keys.json

GOOGLE_PAY_LOGGER_SANITIZER=false

# ============================================
# PROD (used by /prod-test when "PRODUCTION" is selected)
# ============================================
GOOGLEPAY_PROD_MERCHANT_ID=googletest
GOOGLEPAY_PROD_PRIVATE_KEY="-----BEGIN EC PRIVATE KEY-----\nMHcCAQEE...\n-----END EC PRIVATE KEY-----"
```

> **Important:** PEM keys must be stored on a single line. Replace each real newline with the literal characters `\n`. The config layer (`config/googlepay.php`) converts them back into actual newlines before handing them to the SDK.

After editing `.env`, clear the config cache:

```bash
php artisan config:clear
```

### What `merchant_id` actually is

When using `type: 'PAYMENT_GATEWAY'` in the Google Pay JS, Google builds the `recipientId` as `gateway:<gateway-name>`. The SDK rebuilds the **same** string for verification using the `merchantId` from PHP config.

**That means `GOOGLEPAY_MERCHANT_ID` must match the `gateway` value in the front-end JS** (e.g. `placetopay`). The `gatewayMerchantId` parameter (the merchant's id at that gateway) does **not** affect cryptographic verification.

---

## Usage

### 1. Live TEST flow at `/`

Open `http://test-google-sdk.test/` (or whichever URL you serve the app on).

1. The Google Pay button mounts automatically (TEST environment).
2. Click **Pay with G Pay** and pick a TEST card from the popup.
3. The token returned by Google is auto-filled in the textarea.
4. Click **Desencriptar**.
5. The page redirects back showing:
   - `PASS` / `FAIL` banner
   - The original token (signedMessage JSON)
   - The decrypted payload (`card`, `brandToken`)

This validates that your **TEST** `merchant_id` and `private_key` are correctly paired with the `gateway` configured in the JS (`resources/views/test-form.blade.php`).

### 2. Decryption-validation page at `/prod-test`

Open `http://test-google-sdk.test/prod-test`.

This page does **not** generate a token. You paste a payload that was generated elsewhere (e.g. one Google sent you for key-rotation validation) and decrypt it.

1. Choose **Environment**:
   - `PRODUCTION` → uses `GOOGLEPAY_PROD_MERCHANT_ID`, `GOOGLEPAY_PROD_PRIVATE_KEY`, and the PROD keys URL.
   - `TEST` → uses the TEST values.
2. Paste the **full payload JSON** (the object that contains `signature`, `intermediateSigningKey`, `protocolVersion`, `signedMessage`).
3. Click **Desencriptar**.
4. You'll get a clear `PASS` / `FAIL` with the environment used.

#### Key-rotation flow

When you need to validate a key rotation (for example, payloads Google sends you to confirm a new public key works), use this page with `Environment = PRODUCTION` and swap `GOOGLEPAY_PROD_PRIVATE_KEY` in `.env` between the keys you want to test.

1. Set `GOOGLEPAY_PROD_PRIVATE_KEY` to the **current** PROD private key.
2. `php artisan config:clear`
3. On `/prod-test` choose `PRODUCTION` and decrypt every payload you have — record each `PASS` / `FAIL`.
4. Replace `GOOGLEPAY_PROD_PRIVATE_KEY` with the **new** PROD private key.
5. `php artisan config:clear`
6. Decrypt the same payloads again — record each `PASS` / `FAIL`.
7. A payload should `PASS` only with the private key that matches the public key it was encrypted with.

---

## Project structure

```
app/Http/Controllers/
├── TestFormController.php       # Handles /
└── ProdPayloadController.php    # Handles /prod-test

config/
└── googlepay.php                # Reads all GOOGLEPAY_* env vars

resources/views/
├── test-form.blade.php          # Google Pay button + form
└── prod-test.blade.php          # Manual payload form

routes/web.php                   # Routes for both pages
```

---

## Troubleshooting

### `openssl_verify result: 0 / OpenSSL verification: FALSE`

The `signedMessage` couldn't be verified. The most common causes:

- `GOOGLEPAY_MERCHANT_ID` does **not** match the `gateway` used in the front-end JS or the gateway registered with Google for the payload you're decrypting.
- You're pointing at the wrong public-key endpoint (TEST vs PROD).
- The PEM key in `.env` got corrupted (extra spaces, missing `\n`, multi-line value).
- The token was generated for a **different** merchant/gateway than the one in `.env`.

### "Falta configurar en .env: GOOGLEPAY_..."

The corresponding env var is missing or empty. Add it, then run:

```bash
php artisan config:clear
```

### Verifying that `.env` values are actually loaded

```bash
php artisan tinker --execute="
echo 'TEST merchant: '   . config('googlepay.merchant_id') . PHP_EOL;
echo 'PROD merchant: '   . config('googlepay.prod_merchant_id') . PHP_EOL;
echo 'TEST key length: ' . strlen(config('googlepay.private_key')) . PHP_EOL;
echo 'PROD key length: ' . strlen(config('googlepay.prod_private_key')) . PHP_EOL;
echo 'TEST URL: '        . config('googlepay.payment_method_keys_url') . PHP_EOL;
echo 'PROD URL: '        . config('googlepay.prod_payment_method_keys_url') . PHP_EOL;
"
```

A length of `0` means the env var is empty or wasn't reloaded — clear the config cache and try again.

### Tail logs while testing

```bash
tail -f storage/logs/laravel.log | grep -i "googlepay\|verify\|prod"
```

---

## Tests

```bash
php artisan test
```

---

## License

This test harness is intended for internal use. The bundled Laravel framework is licensed under the [MIT license](https://opensource.org/licenses/MIT).
