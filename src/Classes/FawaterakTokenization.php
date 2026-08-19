<?php

namespace DavidMaximous\Fawaterak\Classes;

use DavidMaximous\Fawaterak\Exceptions\MissingPaymentInfoException;
use DavidMaximous\Fawaterak\Traits\SetTokenizationVariables;

/**
 * Card tokenization and recurring payments.
 *
 * These are the v2 endpoints and they authenticate with the vendor API key,
 * not with the OAuth access token.
 *
 * Lifecycle:
 *   1. createCardTokenScreen() -> open the returned URL for the customer
 *   2. Fawaterak posts the new token to your tokenization webhook
 *      (verify it with FawaterakVerify::verifyTokenCallback())
 *   3. payWithToken() for an authenticated charge, or payRecurring() for a
 *      silent recurring charge
 *   4. deleteCustomerToken() when the customer removes the card
 */
class FawaterakTokenization extends BaseController
{
    use SetTokenizationVariables;

    /**
     * Generate the Fawaterak hosted card collection screen.
     * POST /api/v2/createCardTokenScreen
     *
     * The link expires after about 10 minutes.
     *
     * @return array
     *
     * @throws MissingPaymentInfoException
     */
    public function createCardTokenScreen(): array
    {
        $this->checkRequiredFields([
            'currency',
            'customer_unique_id',
        ]);

        $customer = $this->resolveCustomerData();

        $this->checkRequiredKeys($customer, [
            'customer_first_name',
            'customer_last_name',
            'customer_email',
            'customer_phone',
        ]);

        if ($this->deduct_total_amount && is_null($this->amount) && is_null($this->cart_total)) {
            throw new MissingPaymentInfoException('amount');
        }

        $order = [
            'currency' => $this->currency,
        ];

        if (! is_null($this->amount) || ! is_null($this->cart_total)) {
            $order['cartTotal'] = $this->resolveCartTotal();
            $order['cartItems'] = $this->resolveCartItems();
        }

        if (! is_null($this->frequency)) {
            $order['frequency'] = $this->frequency;
        }

        $body = [
            'order' => $order,
            'customerData' => $customer,
            // This endpoint uses snake_case redirection keys.
            'redirectionUrls' => array_filter([
                'success_url' => $this->resolveUrl($this->success_url) ?: $this->resolveUrl(config('fawaterak.FAWATERAK_SUCCESS_URL')),
                'fail_url' => $this->resolveUrl($this->fail_url) ?: $this->resolveUrl(config('fawaterak.FAWATERAK_FAIL_URL')),
                'webhook_url' => $this->resolveUrl($this->token_webhook_url) ?: $this->resolveUrl(config('fawaterak.FAWATERAK_WEBHOOK_URL')),
            ]),
        ];

        $this->checkRequiredKeys($body, ['redirectionUrls.success_url', 'redirectionUrls.fail_url']);

        if (! is_null($this->deduct_total_amount)) {
            $body['deduct_total_amount'] = $this->deduct_total_amount;
        }

        if (! is_null($this->allowed_card_types)) {
            $body['allowedCardTypes'] = $this->allowed_card_types;
        }

        if (! is_null($this->payload)) {
            // This endpoint uses payLoad, the pay endpoints use payload.
            $body['payLoad'] = $this->payload;
        }

        return $this->send('api/v2/createCardTokenScreen', $body, function (array $data) {
            return [
                'status' => 'success',
                'redirect_url' => $data['redirectUrl'] ?? null,
                'redirectUrl' => $data['redirectUrl'] ?? null,
                'message' => __('fawaterak::messages.TOKEN_CREATED'),
            ];
        });
    }

