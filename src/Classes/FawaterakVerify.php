<?php

namespace DavidMaximous\Fawaterak\Classes;

use DavidMaximous\Fawaterak\Exceptions\MissingPaymentInfoException;
use Illuminate\Http\Request;

/**
 * Reads transactions back from Fawaterak and verifies every webhook.
 *
 * The paid webhook keeps the two way validation introduced in 1.x:
 *   1. the HMAC signature sent with the webhook must match
 *   2. the transaction is then re-read from the Fawaterak API and the
 *      identifiers must match what the webhook claimed
 */
class FawaterakVerify extends BaseController
{
    /* -----------------------------------------------------------------
     |  Transactions
     | -----------------------------------------------------------------
     */

    /**
     * Read one transaction. POST /api/v3/getTransactionData
     *
     * @param  string  $intentKey  The transaction intent_key.
     * @return array
     */
    public function getTransactionData($intentKey)
    {
        try {
            $response = $this->client()->oauth('post', 'api/v3/getTransactionData', [
                'intent_key' => $intentKey,
            ]);

            if (! $this->client()->succeeded($response)) {
                return [
                    'status' => 'failed',
                    'message' => $this->extractApiMessage($response['body']),
                    'process_data' => $response['raw'],
                ];
            }

            return $this->formatTransaction($response['body']['data'] ?? []);
        } catch (\Exception $e) {
            return [
                'status' => 'failed',
                'message' => $e->getMessage(),
                'process_data' => [],
            ];
        }
    }

    /**
     * Alias of getTransactionData() with a clearer name.
     *
     * @param  string  $intentKey
     * @return array
     */
    public function getTransaction($intentKey)
    {
        return $this->getTransactionData($intentKey);
    }

