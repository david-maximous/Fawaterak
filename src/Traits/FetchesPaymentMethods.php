<?php

namespace DavidMaximous\Fawaterak\Traits;

use Illuminate\Support\Facades\Cache;

/**
 * Reads the payment methods enabled for the vendor account.
 *
 * Shared by every service class so the list is fetched and cached once, and
 * so payment method names can be resolved wherever they are needed.
 */
trait FetchesPaymentMethods
{
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
        $key = $this->paymentMethodsCacheKey();

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

    /**
     * Cache key, scoped to the environment.
     *
     * @return string
     */
    protected function paymentMethodsCacheKey()
    {
        return 'fawaterak_payment_methods_' . sha1($this->client()->baseUrl());
    }
}