    /**
     * Charge a saved card with 3-D Secure authentication.
     * POST /api/v2/createTokenizationPayRequest
     *
     * @return array
     *
     * @throws MissingPaymentInfoException
     */
    public function payWithToken(): array
    {
        $body = $this->buildPayBody();
        $body['tokenAction'] = 'tokenization';

        return $this->send('api/v2/createTokenizationPayRequest', $body, function (array $data) {
            return [
                'status' => 'success',
                'redirect_to' => $data['redirectTo'] ?? null,
                'redirectTo' => $data['redirectTo'] ?? null,
                'link' => $data['redirectTo'] ?? null,
            ];
        }, __('fawaterak::messages.TOKEN_PAYMENT_FAILED'));
    }

    /**
     * Charge a saved card silently, without customer interaction.
     * POST /api/v2/createTokenizationPayRequest (no tokenAction)
     *
     * @return array
     *
     * @throws MissingPaymentInfoException
     */
    public function payRecurring(): array
    {
        return $this->send('api/v2/createTokenizationPayRequest', $this->buildPayBody(), function (array $data) {
            return [
                'status' => 'success',
                'transaction_id' => $data['transaction_id'] ?? null,
                'message' => __('fawaterak::messages.RECURRING_PAYMENT_DONE'),
            ];
        }, __('fawaterak::messages.TOKEN_PAYMENT_FAILED'));
    }

    /**
     * Delete a saved card token.
     * POST /api/v2/deleteCustomerToken
     *
     * @return array
     *
     * @throws MissingPaymentInfoException
     */
    public function deleteCustomerToken(): array
    {
        $this->checkRequiredFields(['customer_unique_id', 'card_token_unique_id']);

        $body = [
            'customerUniqueId' => $this->customer_unique_id,
            'cardTokenUniqueId' => $this->card_token_unique_id,
        ];

        if (! is_null($this->delete_token_from_bank)) {
            $body['deleteTokenFromBank'] = $this->delete_token_from_bank;
        }

        return $this->send('api/v2/deleteCustomerToken', $body, function (array $data) {
            return [
                'status' => 'success',
                'message' => $data['message'] ?? __('fawaterak::messages.TOKEN_DELETED'),
            ];
        });
    }

    /**
     * The body shared by both pay endpoints.
     *
     * @return array
     *
     * @throws MissingPaymentInfoException
     */
    protected function buildPayBody(): array
    {
        $this->checkRequiredFields(['amount', 'currency', 'customer_token']);

        $customerData = ['customer_token' => $this->customer_token];

        if (! is_null($this->card_cvv)) {
            $customerData['card_cvv'] = $this->card_cvv;
        }

        $body = [
            'order' => [
                'amount' => $this->amount,
                'currency' => $this->currency,
            ],
            'customerData' => $customerData,
        ];

        if (! is_null($this->payload)) {
            $body['payload'] = $this->payload;
        }

        // These endpoints use camelCase redirection keys.
        $redirectionUrls = array_filter([
            'successUrl' => $this->resolveUrl($this->success_url) ?: $this->resolveUrl(config('fawaterak.FAWATERAK_SUCCESS_URL')),
            'failUrl' => $this->resolveUrl($this->fail_url) ?: $this->resolveUrl(config('fawaterak.FAWATERAK_FAIL_URL')),
        ]);

        if (! empty($redirectionUrls)) {
            $body['redirectionUrls'] = $redirectionUrls;
        }

        return $body;
    }

    /**
     * Send a vendor authenticated request and shape the result.
     *
     * @param  string  $path
     * @param  array  $body
     * @param  callable  $onSuccess
     * @param  string|null  $failureMessage
     * @return array
     */
    protected function send($path, array $body, callable $onSuccess, $failureMessage = null): array
    {
        try {
            $response = $this->client()->vendor('post', $path, $body);

            if (! $this->client()->succeeded($response)) {
                $error = $this->apiErrorResponse($response);

                // Fall back to a friendlier message when Fawaterak sent none.
                if ($failureMessage && $error['message'] === __('fawaterak::messages.Process_Has_Been_Blocked_From_System')) {
                    $error['message'] = $failureMessage;
                }

                return $error;
            }

            return $onSuccess($response['body']);
        } catch (\Exception $e) {
            return $this->exceptionResponse($e);
        }
    }
}