    /**
     * Shape a transaction payload.
     *
     * @param  array  $data
     * @return array
     */
    protected function formatTransaction(array $data)
    {
        $intentKey = $data['intent_key'] ?? null;

        return [
            'status' => 'success',
            'intent_key' => $intentKey,
            'transaction_id' => $data['transaction_id'] ?? null,
            'paid' => (int) ($data['paid'] ?? 0),
            'status_text' => $data['status_text'] ?? null,
            'paid_at' => $data['paid_at'] ?? null,
            'total' => $data['total'] ?? null,
            'currency' => $data['currency'] ?? null,
            'commission' => $data['commission'] ?? null,
            'customer_email' => $data['customer_email'] ?? null,
            'payment_method' => $data['payment_method'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'transaction_created_at' => $data['transaction_created_at'] ?? null,
            'transaction_link' => $data['transaction_link'] ?? null,
            'transaction_history' => $data['transaction_history'] ?? [],
            'payload' => $data['pay_load'] ?? null,
            'process_data' => $data,
        ];
    }

    /**
     * Paginated export of your transactions.
     * GET /api/v3/getTransactionsData
     *
     * @param  string  $startDate  Y-m-d
     * @param  string  $endDate  Y-m-d
     * @param  int  $perPage
     * @param  int  $page
     * @param  string|null  $payLoad  Optional substring filter on pay_load.
     * @return array
     *
     * @throws MissingPaymentInfoException
     */
    public function listTransactions($startDate, $endDate, $perPage = 15, $page = 1, $payLoad = null)
    {
        if (empty($startDate)) {
            throw new MissingPaymentInfoException('start_date');
        }

        if (empty($endDate)) {
            throw new MissingPaymentInfoException('end_date');
        }

        $query = [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'per_page' => (int) $perPage,
            'page' => (int) $page,
        ];

        if (! empty($payLoad)) {
            $query['pay_load'] = $payLoad;
        }

        try {
            $response = $this->client()->oauth('get', 'api/v3/getTransactionsData', $query);

            if (! $this->client()->succeeded($response)) {
                return $this->apiErrorResponse($response);
            }

            return [
                'status' => 'success',
                'data' => $response['body']['data'] ?? [],
                'pagination' => $response['body']['pagination'] ?? [],
            ];
        } catch (\Exception $e) {
            return $this->exceptionResponse($e);
        }
    }

    /* -----------------------------------------------------------------
     |  Webhooks
     | -----------------------------------------------------------------
     */

    /**
     * Verify the paid / pending webhook.
     *
     * Alias of verifyPaidCallback(), kept as the 1.x entry point.
     *
     * @param  Request|array  $request
     * @return array
     */
    public function verifyCallback($request)
    {
        return $this->verifyPaidCallback($request);
    }

    /**
     * Verify the paid / pending webhook, then confirm it against the API.
     *
     * @param  Request|array  $request
     * @return array
     */
    public function verifyPaidCallback($request)
    {
        $data = $this->payloadOf($request);

        $dialect = $this->detectDialect($data);

        if (! $dialect) {
            return $this->securityResponse(null, $data);
        }

        $id = $dialect['id'];
        $key = $dialect['key'];
        $status = $dialect['status'];
        $paymentMethod = $data['payment_method'] ?? '';

        // Leg 1: the signature must match.
        $received = $dialect['is_v3']
            ? ($data['transactionHashKey'] ?? $data['hashKey'] ?? null)
            : ($data['hashKey'] ?? $data['transactionHashKey'] ?? null);

        if (! $this->signatureMatches($dialect['string_to_sign'] . '&PaymentMethod=' . $paymentMethod, $received)) {
            return $this->securityResponse($id, $data);
        }

        // Async methods notify twice: pending first, paid once collected.
        if ($status === 'pending') {
            return $this->pendingResponse($id, [
                'transaction_key' => $key,
                'transaction_id' => $id,
                'payment_method' => $paymentMethod,
                'reference_number' => $data['referenceNumber'] ?? null,
                'payload' => $this->decodePayload($data['pay_load'] ?? null),
                'process_data' => $data,
            ]);
        }

        if ($status !== 'paid') {
            return $this->failedResponse($id, $status, $data);
        }

        // Leg 2: read the transaction back from Fawaterak and cross check it.
        $transaction = $this->getTransactionData($key);

        if (($transaction['status'] ?? null) !== 'success' || (int) ($transaction['paid'] ?? 0) !== 1) {
            return $this->failedResponse($id, $transaction['status'] ?? 'failed', $transaction['process_data'] ?? $data);
        }

        if (! $this->identifiersMatch($transaction, $key, $id)) {
            return $this->securityResponse($id, $transaction['process_data'] ?? $data);
        }

        return [
            'success' => true,
            'transaction_key' => $key,
            'transaction_id' => $transaction['transaction_id'] ?: $id,
            'payload' => $transaction['payload'],
            'amount_paid' => $transaction['total'],
            'paid_amount' => $data['paidAmount'] ?? null,
            'currency' => $transaction['currency'],
            'payment_method' => $transaction['payment_method'] ?: $paymentMethod,
            'paid_at' => $transaction['paid_at'],
            'customer' => $data['customerData'] ?? [],
            'message' => __('fawaterak::messages.PAYMENT_DONE'),
            'process_data' => $transaction['process_data'],
        ];
    }

    /**
     * Verify the failed payment webhook.
     *
     * @param  Request|array  $request
     * @return array
     */
    public function verifyFailedCallback($request)
    {
        $data = $this->payloadOf($request);

        $dialect = $this->detectDialect($data);

        if (! $dialect) {
            return $this->securityResponse(null, $data);
        }

        $paymentMethod = $data['payment_method'] ?? '';

        $verified = $this->signatureMatches(
            $dialect['string_to_sign'] . '&PaymentMethod=' . $paymentMethod,
            $data['hashKey'] ?? $data['transactionHashKey'] ?? null
        );

        if (! $verified) {
            return $this->securityResponse($dialect['id'], $data);
        }

        return [
            'success' => false,
            'verified' => true,
            'payment_id' => $dialect['id'],
            'transaction_key' => $dialect['key'],
            'transaction_id' => $dialect['id'],
            'payment_method' => $paymentMethod,
            'amount' => $data['amount'] ?? null,
            'currency' => $data['paidCurrency'] ?? null,
            'error_message' => $data['errorMessage'] ?? null,
            'gateway_response' => $data['response'] ?? null,
            'payload' => $this->decodePayload($data['pay_load'] ?? null),
            'message' => ($data['errorMessage'] ?? null) ?: __('fawaterak::messages.PAYMENT_FAILED'),
            'process_data' => $data,
        ];
    }

    /**
     * Verify the cancel / expired reference webhook.
     *
     * @param  Request|array  $request
     * @return array
     */
    public function verifyCancelCallback($request)
    {
        $data = $this->payloadOf($request);

        $referenceId = $data['referenceId'] ?? null;
        $paymentMethod = $data['paymentMethod'] ?? '';

        $verified = $this->signatureMatches(
            'referenceId=' . $referenceId . '&PaymentMethod=' . $paymentMethod,
            $data['hashKey'] ?? null
        );

        if (! $verified) {
            return $this->securityResponse($referenceId, $data);
        }

        $status = strtoupper((string) ($data['status'] ?? ''));

        return [
            'success' => false,
            'verified' => true,
            'canceled' => true,
            'payment_id' => $referenceId,
            'reference_id' => $referenceId,
            'status' => $status,
            'payment_method' => $paymentMethod,
            'transaction_id' => $data['transactionId'] ?? null,
            'transaction_key' => $data['transactionKey'] ?? null,
            'payload' => $this->decodePayload($data['pay_load'] ?? null),
            'message' => $status === 'EXPIRED'
                ? __('fawaterak::messages.REFERENCE_EXPIRED')
                : __('fawaterak::messages.REFERENCE_CANCELED'),
            'process_data' => $data,
        ];
    }

    /**
     * Verify the refund webhook, sent when a refund request is approved.
     *
     * @param  Request|array  $request
     * @return array
     */
    public function verifyRefundCallback($request)
    {
        $data = $this->payloadOf($request);

        $transactionId = $data['transactionId'] ?? null;
        $amount = $data['amount'] ?? null;
        $currency = $data['currency'] ?? null;

        $verified = $this->signatureMatches(
            'transactionId=' . $transactionId . '&amount=' . $amount . '&currency=' . $currency,
            $data['hashKey'] ?? null
        );

        if (! $verified) {
            return $this->securityResponse($transactionId, $data);
        }

        return [
            'success' => true,
            'verified' => true,
            'refunded' => true,
            'payment_id' => $transactionId,
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'currency' => $currency,
            'status' => $data['status'] ?? null,
            'reason' => $data['reason'] ?? null,
            'approved_at' => $data['approvedAt'] ?? null,
            'message' => __('fawaterak::messages.REFUND_APPROVED'),
            'process_data' => $data,
        ];
    }

    /**
     * Verify the tokenization webhook, sent when a card token is created.
     *
     * @param  Request|array  $request
     * @return array
     */
    public function verifyTokenCallback($request)
    {
        $data = $this->payloadOf($request);

        $customerUniqueId = $data['customerUniqueId'] ?? null;
        $customerCardToken = $data['customerCardToken'] ?? null;

        $verified = $this->signatureMatches(
            'customerUniqueId=' . $customerUniqueId . '&customerCardToken=' . $customerCardToken,
            $data['hashKey'] ?? null
        );

        if (! $verified) {
            return $this->securityResponse($customerUniqueId, $data);
        }

        return [
            'success' => true,
            'verified' => true,
            'customer_unique_id' => $customerUniqueId,
            'customer_token' => $customerCardToken,
            'customer_card' => $data['customerCard'] ?? null,
            'card_brand' => $data['cardBrand'] ?? null,
            'card_token_unique_id' => $data['cardTokenUniqueId'] ?? null,
            'message' => __('fawaterak::messages.TOKEN_CREATED'),
            'process_data' => $data,
        ];
    }

    /* -----------------------------------------------------------------
     |  Webhook helpers
     | -----------------------------------------------------------------
     */

    /**
     * Accept either a Request or a plain array.
     *
     * Paid and failed webhooks arrive form encoded unless the webhook URL
     * contains "_json", so both are handled transparently.
     *
     * @param  Request|array  $request
     * @return array
     */
    protected function payloadOf($request)
    {
        if ($request instanceof Request) {
            return $request->all();
        }

        if (is_array($request)) {
            return $request;
        }

        if (is_object($request) && method_exists($request, 'all')) {
            return (array) $request->all();
        }

        return [];
    }

    /**
     * Work out whether the payload is an API v3 transaction webhook or a
     * legacy v2 invoice webhook, and build the string to sign.
     *
     * @param  array  $data
     * @return array|null
     */
    protected function detectDialect(array $data)
    {
        if (! empty($data['transaction_key'])) {
            return [
                'is_v3' => true,
                'id' => $data['transaction_id'] ?? null,
                'key' => $data['transaction_key'],
                'status' => $data['status'] ?? $data['invoice_status'] ?? null,
                'string_to_sign' => 'TransactionId=' . ($data['transaction_id'] ?? '') . '&TransactionKey=' . $data['transaction_key'],
            ];
        }

        if (! empty($data['invoice_key'])) {
            return [
                'is_v3' => false,
                'id' => $data['invoice_id'] ?? null,
                'key' => $data['invoice_key'],
                'status' => $data['invoice_status'] ?? $data['status'] ?? null,
                'string_to_sign' => 'InvoiceId=' . ($data['invoice_id'] ?? '') . '&InvoiceKey=' . $data['invoice_key'],
            ];
        }

        return null;
    }

    /**
     * Compare the identifiers returned by the API with the ones the webhook
     * claimed. This is the second leg of the two way validation.
     *
     * @param  array  $transaction
     * @param  string  $key
     * @param  mixed  $id
     * @return bool
     */
    protected function identifiersMatch(array $transaction, $key, $id)
    {
        if ((string) $transaction['intent_key'] !== (string) $key) {
            return false;
        }

        $apiId = $transaction['transaction_id'] ?? null;

        // transaction_id is 0 while the intent is still cache only, so it is
        // only compared when both sides actually carry a value.
        if (! empty($apiId) && ! empty($id) && (string) $apiId !== (string) $id) {
            return false;
        }

        return true;
    }

    /**
     * The vendor API key used to sign every webhook.
     *
     * Read from the config (not env) so it keeps working under config:cache.
     *
     * @return string
     */
    protected function vendorKey()
    {
        return (string) config('fawaterak.FAWATERAK_API_KEY');
    }

    /**
     * Constant time HMAC SHA256 comparison.
     *
     * @param  string  $stringToSign
     * @param  string|null  $received
     * @return bool
     */
    protected function signatureMatches($stringToSign, $received)
    {
        if (empty($received) || ! is_string($received)) {
            return false;
        }

        $expected = hash_hmac('sha256', $stringToSign, $this->vendorKey(), false);

        return hash_equals($expected, $received);
    }

    /**
     * Build the hash Fawaterak would send for a string. Useful in tests and
     * when you want to sign a request yourself.
     *
     * @param  string  $stringToSign
     * @return string
     */
    public function signature($stringToSign)
    {
        return hash_hmac('sha256', $stringToSign, $this->vendorKey(), false);
    }

    /**
     * Webhooks deliver pay_load as a JSON string, decode it when possible.
     *
     * @param  mixed  $payload
     * @return mixed
     */
    protected function decodePayload($payload)
    {
        if (! is_string($payload) || trim($payload) === '') {
            return $payload;
        }

        $decoded = json_decode($payload, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $payload;
    }

    /**
     * Map a payment method id to a readable name.
     *
     * Resolves against the payment methods enabled on your own account, so the
     * name is always current and localized. Falls back to a small offline map
     * when the list cannot be reached (no credentials, network down, ...) --
     * this method never throws.
     *
     * @param  string|int  $method  A payment_method_id.
     * @param  string|null  $lang  "en" or "ar". Defaults to the app locale.
     * @return string
     */
    public function matchPaymentMethod($method, ?string $lang = null)
    {
        try {
            $list = $this->paymentMethodsList($lang);

            foreach ($list as $id => $name) {
                if ((string) $id === (string) $method && $name !== '') {
                    return $name;
                }
            }
        } catch (\Throwable $e) {
            // Fall through to the offline map below.
        }

        return $this->staticPaymentMethodName($method);
    }

    /**
     * Offline payment method names, limited to the ids the Fawaterak API
     * specification documents.
     *
     * @param  string|int  $method
     * @return string
     */
    protected function staticPaymentMethodName($method)
    {
        return match ((string) $method) {
            '9' => 'card',
            '3' => 'fawry',
            '4' => 'mwallet',
            '12' => 'aman',
            '4' => 'basata',
            default => 'N/A',
        };
    }
}
