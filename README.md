# Fawaterak for Laravel

Accept online payments in Egypt through [Fawaterak](https://fawaterk.com) — cards, Fawry, Meeza, mobile wallets, Aman, Masary and Apple Pay — with a fluent, readable API.

```php
$response = (new FawaterakPayment())
    ->setFirstName('Ahmed')
    ->setLastName('Ali')
    ->setUserEmail('ahmed@example.com')
    ->setUserPhone('01000000000')
    ->setAmount(250)
    ->pay();

return redirect($response['url']);
```

Built on **Fawaterak API v3**. Handles OAuth tokens for you, verifies every webhook signature, and cross checks each paid notification against the Fawaterak API before you trust it.

| | |
|---|---|
| **PHP** | 8.0 and up |
| **Laravel** | 9, 10, 11, 12 |
| **API** | Fawaterak v3 (transactions, refunds) + v2 (tokenization) |

---

## Table of contents

- [Install](#install)
- [Configuration](#configuration)
- [Quick start](#quick-start)
- [Creating transactions](#creating-transactions)
- [Payment methods](#payment-methods)
- [Reading transactions](#reading-transactions)
- [Webhooks](#webhooks)
- [Tokenization and recurring payments](#tokenization-and-recurring-payments)
- [Refunds](#refunds)
- [Localization](#localization)
- [Upgrading from 1.x](#upgrading-from-1x)
- [API reference](#api-reference)
- [Troubleshooting](#troubleshooting)

---

## Install

```bash
composer require david-maximous/fawaterak
```

Publish the config file, and the translations if you want to edit them:

```bash
php artisan vendor:publish --tag=fawaterak-config
php artisan vendor:publish --tag=fawaterak-lang
```

## Configuration

Fawaterak uses **two different credentials**, and you need both:

| Credential | Where to get it | Used for |
|---|---|---|
| **OAuth client id + secret** | Dashboard → Integrations → OAuth client credentials | Transactions, payment methods, refunds (`/api/v3/*`) |
| **Vendor API key** | Dashboard → Integrations → API key | Verifying every webhook signature, and tokenization / recurring |

Add them to your `.env`:

```dotenv
# https://staging.fawaterk.com/ while testing, https://app.fawaterk.com/ when live
FAWATERAK_URL=https://staging.fawaterk.com/

FAWATERAK_API_KEY=your-vendor-api-key
FAWATERAK_CLIENT_ID=your-oauth-client-id
FAWATERAK_CLIENT_SECRET=your-oauth-client-secret

# Where the customer comes back to. Route name or absolute URL, both work.
FAWATERAK_SUCCESS_URL=https://yoursite.com/payments/success
FAWATERAK_FAIL_URL=https://yoursite.com/payments/failed
FAWATERAK_PENDING_URL=https://yoursite.com/payments/pending
FAWATERAK_BACK_URL=https://yoursite.com/checkout

# Your server to server notification URL.
# Keep "_json" in the path to receive JSON instead of form-encoded data.
FAWATERAK_WEBHOOK_URL=https://yoursite.com/webhooks/fawaterak_json
```

That is all the setup there is. **Access tokens are handled for you** — the package requests one on first use, caches it until it expires, refreshes it in the background, and retries once if Fawaterak ever rejects it.

<details>
<summary>Every available config key</summary>

| Key | Default | What it does |
|---|---|---|
| `FAWATERAK_URL` | staging | Base URL. Switch to `https://app.fawaterk.com/` for production. |
| `FAWATERAK_API_KEY` | — | Vendor API key. Webhook signatures and tokenization. |
| `FAWATERAK_CLIENT_ID` | — | OAuth client id. |
| `FAWATERAK_CLIENT_SECRET` | — | OAuth client secret. |
| `FAWATERAK_SUCCESS_URL` | — | Redirect after a successful payment. |
| `FAWATERAK_FAIL_URL` | — | Redirect after a failed payment. |
| `FAWATERAK_PENDING_URL` | — | Redirect for pending payments. |
| `FAWATERAK_BACK_URL` | — | Back / cancel link on the hosted checkout. |
| `FAWATERAK_WEBHOOK_URL` | — | Per transaction webhook URL. |
| `FAWATERAK_REDIRECT_URL` | `payment-redirect` | Legacy single route name (see [Upgrading](#upgrading-from-1x)). |
| `FAWATERAK_CACHE_STORE` | app default | Cache store for the access token. |
| `FAWATERAK_TIMEOUT` | `30` | HTTP timeout in seconds. |
| `FAWATERAK_LANG` | `en` | Fallback language sent to Fawaterak. |
| `FAWATERAK_METHODS_CACHE_TTL` | `600` | Seconds to cache the payment methods list. `0` disables it. |

</details>

---

## Quick start

Send the customer to a Fawaterak hosted checkout page where they pick how to pay:

```php
use DavidMaximous\Fawaterak\Classes\FawaterakPayment;

Route::get('/checkout', function () {
    $response = (new FawaterakPayment())
        ->setFirstName('Ahmed')
        ->setLastName('Ali')
        ->setUserEmail('ahmed@example.com')
        ->setUserPhone('01000000000')
        ->setItemName('Order #1001')
        ->setAmount(250)
        ->setPayload(['order_id' => 1001])
        ->pay();

    if ($response['status'] !== 'success') {
        return back()->with('error', $response['message']);
    }

    // Save this. It is how you match the webhook back to your order.
    Order::find(1001)->update(['intent_key' => $response['intent_key']]);

    return redirect($response['url']);
});
```

You get back:

```php
[
    'status'      => 'success',
    'intent_key'  => '550e8400-e29b-41d4-a716-446655440000',
    'url'         => 'https://app.fawaterk.com/ts/a1b2c',
    'expires_in'  => 2592000,
    // ...
]
```

> **`intent_key` is the identifier that matters.** Store it against your order. It is what the webhook sends back and what you use to look the transaction up later.

The customer paying is **not** confirmation. Always fulfil orders from [the webhook](#webhooks).

---

## Creating transactions

### Hosted checkout vs direct payment

| | You do | You get back |
|---|---|---|
| **Hosted checkout** | Do not set a payment method | `url` — redirect the customer there |
| **Direct payment** | Call `setMethod(...)` | `payment_data` — a card redirect, a Fawry code, or a wallet request |

### Direct card payment

```php
$response = (new FawaterakPayment())
    ->setFirstName('Ahmed')->setLastName('Ali')
    ->setUserEmail('ahmed@example.com')->setUserPhone('01000000000')
    ->setAmount(250)
    ->setMethod('card')
    ->createTransaction();

return redirect($response['url']);   // 3-D Secure page
```

### Direct Fawry / Aman / Masary reference code

```php
$response = (new FawaterakPayment())
    ->setFirstName('Ahmed')->setLastName('Ali')
    ->setUserEmail('ahmed@example.com')->setUserPhone('01000000000')
    ->setAmount(250)
    ->setMethod('fawry')
    ->createTransaction();

// Show the customer the code, then wait for the webhook.
$response['reference_number'];  // "981335305"
$response['expire_date'];       // "2026-06-08 15:53:41"
```

The customer pays this code at a Fawry outlet, which can take days. You will receive a **pending** webhook now and a **paid** webhook when they actually pay.

### Direct mobile wallet (Meeza)

```php
$response = (new FawaterakPayment())
    ->setFirstName('Ahmed')->setLastName('Ali')
    ->setUserEmail('ahmed@example.com')->setUserPhone('01000000000')
    ->setAmount(250)
    ->setMethod('mwallet')
    ->setMobileWalletNumber('01000000000')
    ->createTransaction();

$response['system_reference'];  // wallet request reference
$response['iso_qr'];            // QR payload for scan to pay
```

### Picking a payment method

`setMethod()` accepts friendly names or a numeric id straight from Fawaterak:

```php
->setMethod('card')       // or 'visa', 'mastercard'
->setMethod('fawry')
->setMethod('mwallet')    // or 'meeza'
->setMethod('aman')
->setMethod('basata')     // or 'masary'
->setMethod('applepay')
->setMethod(37)           // any payment_method_id from listPaymentMethods()
```

Ids differ between accounts, so **treat [`listPaymentMethods()`](#payment-methods) as the source of truth** and use the aliases as a convenience. An unrecognised alias throws `MissingPaymentInfoException` rather than silently sending an empty method.

### A fuller example

```php
$response = (new FawaterakPayment())
    ->setFirstName('Ahmed')
    ->setLastName('Ali')
    ->setUserEmail('ahmed@example.com')
    ->setUserPhone('01000000000')
    ->setAddress('Cairo')
    ->setCustomerUniqueId('user_12345')
    ->setSaveCustomer(true)              // needs setCustomerUniqueId()

    ->addCartItem('Blue T-shirt', 150, 2)
    ->addCartItem('Cap', 100, 1)         // cartTotal becomes 400 automatically

    ->setCurrency('EGP')
    ->setLanguage('ar')
    ->setTax('VAT', 14)                  // percentage
    ->setDiscount('pcg', 10)             // 'pcg' percentage, 'literal' fixed
    ->setTrNumber('ORD-1001')
    ->setDueDate(now()->addDays(3))
    ->setSendEmail(true)
    ->setListStyle('v')                  // 'h' horizontal (default) or 'v'

    ->setSuccessUrl('checkout.thanks')   // route name or URL
    ->setFailUrl('https://shop.test/failed')
    ->setBackUrl('checkout.index')
    ->setWebhookUrl('https://shop.test/webhooks/fawaterak_json')

    ->setPayload(['order_id' => 1001, 'coupon' => 'EID25'])
    ->createTransaction();
```

Anything you put in `setPayload()` comes straight back to you in the webhook — it is the cleanest way to tie a payment to your own records.

Want to see exactly what will be sent before it goes out?

```php
$payment->buildTransactionBody();   // the full request array
```

---

## Payment methods

List the methods enabled on your account, with their real ids and logos:

```php
$methods = (new FawaterakPayment())->listPaymentMethods();

foreach ($methods['data'] as $method) {
    $method['payment_method_id'];  // 3
    $method['name_en'];            // "Fawry"
    $method['name_ar'];            // "فوري"
    $method['logo'];               // logo URL
    $method['redirect'];           // "true" hosted page, "false" direct dispatch
}
```

Just need a dropdown?

```php
(new FawaterakPayment())->paymentMethodsList();      // [2 => 'Visa-Mastercard', 3 => 'Fawry']
(new FawaterakPayment())->paymentMethodsList('ar');  // [2 => 'فيزا -ماستر كارد', 3 => 'فوري']
```

The list is cached for 10 minutes. Pass `listPaymentMethods(true)` to bypass the cache.

> Only methods with **Integration status** enabled in Business settings → Payment method appear here. If one is missing, enable it in the dashboard.

---

## Reading transactions

### One transaction

```php
use DavidMaximous\Fawaterak\Classes\FawaterakVerify;

$transaction = (new FawaterakVerify())->getTransactionData($intentKey);

$transaction['paid'];                 // 1 or 0
$transaction['status_text'];          // "paid", "unpaid", ...
$transaction['total'];                // 250
$transaction['currency'];             // "EGP"
$transaction['payment_method'];       // "Fawry", already localized
$transaction['paid_at'];
$transaction['pay_load'];             // what you sent in setPayload()
$transaction['transaction_history'];  // every payment attempt
```

`status` is `success` whenever the read worked — check `paid` to know whether money arrived.

### Many transactions

```php
$result = (new FawaterakVerify())->listTransactions(
    startDate: '2026-01-01',
    endDate:   '2026-01-31',
    perPage:   50,
    page:      1,
    payLoad:   'ORD-'        // optional filter on your payload
);

$result['data'];        // the transactions
$result['pagination'];  // total, per_page, current_page, last_page, from, to
```

---

## Webhooks

Fawaterak notifies your server about everything that happens. **This is the only trustworthy source of payment status** — a customer landing on your success URL proves nothing.

| Webhook | Fires when | Where to set the URL |
|---|---|---|
| **Paid / pending** | Payment succeeds, or a reference is issued | `setWebhookUrl()` per transaction, or dashboard → Webhook |
| **Failed** | A card or gateway payment fails | Dashboard → Failed webhook |
| **Cancel** | A reference expires or is cancelled | Dashboard → Cancellation webhook |
| **Refund** | A refund request is approved | Dashboard → Refund webhook |
| **Token created** | A customer saves a card | `setTokenWebhookUrl()` |

> **Put `_json` in the path** of your paid and failed webhook URLs (`/webhooks/fawaterak_json`) to receive JSON. Without it, Fawaterak posts form-encoded data. Either way the package handles it.

Every webhook route needs to be excluded from CSRF protection. In Laravel 11+ that is `bootstrap/app.php`; before that, `App\Http\Middleware\VerifyCsrfToken::$except`.

### How the paid webhook is verified

Anyone can POST to a public URL, so the package checks a payment **twice** before telling you it is real:

```
  Webhook arrives
        |
   1.   +--> Does the HMAC-SHA256 signature match your vendor API key?  --no--> rejected
        |
   2.   +--> Ask the Fawaterak API for this transaction directly.
        |    Is it really paid, and do the identifiers match?           --no--> rejected
        |
        +--> success: true
```

The second leg is what makes forgery impractical: even with a valid looking body, the answer comes from Fawaterak's own servers over an authenticated connection.

### Paid / pending

```php
use DavidMaximous\Fawaterak\Classes\FawaterakVerify;

Route::post('/webhooks/fawaterak_json', function (Request $request) {
    $result = (new FawaterakVerify())->verifyPaidCallback($request);

    if ($result['success'] ?? false) {
        $order = Order::where('intent_key', $result['transaction_key'])->first();

        $order->markAsPaid(
            amount: $result['amount_paid'],
            method: $result['payment_method'],
            paidAt: $result['paid_at'],
        );
    } elseif ($result['pending'] ?? false) {
        // Fawry / Aman code issued. Nothing has been paid yet.
        Order::where('intent_key', $result['transaction_key'])->update(['status' => 'awaiting_payment']);
    } else {
        Log::warning('Fawaterak webhook rejected', $result);
    }

    return response()->json(['status' => 'ok']);   // always answer 200 quickly
});
```

A successful result gives you:

```php
[
    'success'         => true,
    'transaction_key' => '550e8400-...',   // matches your stored intent_key
    'transaction_id'  => 12345,
    'amount_paid'     => 250,              // from the API, not the webhook body
    'currency'        => 'EGP',
    'payment_method'  => 'Fawry',
    'paid_at'         => '2026-06-06 12:05:00',
    'payload'         => ['order_id' => 1001],
    'customer'        => ['customer_email' => '...', ...],
    'message'         => 'operation completed successfully',
    'process_data'    => [...],            // the full API response
]
```

`amount_paid` deliberately comes from the API rather than from the webhook body, so a tampered amount cannot reach your order.

### Failed

```php
Route::post('/webhooks/fawaterak/failed', function (Request $request) {
    $result = (new FawaterakVerify())->verifyFailedCallback($request);

    if ($result['verified'] ?? false) {
        Order::where('intent_key', $result['transaction_key'])
            ->update(['status' => 'failed', 'failure_reason' => $result['error_message']]);
    }

    return response()->json(['status' => 'ok']);
});
```

### Cancelled or expired reference

```php
Route::post('/webhooks/fawaterak/cancel', function (Request $request) {
    $result = (new FawaterakVerify())->verifyCancelCallback($request);

    if ($result['verified'] ?? false) {
        // $result['status'] is "EXPIRED" or "CANCELED"
        Order::where('intent_key', $result['transaction_key'])->update(['status' => 'expired']);
    }

    return response()->json(['status' => 'ok']);
});
```

### Refund approved

```php
Route::post('/webhooks/fawaterak/refund', function (Request $request) {
    $result = (new FawaterakVerify())->verifyRefundCallback($request);

    if ($result['verified'] ?? false) {
        Refund::where('transaction_id', $result['transaction_id'])->update([
            'status'      => 'approved',
            'amount'      => $result['amount'],
            'approved_at' => $result['approved_at'],
        ]);
    }

    return response()->json(['status' => 'ok']);
});
```

### Card token created

```php
Route::post('/webhooks/fawaterak/token', function (Request $request) {
    $result = (new FawaterakVerify())->verifyTokenCallback($request);

    if ($result['verified'] ?? false) {
        User::where('reference', $result['customer_unique_id'])->first()->cards()->create([
            'token'      => $result['customer_token'],       // charge this later
            'token_id'   => $result['card_token_unique_id'], // delete with this
            'last_four'  => substr($result['customer_card'], -4),
            'brand'      => $result['card_brand'],
        ]);
    }

    return response()->json(['status' => 'ok']);
});
```

### When verification fails

Rejected webhooks return `success: false` with a `message` and the raw `process_data`. They are never thrown as exceptions, so log them and still answer `200` — Fawaterak retries otherwise.

---

## Tokenization and recurring payments

Save a customer card once, then charge it later without asking for the card again.

```
createCardTokenScreen()  ->  customer enters card  ->  token webhook  ->  payWithToken() / payRecurring()
```

### 1. Collect the card

```php
use DavidMaximous\Fawaterak\Classes\FawaterakTokenization;

$screen = (new FawaterakTokenization())
    ->setCustomerUniqueId('user_12345')
    ->setCustomerFirstName('Ahmed')
    ->setCustomerLastName('Ali')
    ->setCustomerEmail('ahmed@example.com')
    ->setCustomerPhone('01000000000')
    ->setCurrency('EGP')
    ->setAmount(100)
    ->setDeductTotalAmount(true)          // false authorises and voids instead
    ->setAllowedCardTypes(['visa', 'mastercard'])
    ->setSuccessUrl('https://shop.test/cards/saved')
    ->setFailUrl('https://shop.test/cards/failed')
    ->setTokenWebhookUrl('https://shop.test/webhooks/fawaterak/token')
    ->createCardTokenScreen();

return redirect($screen['redirect_url']);   // or load it in an iframe
```

> This link **expires after about 10 minutes** and cannot be reused. Generate it when the customer is ready to enter their card.

### 2. Receive the token

Fawaterak posts the new token to your token webhook — see [Card token created](#card-token-created) above. Store `customer_token`; that is what you charge.

### 3. Charge the saved card

**With authentication** (customer present, 3-D Secure):

```php
$result = (new FawaterakTokenization())
    ->setOrder(250, 'EGP')
    ->setCustomerToken($card->token)
    ->setPayload(['order_id' => 1002])
    ->setSuccessUrl('https://shop.test/payments/success')
    ->setFailUrl('https://shop.test/payments/failed')
    ->payWithToken();

return redirect($result['redirect_to']);
```

**Recurring** (customer not present, no interaction — subscriptions):

```php
$result = (new FawaterakTokenization())
    ->setOrder(99, 'EGP')
    ->setCustomerToken($subscription->card_token)
    ->setPayload(['subscription_id' => 55])
    ->payRecurring();

if ($result['status'] === 'success') {
    $subscription->renew($result['transaction_id']);
}
```

If CVV validation is enabled on your account, add `->setCardCvv('123')`.

### 4. Delete a saved card

```php
(new FawaterakTokenization())
    ->setCustomerUniqueId('user_12345')
    ->setCardTokenUniqueId($card->token_id)
    ->deleteCustomerToken();
```

Pass `->setDeleteTokenFromBank($providerToken)` to also remove it at the bank.

---

## Refunds

```php
use DavidMaximous\Fawaterak\Classes\FawaterakRefund;

$refund = new FawaterakRefund();

$refund->types();    // ['0' => 'invoice', '1' => 'Payment link transaction', ...]
$refund->reasons();  // ['Product not available', 'Customer changed their mind', ...]
```

The reason you submit **must** be one of the strings from `reasons()`. Both accept a language: `types('ar')`, `reasons('ar')`.

### Request a refund

```php
$result = (new FawaterakRefund())
    ->setRefundType(FawaterakRefund::INTEGRATION_TRANSACTION)
    ->setRefundId($order->fawaterak_transaction_id)
    ->setReason('Customer changed their mind')
    ->setRefundableAmount(100)              // partial or full
    ->setComment('Returned one item')       // optional, internal
    ->create();
```

Refund type constants:

| Constant | Value | Refunding |
|---|---|---|
| `FawaterakRefund::INVOICE` | `0` | An invoice |
| `FawaterakRefund::PAYMENT_LINK` | `1` | A payment link transaction |
| `FawaterakRefund::COLLECTION_LINK` | `2` | A collection link transaction |
| `FawaterakRefund::INTEGRATION_TRANSACTION` | `3` | An API transaction (what this package creates) |

A refund is **submitted, not settled**. Fawaterak reviews it and calls your [refund webhook](#refund-approved) once approved.

### Track refunds

```php
$refund->all();          // paginated list, 10 per page
$refund->details(42);    // one request
$refund->delete(42);     // cancel a still-pending request
```

Approved refunds cannot be deleted.

---

## Localization

Every message the package returns is translated. It follows your app locale, and falls back to `FAWATERAK_LANG`.

```php
app()->setLocale('ar');
$result['message'];   // "تمت العملية بنجاح"
```

Customise the wording:

```bash
php artisan vendor:publish --tag=fawaterak-lang
# -> lang/vendor/fawaterak/{en,ar}/messages.php
```

`setLanguage('ar')` separately controls the language of the Fawaterak hosted checkout page.

---

## Upgrading from 1.x

**Your existing code keeps working.** `pay()`, `verifyCallback()` and `getTransactionData()` all still exist with the same signatures, and the arrays they return still contain the old keys. Two things do need your attention:

### 1. Add OAuth credentials

Version 1.x authenticated everything with `FAWATERAK_API_KEY`. API v3 needs OAuth as well:

```dotenv
FAWATERAK_CLIENT_ID=...
FAWATERAK_CLIENT_SECRET=...
```

Keep `FAWATERAK_API_KEY` — webhooks and tokenization still use it.

### 2. Your payload is no longer double wrapped

1.x had a bug that wrapped your payload in an extra array, forcing code like this:

```php
$payload = json_decode($response['payload'], true);
$item = $payload[0]['item'];   // <- the [0] was the bug
```

Now the payload comes back exactly as you sent it:

```php
$item = $response['payload']['item'];
```

**Search your webhook handlers for `[0]` and remove it.**

### What changed under the hood

| 1.x | 2.0 |
|---|---|
| `pay()` → `POST /api/v2/invoiceInitPay` | `POST /api/v3/createTransaction` |
| `invoice_id` + `invoice_key` | a single `intent_key` — both old keys now return it |
| `link` | `url` — `link` still returned |
| `getTransactionData($invoiceId)` | same method, now takes the `intent_key` |
| Method ids hard coded | `listPaymentMethods()` returns your real ids |
| Webhook key read via `env()` | read via `config()`, so `config:cache` no longer breaks it |
| Paid webhook only | paid, pending, failed, cancel, refund and token webhooks |
| Hash compared with `==` | constant time `hash_equals()` |
| `Fawaterak` facade unusable | facade now bound and working |

Two behaviours were **fixed** rather than preserved:

- `getTransactionData()` used to report `status: 'failed'` for a valid unpaid transaction. It now returns `status: 'success'` with `paid => 0`; a failed **request** is still `status: 'failed'`. The paid webhook check is unaffected.
- `setMethod('nonsense')` used to silently send an empty method and produce a confusing API error. It now throws `MissingPaymentInfoException`.

If you were relying on the legacy `FAWATERAK_REDIRECT_URL` route name, it still works exactly as before — `/success`, `/failed` and `/pending` are appended to it whenever you have not set more specific URLs.

---

## API reference

### `FawaterakPayment`

| Method | Description |
|---|---|
| `createTransaction()` | Create a transaction. The canonical method. |
| `pay(...)` | 1.x alias, accepts the same positional arguments. |
| `listPaymentMethods($fresh = false)` | Your enabled payment methods. |
| `paymentMethodsList($lang = null)` | `[id => name]` map. |
| `buildTransactionBody()` | The exact request array, for debugging. |

**Setters** — all chainable, all with matching getters (`getAmount()`, `getCartItems()`, …):

`setAmount` `setFirstName` `setLastName` `setUserEmail` `setUserPhone` `setMethod` `setPaymentMethodId` `setItemName` `setQuantity` `setCurrency` `setPayload` `setLanguage` `setAddress` `setCustomerNumber` `setCustomerUniqueId` `setSaveCustomer` `setCartItems` `addCartItem` `setCartTotal` `setSuccessUrl` `setFailUrl` `setPendingUrl` `setBackUrl` `setWebhookUrl` `setSendEmail` `setSendSMS` `setDueDate` `setTrNumber` `setRedirectOption` `setAuthAndCapture` `setTax` `setDiscount` `setListStyle` `setMobileWalletNumber`

`reset()` clears everything so one instance can create several transactions.

### `FawaterakVerify`

| Method | Description |
|---|---|
| `getTransactionData($intentKey)` | Read one transaction. |
| `listTransactions($start, $end, $perPage, $page, $payLoad)` | Paginated export. |
| `verifyPaidCallback($request)` | Paid / pending webhook, two leg verification. |
| `verifyCallback($request)` | 1.x alias of the above. |
| `verifyFailedCallback($request)` | Failed payment webhook. |
| `verifyCancelCallback($request)` | Expired or cancelled reference webhook. |
| `verifyRefundCallback($request)` | Approved refund webhook. |
| `verifyTokenCallback($request)` | Card token created webhook. |
| `signature($stringToSign)` | Compute a hash yourself, useful in tests. |

All verifiers accept an `Illuminate\Http\Request` or a plain array.

### `FawaterakTokenization`

`createCardTokenScreen()` · `payWithToken()` · `payRecurring()` · `deleteCustomerToken()`

### `FawaterakRefund`

`types()` · `reasons()` · `create()` · `all()` · `details($id)` · `delete($id)`

### `FawaterakAuth`

You rarely need this, but it is there:

```php
use DavidMaximous\Fawaterak\Classes\FawaterakAuth;

$auth = new FawaterakAuth();
$auth->token();        // cached access token, fetched if needed
$auth->issue();        // force a new client_credentials exchange
$auth->refresh();      // use the stored refresh token
$auth->forget();       // drop the cached token
$auth->isConfigured(); // are the OAuth credentials set?
```

### Exceptions

| Exception | When |
|---|---|
| `MissingPaymentInfoException` | A required field was not set. |
| `FawaterakAuthenticationException` | OAuth credentials missing, invalid, or rejected twice. |
| `FawaterakRequestException` | Available for request level failures. |

API errors are **not** thrown — they come back as `['status' => 'error', 'message' => ..., 'errors' => [...]]` so a payment failure never breaks a request.

---

## Troubleshooting

**"Fawaterak client id and client secret are not configured"**
Set `FAWATERAK_CLIENT_ID` and `FAWATERAK_CLIENT_SECRET`, then `php artisan config:clear`.

**Webhooks always fail verification**
Check `FAWATERAK_API_KEY` matches the vendor API key in the dashboard exactly — it signs the hash. Confirm your route is excluded from CSRF. Log `$request->all()` and check `transactionHashKey` is present.

**Payment method missing from `listPaymentMethods()`**
Enable **Integration status** for it in Business settings → Payment method.

**Everything works on staging, nothing works on live**
`FAWATERAK_URL` must be `https://app.fawaterk.com/`, and live credentials are different from staging ones. Clear the cached token with `(new FawaterakAuth())->forget()` after switching.

**A transaction is created but never marked paid**
Payment status only arrives through webhooks. Confirm your webhook URL is publicly reachable and returns `200` quickly.

**`Route [x] not defined` used to be thrown**
Redirect URLs are now resolved safely — an unknown route name is passed through as a literal string instead of throwing during checkout.

---

## Testing

```bash
composer install
composer test
```

The suite fakes every HTTP call using the response examples from the official Fawaterak OpenAPI document, and covers token caching and refresh, all transaction modes, every webhook signature path (valid, tampered, legacy), tokenization and refunds.

---

## License

MIT. See [LICENSE](LICENSE).

Built by [David Maximous](mailto:info@davidmaximous.me).
