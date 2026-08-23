<?php

namespace DavidMaximous\Fawaterak\Classes;

use DavidMaximous\Fawaterak\Exceptions\MissingPaymentInfoException;

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
     * Create a transaction. POST /api/v3/createTransaction
     *
     * Two modes, decided by whether a payment method was selected:
     *   - no method  -> hosted checkout, the response carries "url"
     *   - a method   -> direct payment, the response carries "payment_data"
     *
     * Every argument is optional: pass them positionally, or set them with the
     * fluent setters and call pay() with no arguments at all.
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
            $response = $this->client()->oauth('post', 'api/v3/createTransaction', $this->debug());

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
     * The exact request body pay() will send to Fawaterak.
     *
     * Nothing is sent when you call this. Use it to inspect, log or dd() the
     * payload while you are building an integration.
     *
     * @return array
     */
    public function debug(): array
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
        ];
    }

}
