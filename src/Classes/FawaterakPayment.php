<?php

namespace DavidMaximous\Fawaterak\Classes;

use DavidMaximous\Fawaterak\Exceptions\MissingPaymentInfoException;
use Illuminate\Support\Facades\Cache;

/**
 * Creates Fawaterak transactions (API v3).
 *
 * Two modes, decided by whether a payment method was selected:
 *   - Hosted checkout : no method set -> you receive a checkout "url".
 *   - Direct payment  : a method set  -> you receive "payment_data" with the
 *                       card redirect, the Fawry style reference number, or
 *                       the wallet request.
 */
class FawaterakPayment extends BaseController
{
    /**
     * Create a transaction and return the checkout link or the payment data.
     *
     * Kept with the exact 1.x signature. The returned array contains the new
     * API v3 keys plus the legacy invoice_id / invoice_key / link aliases.
     *
     * @param  $amount
     * @param  null  $first_name
     * @param  null  $last_name
     * @param  null  $user_email
     * @param  null  $user_phone
     * @param  null  $method
     * @param  null  $item_name
     * @param  null  $quantity
     * @param  null  $currency
     * @param  null  $language
     * @param  array|null  $payload
     * @return array
     *
     * @throws MissingPaymentInfoException
     */
    public function pay($amount = null, $first_name = null, $last_name = null, $user_email = null, $user_phone = null, $method = null, $item_name = null, $quantity = null, $currency = null, $language = null, ?array $payload = []): array
    {
        $this->setPassedVariablesToGlobal($amount, $first_name, $last_name, $user_email, $user_phone, $method, $item_name, $quantity, $currency, $language, $payload);

        return $this->createTransaction();
    }

    /**
     * Create a transaction. POST /api/v3/createTransaction
     *
     * @return array
     *
     * @throws MissingPaymentInfoException
     */
    public function createTransaction(): array
    {
        $required_fields = ['amount', 'first_name', 'last_name', 'user_email', 'user_phone'];
        $this->checkRequiredFields($required_fields);

        // Saving the customer requires your own customer identifier.
        if ($this->save_customer) {
            $this->checkRequiredFields(['customer_unique_id']);
        }

        // Set defaults if not set
        $this->item_name ?? $this->item_name = 'default';
        $this->quantity ?? $this->quantity = '1';
        $this->currency ?? $this->currency = 'EGP';
        $this->language ?? $this->language = $this->resolveLang();

        try {
            $response = $this->client()->oauth('post', 'api/v3/createTransaction', $this->buildTransactionBody());

            if (! $this->client()->succeeded($response)) {
                return $this->apiErrorResponse($response);
            }

            return $this->formatTransactionResponse($response['body']);
        } catch (MissingPaymentInfoException $e) {
            throw $e;
        } catch (\Exception $e) {
            return $this->exceptionResponse($e);
        }
    }

    /**
     * The request body sent to createTransaction.
     *
     * Exposed so you can inspect or log exactly what will be sent.
     *
     * @return array
     */
    public function buildTransactionBody(): array
    {
        $body = [
            'cartTotal' => $this->resolveCartTotal(),
            'currency' => $this->currency ?: 'EGP',
            'customer' => array_filter([
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'email' => $this->user_email,
                'phone' => $this->user_phone,
                'address' => $this->address,
                'customer_number' => $this->customer_number,
                'customer_unique_id' => $this->customer_unique_id,
            ], function ($value) {
                return ! is_null($value) && $value !== '';
            }),
            'cartItems' => $this->resolveCartItems(),
            'redirectionUrls' => $this->resolveRedirectionUrls(),
            'lang' => $this->language ?: $this->resolveLang(),
        ];

        if (! is_null($this->payload)) {
            // API v3 expects a flat object under pay_load.
            $body['pay_load'] = $this->payload;
        }

        if (! is_null($this->method) && $this->method !== '') {
            $body['payment_method_id'] = (int) $this->method;
        }

        $optional = [
            'mobileWalletNumber' => $this->mobile_wallet_number,
            'save_customer' => $this->save_customer,
            'sendEmail' => $this->send_email,
            'sendSMS' => $this->send_sms,
            'due_date' => $this->due_date,
            'tr_number' => $this->tr_number,
            'redirectOption' => $this->redirect_option,
            'authAndCapture' => $this->auth_and_capture,
            'taxData' => $this->tax_data,
            'discountData' => $this->discount_data,
            'list_style' => $this->list_style,
        ];

        foreach ($optional as $key => $value) {
            if (! is_null($value)) {
                $body[$key] = $value;
            }
        }

        return $body;
    }

