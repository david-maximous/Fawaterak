# Fawaterak for Laravel

Accept payments through [Fawaterak](https://fawaterk.com) — cards, Fawry, Meeza, wallets, Aman, Masary, Apple Pay. Built on Fawaterak **API v3**.

Requires PHP 8.0+ and Laravel 9–12.

---

## Install

```bash
composer require david-maximous/fawaterak
php artisan vendor:publish --tag=fawaterak-config
php artisan vendor:publish --tag=fawaterak-lang   # only if you want to edit the messages
```

You need **two** credentials from your Fawaterak dashboard:

| Credential | Dashboard location | Used by |
|---|---|---|
| OAuth client id + secret | Integrations → OAuth client credentials | transactions, payment methods, refunds |
| Vendor API key | Integrations → API key | webhook signatures, tokenization |

```dotenv
FAWATERAK_URL=https://staging.fawaterk.com/     # https://app.fawaterk.com/ when live

FAWATERAK_API_KEY=your-vendor-api-key
FAWATERAK_CLIENT_ID=your-oauth-client-id
FAWATERAK_CLIENT_SECRET=your-oauth-client-secret

FAWATERAK_SUCCESS_URL=https://yoursite.com/payments/success
FAWATERAK_FAIL_URL=https://yoursite.com/payments/failed
FAWATERAK_PENDING_URL=https://yoursite.com/payments/pending
FAWATERAK_BACK_URL=https://yoursite.com/checkout
FAWATERAK_WEBHOOK_URL=https://yoursite.com/webhooks/fawaterak_json
```

Optional keys: `FAWATERAK_TIMEOUT` (30), `FAWATERAK_LANG` (en), `FAWATERAK_CACHE_STORE` (app default), `FAWATERAK_METHODS_CACHE_TTL` (600), `FAWATERAK_REDIRECT_URL` (legacy single route name).

Access tokens are fetched, cached and refreshed automatically. You never handle them.

---

## The two modes of checkout

| | Set a payment method? | You get back | You do |
|---|---|---|---|
| **Hosted checkout** | No | `url` | Redirect the customer there; they pick how to pay |
| **Direct payment** | Yes, `setMethod()` | `payment_data` | Redirect for cards, or show the reference / QR yourself |

### Hosted checkout

```php
use DavidMaximous\Fawaterak\Classes\FawaterakPayment;

$response = (new FawaterakPayment())
    ->setFirstName('Ahmed')
    ->setLastName('Ali')
    ->setUserEmail('ahmed@example.com')
    ->setUserPhone('01000000000')
    ->setAmount(250)
    ->pay();

return redirect($response['url']);
```

```php
// response
[
    'status'       => 'success',
    'message'      => 'Transaction link created',
    'intent_key'   => '550e8400-e29b-41d4-a716-446655440000',
    'url'          => 'https://app.fawaterk.com/ts/a1b2c',
    'expires_in'   => 2592000,
    'payment_data' => null,
]
```

### Direct payment

```php
$response = (new FawaterakPayment())
    ->setFirstName('Ahmed')
    ->setLastName('Ali')
    ->setUserEmail('ahmed@example.com')
    ->setUserPhone('01000000000')
    ->setAmount(250)
    ->setMethod('fawry')
    ->pay();
```

```php
// response - Fawry / Aman / Masary
[
    'status'           => 'success',
    'intent_key'       => '550e8400-e29b-41d4-a716-446655440000',
    'url'              => null,
    'reference_number' => '981335305',        // show this to the customer
    'expire_date'      => '2021-07-06 15:53:41',
    'payment_data'     => ['referenceNumber' => '981335305', /* ... */],
]

// response - card: 'url' holds the 3-D Secure page, redirect to it
// response - wallet: 'system_reference' and 'iso_qr' are filled instead
```

**Store `intent_key` against your order.** It is what the webhook sends back.

Money is only confirmed by [the webhook](#webhooks), never by the customer reaching your success URL.

---

## FawaterakPayment

### `pay()`

Creates the transaction. Every setter below is shown; only the first five are required.

```php
$response = (new FawaterakPayment())
    ->setFirstName('Ahmed')
    ->setLastName('Ali')
    ->setUserEmail('ahmed@example.com')
    ->setUserPhone('01000000000')
    ->setAmount(250)
    ->setMethod('fawry')                              // optional - omit for hosted checkout
    ->setItemName('Order #1001')                      // optional - defaults to "default"
    ->setQuantity(2)                                  // optional - defaults to 1
    ->setCurrency('EGP')                              // optional - defaults to EGP
    ->setLanguage('ar')                               // optional - checkout page language
    ->setPayload(['order_id' => 1001])                // optional - returned in the webhook
    ->setAddress('Cairo')                             // optional
    ->setCustomerNumber('CUST-1001')                  // optional
    ->setCustomerUniqueId('user_12345')               // optional - required if saving the customer
    ->setSaveCustomer(true)                           // optional - saves to your customers list
    ->addCartItem('Blue T-shirt', 150, 2)             // optional - repeatable, replaces item/amount
    ->setCartItems([['name' => 'Cap', 'price' => 100, 'quantity' => 1]])  // optional - whole cart
    ->setCartTotal(400)                               // optional - overrides the computed total
    ->setTax('VAT', 14)                               // optional - percentage
    ->setDiscount('pcg', 10)                          // optional - 'pcg' percent or 'literal' fixed
    ->setTrNumber('ORD-1001')                         // optional - your own reference
    ->setDueDate(now()->addDays(3))                   // optional - defaults to +2 days
    ->setSendEmail(true)                              // optional - emails the link to the customer
    ->setSendSMS(false)                               // optional - SMSes the link
    ->setListStyle('v')                               // optional - 'h' (default) or 'v'
    ->setRedirectOption(true)                         // optional - force hosted checkout
    ->setAuthAndCapture(1)                            // optional - 0 or 1, card flows
    ->setMobileWalletNumber('01000000000')            // optional - required by some wallets
    ->setSuccessUrl('https://shop.test/thanks')       // optional - route name or URL
    ->setFailUrl('https://shop.test/failed')          // optional
    ->setPendingUrl('https://shop.test/pending')      // optional
    ->setBackUrl('https://shop.test/cart')            // optional
    ->setWebhookUrl('https://shop.test/hook_json')    // optional - overrides the dashboard URL
    ->pay();
```

You can also pass the first eleven positionally:

```php
$response = (new FawaterakPayment())->pay(
    250,                        // amount
    'Ahmed',                    // first name
    'Ali',                      // last name
    'ahmed@example.com',        // email
    '01000000000',              // phone
    'fawry',                    // optional - method
    'Order #1001',              // optional - item name
    1,                          // optional - quantity
    'EGP',                      // optional - currency
    'ar',                       // optional - language
    ['order_id' => 1001]        // optional - payload
);
```

Response keys: `status`, `message`, `intent_key`, `url`, `expires_in`, `payment_data`, `reference_number`, `expire_date`, `expiration_time`, `system_reference`, `iso_qr`.

On failure you get `['status' => 'error', 'message' => '...', 'errors' => [...], 'http_status' => 422]`.

**Payment method names** for `setMethod()`: `card` (or `visa`, `mastercard`), `fawry`, `mwallet` (or `meeza`), `aman`, `basata` (or `masary`), `applepay`. You can also pass a numeric id from `listPaymentMethods()`. An unknown name throws `MissingPaymentInfoException`.

### `debug()`

Returns the exact array `pay()` would send. Nothing is sent.

```php
$body = (new FawaterakPayment())
    ->setFirstName('Ahmed')
    ->setLastName('Ali')
    ->setUserEmail('ahmed@example.com')
    ->setUserPhone('01000000000')
    ->setAmount(250)
    ->debug();

dd($body);
```

```php
// response
[
    'cartTotal'       => 250.0,
    'currency'        => 'EGP',
    'customer'        => ['first_name' => 'Ahmed', 'last_name' => 'Ali', 'email' => '...', 'phone' => '...'],
    'cartItems'       => [['name' => 'default', 'price' => 250, 'quantity' => 1]],
    'redirectionUrls' => ['successUrl' => '...', 'failUrl' => '...', 'pendingUrl' => '...'],
    'lang'            => 'en',
]
```

### `listPaymentMethods()`

```php
$methods = (new FawaterakPayment())->listPaymentMethods(
    fresh: false   // optional - true bypasses the 10 minute cache
);
```

```php
// response
[
    'status' => 'success',
    'data' => [
        [
            'payment_method_id'      => 2,
            'name_en'                => 'Visa-Mastercard',
            'name_ar'                => 'فيزا -ماستر كارد',
            'redirect'               => 'true',      // "false" = direct dispatch
            'logo'                   => 'https://app.fawaterk.com/clients/payment_options/mastercard-visa.png',
            'commission_on_customer' => 2,
            'integration_status'     => 1,
        ],
    ],
    'vendorSettingsData' => ['custome_iframe_title' => null],
]
```

Only methods with **Integration status** enabled in your dashboard appear here.

### `paymentMethodsList()`

```php
$list = (new FawaterakPayment())->paymentMethodsList(
    lang: 'ar'   // optional - 'en' or 'ar', defaults to the app locale
);
```

```php
// response
[2 => 'فيزا -ماستر كارد', 3 => 'فوري', 4 => 'ميزا']
```

---

## FawaterakVerify

### `getTransactionData()`

```php
use DavidMaximous\Fawaterak\Classes\FawaterakVerify;

$transaction = (new FawaterakVerify())->getTransactionData(
    intentKey: '550e8400-e29b-41d4-a716-446655440000'
);
```

```php
// response
[
    'status'                 => 'success',   // the read worked; check 'paid' for the money
    'intent_key'             => '550e8400-e29b-41d4-a716-446655440000',
    'transaction_id'         => 12345,
    'paid'                   => 1,           // 1 or 0
    'status_text'            => 'paid',
    'paid_at'                => '2026-06-06 12:05:00',
    'total'                  => 100,
    'currency'               => 'EGP',
    'commission'             => 2.5,
    'customer_email'         => 'ahmed@example.com',
    'payment_method'         => 'Fawry',
    'due_date'               => '2026-06-08 12:00:00',
    'transaction_created_at' => '2026-06-06 12:00:00',
    'transaction_link'       => 'https://app.fawaterk.com/transactions/550e8400-...',
    'transaction_history'    => [['method' => ['name' => 'Fawry', 'logo' => '...'], 'amount' => '100.00 EGP', 'status' => 'success', 'reference' => '981335305', 'date' => '...']],
    'payload'                => ['order_id' => 'ORD-1001'],
    'process_data'           => [/* raw API response */],
]
```

### `listTransactions()`

```php
$result = (new FawaterakVerify())->listTransactions(
    startDate: '2026-01-01',
    endDate:   '2026-01-31',
    perPage:   50,        // optional - defaults to 15
    page:      1,         // optional - defaults to 1
    payLoad:   'ORD-'     // optional - substring filter on your payload
);
```

```php
// response
[
    'status' => 'success',
    'data' => [[
        'transaction_id'         => 12345,
        'intent_key'             => '550e8400-e29b-41d4-a716-446655440000',
        'status_text'            => 'paid',
        'total'                  => 100,
        'currency'               => 'EGP',
        'payment_method'         => 'Fawry',
        'customer_email'         => 'ahmed@example.com',
        'transaction_created_at' => '2026-06-06 12:00:00',
    ]],
    'pagination' => ['total' => 1, 'per_page' => 50, 'current_page' => 1, 'last_page' => 1, 'from' => 1, 'to' => 1],
]
```

### `matchPaymentMethod()`

```php
$name = (new FawaterakVerify())->matchPaymentMethod(
    method: 3,
    lang: 'en'   // optional - 'en' or 'ar', defaults to the app locale
);
// "Fawry"
```

Resolves against the methods enabled on your account, so ids are always current. Falls back to a small offline map if the list cannot be reached. Never throws.

### `signature()`

Builds the HMAC hash Fawaterak would send. Useful in tests.

```php
$hash = (new FawaterakVerify())->signature(
    stringToSign: 'TransactionId=12345&TransactionKey=550e8400-...&PaymentMethod=Fawry'
);
```

---

## Webhooks

Fawaterak POSTs to your server. This is the only trustworthy source of payment status.

| Webhook | Fires when | URL comes from |
|---|---|---|
| Paid / pending | Payment succeeds, or a reference is issued | `setWebhookUrl()` or dashboard → Webhook |
| Failed | A card or gateway payment fails | Dashboard → Failed webhook |
| Cancel | A reference expires or is cancelled | Dashboard → Cancellation webhook |
| Refund | A refund is approved | Dashboard → Refund webhook |
| Token created | A customer saves a card | `setTokenWebhookUrl()` |

Put `_json` in the paid and failed webhook paths to receive JSON instead of form data. Exclude every webhook route from CSRF.

**Paid webhooks are verified twice:** the HMAC signature must match your vendor API key, then the transaction is re-read from the Fawaterak API and the identifiers must match. Only then do you get `success: true`. Amounts come from the API, not the webhook body, so a tampered amount cannot reach your order.

### `verifyPaidCallback()`

```php
Route::post('/webhooks/fawaterak_json', function (Request $request) {
    $result = (new FawaterakVerify())->verifyPaidCallback($request);   // Request or plain array

    if ($result['success'] ?? false) {
        Order::where('intent_key', $result['transaction_key'])->first()->markAsPaid();
    } elseif ($result['pending'] ?? false) {
        // reference issued, nothing paid yet
    }

    return response()->json(['status' => 'ok']);   // answer 200 quickly
});
```

```php
// response - paid
[
    'success'         => true,
    'transaction_key' => '550e8400-e29b-41d4-a716-446655440000',
    'transaction_id'  => 12345,
    'amount_paid'     => 100,                  // from the API - trust this one
    'paid_amount'     => '100.00',             // from the webhook body
    'currency'        => 'EGP',
    'payment_method'  => 'Fawry',
    'paid_at'         => '2026-06-06 12:05:00',
    'payload'         => ['order_id' => 'ORD-1001'],
    'customer'        => ['customer_email' => 'ahmed@example.com', /* ... */],
    'message'         => 'operation completed successfully',
    'process_data'    => [/* raw */],
]

// response - pending
['success' => false, 'pending' => true, 'transaction_key' => '...', 'reference_number' => '981335305', 'payload' => [...], 'message' => 'Payment is pending, ...']

// response - rejected
['success' => false, 'payment_id' => 12345, 'message' => 'Security checks are not passed by the system', 'process_data' => [...]]
```

`verifyCallback()` is the same method under its original name.

### `verifyFailedCallback()`

```php
$result = (new FawaterakVerify())->verifyFailedCallback($request);
```

```php
// response
[
    'success'          => false,
    'verified'         => true,          // absent when the signature failed
    'transaction_key'  => '550e8400-...',
    'transaction_id'   => 12345,
    'payment_method'   => 'Visa-Mastercard',
    'amount'           => '150.00',
    'currency'         => 'EGP',
    'error_message'    => 'Payment declined by issuer',
    'gateway_response' => '{"gatewayCode":"DECLINED"}',
    'payload'          => ['order_id' => 'ORD-1001'],
    'process_data'     => [/* raw */],
]
```

### `verifyCancelCallback()`

```php
$result = (new FawaterakVerify())->verifyCancelCallback($request);
```

```php
// response
[
    'success'         => false,
    'verified'        => true,
    'canceled'        => true,
    'reference_id'    => 998877,
    'status'          => 'EXPIRED',      // or "CANCELED"
    'payment_method'  => 'Aman',
    'transaction_id'  => 12345,
    'transaction_key' => '550e8400-...',
    'payload'         => ['order_id' => 'ORD-1001'],
    'message'         => 'The payment reference has expired',
]
```

### `verifyRefundCallback()`

```php
$result = (new FawaterakVerify())->verifyRefundCallback($request);
```

```php
// response
[
    'success'        => true,
    'verified'       => true,
    'refunded'       => true,
    'transaction_id' => 12345,
    'amount'         => '50.00',
    'currency'       => 'EGP',
    'status'         => 1,
    'reason'         => 'Customer requested partial refund',
    'approved_at'    => '2026-06-02 10:15:00',
    'message'        => 'Refund request approved',
]
```

### `verifyTokenCallback()`

```php
$result = (new FawaterakVerify())->verifyTokenCallback($request);
```

```php
// response
[
    'success'              => true,
    'verified'             => true,
    'customer_unique_id'   => '222111333',
    'customer_token'       => '9731673377207107',   // charge this later
    'customer_card'        => '512345xxxxxx0008',
    'card_brand'           => 'MASTERCARD',
    'card_token_unique_id' => '2345',               // delete with this
]
```

---

## FawaterakTokenization

Save a card once, charge it later. These endpoints use the vendor API key, not OAuth.

Flow: `createCardTokenScreen()` → customer enters card → token webhook → `payWithToken()` or `payRecurring()`.

### `createCardTokenScreen()`

```php
use DavidMaximous\Fawaterak\Classes\FawaterakTokenization;

$screen = (new FawaterakTokenization())
    ->setCustomerUniqueId('user_12345')
    ->setCustomerFirstName('Ahmed')
    ->setCustomerLastName('Ali')
    ->setCustomerEmail('ahmed@example.com')
    ->setCustomerPhone('01000000000')
    ->setCurrency('EGP')
    ->setAmount(100)                                   // optional - required if deducting
    ->setDeductTotalAmount(true)                       // optional - false authorises and voids
    ->setAllowedCardTypes(['visa', 'mastercard'])      // optional - also 'meeza'
    ->setFrequency('monthly')                          // optional
    ->setPayload(['plan' => 'pro'])                    // optional
    ->setSuccessUrl('https://shop.test/cards/saved')
    ->setFailUrl('https://shop.test/cards/failed')
    ->setTokenWebhookUrl('https://shop.test/webhooks/fawaterak/token')   // optional
    ->createCardTokenScreen();

return redirect($screen['redirect_url']);
```

```php
// response
[
    'status'       => 'success',
    'redirect_url' => 'https://staging.fawaterk.com/nbe/storeToken/19941166e947...',
    'message'      => 'Card token created successfully',
]
```

The link expires after about **10 minutes** and cannot be reused.

### `payWithToken()`

Customer present, goes through 3-D Secure.

```php
$result = (new FawaterakTokenization())
    ->setOrder(250, 'EGP')                             // amount, currency
    ->setCustomerToken('8lvfHBZC3n7lmTRO')
    ->setCardCvv('100')                                // optional - only if CVV check is on
    ->setPayload(['order_id' => 1002])                 // optional
    ->setSuccessUrl('https://shop.test/success')       // optional
    ->setFailUrl('https://shop.test/failed')           // optional
    ->payWithToken();

return redirect($result['redirect_to']);
```

```php
// response
['status' => 'success', 'redirect_to' => 'https://staging.fawaterk.com/mpgs/eyJpdiI6...auth']
```

### `payRecurring()`

Customer not present. Same parameters, no `tokenAction` is sent.

```php
$result = (new FawaterakTokenization())
    ->setOrder(99, 'EGP')
    ->setCustomerToken('UFZiaC98572rKZGZ')
    ->setCardCvv('100')                    // optional
    ->setPayload(['subscription_id' => 55])  // optional
    ->payRecurring();
```

```php
// response
['status' => 'success', 'transaction_id' => 1011767, 'message' => 'Recurring payment completed successfully']
```

### `deleteCustomerToken()`

```php
$result = (new FawaterakTokenization())
    ->setCustomerUniqueId('user_12345')
    ->setCardTokenUniqueId('2345')
    ->setDeleteTokenFromBank('9731673377207107')   // optional - also removes it at the bank
    ->deleteCustomerToken();
```

```php
// response
['status' => 'success', 'message' => 'token deleted']
```

---

## FawaterakRefund

A refund is submitted for review. Fawaterak calls your refund webhook once approved.

### `types()` and `reasons()`

```php
use DavidMaximous\Fawaterak\Classes\FawaterakRefund;

$refund = new FawaterakRefund();

$refund->types(lang: 'en');     // optional - 'en' or 'ar', defaults to the app locale
$refund->reasons(lang: 'en');   // optional
```

```php
// types response
['status' => 'success', 'data' => ['0' => 'invoice', '1' => 'Payment link transaction', '2' => 'Collection link transaction', '3' => 'Integration transaction']]

// reasons response
['status' => 'success', 'data' => ['Product not available', 'Customer changed their mind', 'Incorrect product delivered', 'Other']]
```

### `create()`

```php
$result = (new FawaterakRefund())
    ->setRefundType(FawaterakRefund::INTEGRATION_TRANSACTION)
    ->setRefundId(12345)
    ->setReason('Customer changed their mind')     // must be one of reasons()
    ->setRefundableAmount(100)                     // partial or full
    ->setComment('Returned one item')              // optional - internal note
    ->create();
```

```php
// response
['status' => 'success', 'message' => 'Refunded successfully']
```

Type constants: `INVOICE` (0), `PAYMENT_LINK` (1), `COLLECTION_LINK` (2), `INTEGRATION_TRANSACTION` (3 — what `pay()` creates).

### `all()`, `details()`, `delete()`

```php
$refund->all();               // 10 per page
$refund->details(id: 42);
$refund->delete(id: 42);      // optional arg - or use setRefundId(42) first
```

```php
// all() response
[
    'status' => 'success',
    'data' => [[
        'id' => 42, 'refundable_type' => 'Invoice', 'refundable_amount' => 50,
        'status' => 'pending', 'refundable_id' => 12345, 'reason' => 'Other',
        'created_at' => '2026-06-01 10:00:00', 'can_delete' => true,
    ]],
    'pagination' => ['total' => 1, 'per_page' => 10, 'current_page' => 1, 'last_page' => 1, /* ... */],
]

// details() response
['status' => 'success', 'data' => ['id' => 42, 'refundable_amount' => 50, 'status' => 'pending', 'reason' => 'Other', 'created_at' => '...']]

// delete() response
['status' => 'success', 'message' => 'Refund request canceled successfully']
```

Approved refunds cannot be deleted.

---

## FawaterakAuth

Tokens are automatic. Reach for this only when you need control.

```php
use DavidMaximous\Fawaterak\Classes\FawaterakAuth;

$auth = new FawaterakAuth();

$auth->token(force: false);   // optional arg - true skips the cache
$auth->issue();               // new client_credentials exchange
$auth->refresh(refreshToken: null);  // optional arg - defaults to the stored one
$auth->forget();              // drop the cached token, e.g. after switching environments
$auth->isConfigured();        // bool
```

```php
// issue() / refresh() response
['access_token' => 'eyJ0eXAiOi...', 'refresh_token' => 'def50200...', 'token_type' => 'Bearer', 'expires_in' => 31536000, 'expires_at' => 1780000000]
```

Throws `FawaterakAuthenticationException` when credentials are missing or rejected.

---

## Localization

Messages follow your app locale (`en` / `ar`), falling back to `FAWATERAK_LANG`.

```php
app()->setLocale('ar');
$result['message'];   // "تمت العملية بنجاح"
```

`setLanguage('ar')` is separate — it sets the language of the Fawaterak checkout page.

---

## Upgrading from 1.x

- Add `FAWATERAK_CLIENT_ID` and `FAWATERAK_CLIENT_SECRET`. Keep `FAWATERAK_API_KEY`.
- `intent_key` replaces `invoice_id` and `invoice_key`. `url` replaces `link`.
- `payload` is no longer double wrapped — drop the `[0]` index in your webhook handlers.
- `getTransactionData()` now takes the `intent_key`, and returns `status: 'success'` with `paid => 0` for an unpaid transaction instead of `status: 'failed'`.
- `setMethod()` with an unknown name now throws instead of silently sending nothing.
- `matchPaymentMethod(4)` returns "Meeza", not "Mobile Wallet".

---

## Troubleshooting

**Webhooks always fail verification** — `FAWATERAK_API_KEY` must exactly match the dashboard's vendor API key, and the route must be CSRF exempt.

**A payment method is missing** — enable its **Integration status** in Business settings → Payment method.

**Works on staging, not on live** — set `FAWATERAK_URL` to `https://app.fawaterk.com/`, use live credentials, then run `(new FawaterakAuth())->forget()`.

---

## Testing

```bash
composer install && composer test
```

---

MIT licensed. Built by [David Maximous](mailto:info@davidmaximous.me).
