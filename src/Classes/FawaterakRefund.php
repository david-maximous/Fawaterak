<?php

namespace DavidMaximous\Fawaterak\Classes;

use DavidMaximous\Fawaterak\Exceptions\MissingPaymentInfoException;
use DavidMaximous\Fawaterak\Traits\SetRefundVariables;

/**
 * Refund requests (API v3, OAuth authenticated).
 *
 * Typical flow:
 *   types()   -> pick a refund_type
 *   reasons() -> pick an allowed reason
 *   create()  -> submit the request
 *   all() / details() / delete()
 *
 * A refund is not instant: Fawaterak reviews the request and calls your
 * refund webhook once it is approved.
 */
class FawaterakRefund extends BaseController
{
    use SetRefundVariables;

    /** Refund an invoice. */
    const INVOICE = '0';

    /** Refund a payment link transaction. */
    const PAYMENT_LINK = '1';

    /** Refund a collection link transaction. */
    const COLLECTION_LINK = '2';

    /** Refund an integration (API) transaction. */
    const INTEGRATION_TRANSACTION = '3';

    /**
     * The available refund types. POST /api/v3/refund/refundTypes
     *
     * @param  string|null  $lang  "en" or "ar".
     * @return array
     */
    public function types(?string $lang = null): array
    {
        return $this->call('post', 'api/v3/refund/refundTypes', ['lang' => $this->resolveLang($lang)], function (array $body) {
            return [
                'status' => 'success',
                'data' => $body['data'] ?? [],
            ];
        });
    }

    /**
     * The allowed refund reasons. POST /api/v3/refund/refundReasons
     *
     * The reason passed to create() must be one of these strings.
     *
     * @param  string|null  $lang  "en" or "ar".
     * @return array
     */
    public function reasons(?string $lang = null): array
    {
        return $this->call('post', 'api/v3/refund/refundReasons', ['lang' => $this->resolveLang($lang)], function (array $body) {
            return [
                'status' => 'success',
                'data' => $body['data'] ?? [],
            ];
        });
    }

    /**
     * Submit a refund request. POST /api/v3/refund/create
     *
     * @return array
     *
     * @throws MissingPaymentInfoException
     */
    public function create(): array
    {
        $this->checkRequiredFields(['refund_type', 'refund_id', 'reason', 'refundable_amount']);

        $body = [
            'refund_type' => (string) $this->refund_type,
            'refund_id' => (int) $this->refund_id,
            'reason' => $this->reason,
            'refundable_amount' => $this->refundable_amount,
        ];

        if (! is_null($this->comment)) {
            $body['comment'] = $this->comment;
        }

        return $this->call('post', 'api/v3/refund/create', $body, function (array $response) {
            return [
                'status' => 'success',
                'message' => $response['message'] ?? __('fawaterak::messages.REFUND_CREATED'),
            ];
        }, __('fawaterak::messages.REFUND_FAILED'));
    }

    /**
     * List your refund requests, 10 per page. POST /api/v3/refund/index
     *
     * @return array
     */
    public function all(): array
    {
        // Paginator response: no status envelope, judge on the HTTP code only.
        return $this->call('post', 'api/v3/refund/index', [], function (array $body) {
            return [
                'status' => 'success',
                'data' => $body['data'] ?? [],
                'pagination' => [
                    'total' => $body['total'] ?? null,
                    'per_page' => $body['per_page'] ?? null,
                    'current_page' => $body['current_page'] ?? null,
                    'last_page' => $body['last_page'] ?? null,
                    'from' => $body['from'] ?? null,
                    'to' => $body['to'] ?? null,
                    'next_page_url' => $body['next_page_url'] ?? null,
                    'prev_page_url' => $body['prev_page_url'] ?? null,
                ],
            ];
        }, null, false);
    }

    /**
     * Alias of all().
     *
     * @return array
     */
    public function index(): array
    {
        return $this->all();
    }

    /**
     * One refund request. GET /api/v3/refund/details/{id}
     *
     * @param  int  $id
     * @return array
     */
    public function details($id): array
    {
        // The body is the refund itself, and its own "status" field (pending,
        // approved, ...) must not be mistaken for a response envelope.
        return $this->call('get', 'api/v3/refund/details/' . $id, [], function (array $body) {
            if (empty($body) || empty($body['id'])) {
                return [
                    'status' => 'failed',
                    'message' => __('fawaterak::messages.TRANSACTION_NOT_FOUND'),
                    'data' => null,
                ];
            }

            return [
                'status' => 'success',
                'data' => $body,
            ];
        }, null, false);
    }

    /**
     * Cancel a pending refund request. POST /api/v3/refund/delete
     *
     * Approved refunds cannot be deleted.
     *
     * @param  int|null  $id  Defaults to the value set with setRefundId().
     * @return array
     *
     * @throws MissingPaymentInfoException
     */
    public function delete($id = null): array
    {
        if (! is_null($id)) {
            $this->setRefundId($id);
        }

        $this->checkRequiredFields(['refund_id']);

        return $this->call('post', 'api/v3/refund/delete', ['refund_id' => (int) $this->refund_id], function (array $body) {
            return [
                'status' => 'success',
                'message' => $body['message'] ?? __('fawaterak::messages.REFUND_DELETED'),
            ];
        }, __('fawaterak::messages.REFUND_FAILED'));
    }

    /**
     * Perform an OAuth authenticated call and shape the result.
     *
     * @param  string  $method
     * @param  string  $path
     * @param  array  $body
     * @param  callable  $onSuccess
     * @param  string|null  $failureMessage
     * @param  bool  $statusEnvelope  Whether the body carries a "status" field
     *                                describing the response itself.
     * @return array
     */
    protected function call($method, $path, array $body, callable $onSuccess, $failureMessage = null, $statusEnvelope = true): array
    {
        try {
            $response = $this->client()->oauth($method, $path, $body);

            $ok = $statusEnvelope ? $this->client()->succeeded($response) : ($response['ok'] ?? false);

            if (! $ok) {
                $error = $this->apiErrorResponse($response);

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
