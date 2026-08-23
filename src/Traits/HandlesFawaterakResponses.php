<?php

namespace DavidMaximous\Fawaterak\Traits;

trait HandlesFawaterakResponses
{
    /**
     * A successful response. Always contains status => success.
     *
     * @param  array  $extra
     * @return array
     */
    public function successResponse(array $extra = [])
    {
        return array_merge(['status' => 'success'], $extra);
    }

    /**
     * The system refused / could not complete the operation.
     *
     * Shape kept identical to version 1.x.
     *
     * @param  mixed  $reference_id
     * @param  mixed  $code
     * @param  mixed  $process_data
     * @return array
     */
    public function failedResponse($reference_id, $code, $process_data)
    {
        return [
            'success' => false,
            'payment_id' => $reference_id,
            'message' => __('fawaterak::messages.PAYMENT_FAILED_WITH_CODE', ['CODE' => $code]),
            'process_data' => $process_data,
        ];
    }

    /**
     * Security checks (hash / cross check) did not pass.
     *
     * Shape kept identical to version 1.x.
     *
     * @param  mixed  $reference_id
     * @param  mixed  $process_data
     * @return array
     */
    public function securityResponse($reference_id, $process_data)
    {
        return [
            'success' => false,
            'payment_id' => $reference_id,
            'message' => __('fawaterak::messages.Security_checks_are_not_passed_by_the_system'),
            'process_data' => $process_data,
        ];
    }

    /**
     * The payment is awaiting the customer (Fawry, Aman, Masary, ...).
     *
     * @param  mixed  $reference_id
     * @param  array  $extra
     * @return array
     */
    public function pendingResponse($reference_id, array $extra = [])
    {
        return array_merge([
            'success' => false,
            'pending' => true,
            'payment_id' => $reference_id,
            'message' => __('fawaterak::messages.PAYMENT_PENDING'),
        ], $extra);
    }

    /**
     * Wrap a thrown exception in the array shape used across the package.
     *
     * @param  \Throwable  $e
     * @return array
     */
    public function exceptionResponse(\Throwable $e)
    {
        return [
            'status' => 'failed',
            'message' => __('fawaterak::messages.PAYMENT_FAILED_WITH_CODE', ['CODE' => $e->getCode()]),
            'error' => $e->getMessage(),
        ];
    }

    /**
     * Normalise an unsuccessful API response into the 1.x failure shape.
     *
     * @param  array  $response  A normalised FawaterakClient response.
     * @param  string|null  $message
     * @return array
     */
    public function apiErrorResponse(array $response, $message = null)
    {
        $body = $response['body'] ?? [];

        $result = [
            'status' => $body['status'] ?? 'failed',
            'message' => $message ?: $this->extractApiMessage($body),
            'response' => $response['raw'] ?? '',
            'http_status' => $response['status'] ?? 0,
        ];

        $errors = $this->extractApiErrors($body);

        if (! empty($errors)) {
            $result['errors'] = $errors;
        }

        return $result;
    }

    /**
     * Pull a readable message out of an error body.
     *
     * @param  array  $body
     * @return string
     */
    protected function extractApiMessage(array $body)
    {
        $message = $body['message'] ?? null;

        if (is_string($message) && trim($message) !== '') {
            return $message;
        }

        if (is_array($message)) {
            $first = reset($message);

            if (is_array($first)) {
                $first = reset($first);
            }

            if (is_string($first) && trim($first) !== '') {
                return $first;
            }
        }

        return __('fawaterak::messages.Process_Has_Been_Blocked_From_System');
    }

    /**
     * Field level validation errors, if any.
     *
     * @param  array  $body
     * @return array
     */
    protected function extractApiErrors(array $body)
    {
        if (isset($body['errors']) && is_array($body['errors'])) {
            return $body['errors'];
        }

        if (isset($body['message']) && is_array($body['message'])) {
            return $body['message'];
        }

        return [];
    }
}