    /**
     * Alias of buildTransactionBody().
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->buildTransactionBody();
    }

    /**
     * Resolve the five redirection URLs.
     *
     * Priority for each URL: fluent setter -> dedicated config key -> the
     * legacy FAWATERAK_REDIRECT_URL route with /success, /failed and /pending
     * appended (1.x behaviour).
     *
     * @return array
     */
    protected function resolveRedirectionUrls(): array
    {
        $legacyBase = $this->resolveUrl(config('fawaterak.FAWATERAK_REDIRECT_URL'));

        $urls = [
            'successUrl' => $this->resolveUrl($this->success_url)
                ?: $this->resolveUrl(config('fawaterak.FAWATERAK_SUCCESS_URL'))
                ?: ($legacyBase ? $legacyBase . '/success' : null),

            'failUrl' => $this->resolveUrl($this->fail_url)
                ?: $this->resolveUrl(config('fawaterak.FAWATERAK_FAIL_URL'))
                ?: ($legacyBase ? $legacyBase . '/failed' : null),

            'pendingUrl' => $this->resolveUrl($this->pending_url)
                ?: $this->resolveUrl(config('fawaterak.FAWATERAK_PENDING_URL'))
                ?: ($legacyBase ? $legacyBase . '/pending' : null),

            'backUrl' => $this->resolveUrl($this->back_url)
                ?: $this->resolveUrl(config('fawaterak.FAWATERAK_BACK_URL')),

            'webhookUrl' => $this->resolveUrl($this->webhook_url)
                ?: $this->resolveUrl(config('fawaterak.FAWATERAK_WEBHOOK_URL')),
        ];

        return array_filter($urls, function ($value) {
            return ! empty($value);
        });
    }

    /**
     * Shape the createTransaction response.
     *
     * @param  array  $body
     * @return array
     */
    protected function formatTransactionResponse(array $body): array
    {
        $data = $body['data'] ?? [];
        $paymentData = $data['payment_data'] ?? null;
        $intentKey = $data['intent_key'] ?? null;

        $url = $data['url'] ?? null;

        if (is_array($paymentData) && ! empty($paymentData['redirectTo'])) {
            $url = $paymentData['redirectTo'];
        }

        return [
            'status' => $body['status'] ?? 'success',
            'message' => $body['message'] ?? __('fawaterak::messages.TRANSACTION_CREATED'),
            'intent_key' => $intentKey,
            'url' => $url,
            'expires_in' => $data['expires_in'] ?? null,
            'payment_data' => $paymentData,

            // Direct payment shortcuts
            'reference_number' => is_array($paymentData) ? ($paymentData['referenceNumber'] ?? null) : null,
            'expire_date' => is_array($paymentData) ? ($paymentData['expireDate'] ?? null) : null,
            'expiration_time' => is_array($paymentData) ? ($paymentData['expirationTime'] ?? null) : null,
            'system_reference' => is_array($paymentData) ? ($paymentData['systemReference'] ?? null) : null,
            'iso_qr' => is_array($paymentData) ? ($paymentData['isoQr'] ?? null) : null,

            // Backwards compatible aliases. API v3 has a single identifier
            // (intent_key) where v2 had an invoice id and an invoice key.
            'invoice_id' => $intentKey,
            'invoice_key' => $intentKey,
            'link' => $url,
        ];
    }

    /**
     * List the payment methods enabled for your account.
     * GET /api/v3/getTrPaymentmethods
     *
     * @param  bool  $fresh  Bypass the local cache.
     * @return array
     */
    public function listPaymentMethods(bool $fresh = false): array
    {
        $ttl = (int) config('fawaterak.FAWATERAK_METHODS_CACHE_TTL', 600);
        $key = 'fawaterak_payment_methods_' . sha1($this->client()->baseUrl());

        if (! $fresh && $ttl > 0) {
            $cached = Cache::get($key);

            if (is_array($cached)) {
                return $cached;
            }
        }

        try {
            $response = $this->client()->oauth('get', 'api/v3/getTrPaymentmethods');

            if (! $this->client()->succeeded($response)) {
                return $this->apiErrorResponse($response, __('fawaterak::messages.PAYMENT_METHODS_FETCH_FAILED'));
            }

            $result = [
                'status' => 'success',
                'data' => $response['body']['data'] ?? [],
                'vendorSettingsData' => $response['body']['vendorSettingsData'] ?? [],
            ];

            if ($ttl > 0) {
                Cache::put($key, $result, $ttl);
            }

            return $result;
        } catch (\Exception $e) {
            return $this->exceptionResponse($e);
        }
    }

    /**
     * Alias of listPaymentMethods().
     *
     * @param  bool  $fresh
     * @return array
     */
    public function paymentMethods(bool $fresh = false): array
    {
        return $this->listPaymentMethods($fresh);
    }

    /**
     * A flat [payment_method_id => localized name] map.
     *
     * @param  string|null  $lang  "en" or "ar". Defaults to the app locale.
     * @return array
     */
    public function paymentMethodsList(?string $lang = null): array
    {
        $lang = $this->resolveLang($lang);
        $methods = $this->listPaymentMethods();

        if (($methods['status'] ?? null) !== 'success') {
            return [];
        }

        $list = [];

        foreach ($methods['data'] as $method) {
            $id = $method['payment_method_id'] ?? null;

            if (is_null($id)) {
                continue;
            }

            $list[$id] = $lang === 'ar'
                ? ($method['name_ar'] ?? $method['name_en'] ?? '')
                : ($method['name_en'] ?? $method['name_ar'] ?? '');
        }

        return $list;
    }
}
