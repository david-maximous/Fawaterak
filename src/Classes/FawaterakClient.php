<?php

namespace DavidMaximous\Fawaterak\Classes;

use DavidMaximous\Fawaterak\Exceptions\FawaterakAuthenticationException;
use Illuminate\Support\Facades\Http;

/**
 * Thin HTTP layer shared by every Fawaterak service class.
 *
 * Every call is normalised into:
 *   ['ok' => bool, 'status' => int, 'body' => array, 'raw' => string]
 *
 * so the service classes keep returning plain arrays, exactly like 1.x did.
 */
class FawaterakClient
{
    /**
     * Lazily resolved OAuth token manager.
     *
     * @var FawaterakAuth|null
     */
    protected $auth;

    public function __construct(?FawaterakAuth $auth = null)
    {
        $this->auth = $auth;
    }

    /**
     * Base URL without a trailing slash.
     *
     * @return string
     */
    public function baseUrl()
    {
        return rtrim((string) config('fawaterak.FAWATERAK_URL'), '/');
    }

    /**
     * Build a full URL for a path.
     *
     * @param  string  $path
     * @return string
     */
    public function url($path)
    {
        return $this->baseUrl() . '/' . ltrim($path, '/');
    }

    /**
     * The OAuth token manager.
     *
     * @return FawaterakAuth
     */
    public function auth()
    {
        if (! $this->auth) {
            $this->auth = new FawaterakAuth($this);
        }

        return $this->auth;
    }

    /**
     * Request timeout in seconds.
     *
     * @return int
     */
    protected function timeout()
    {
        return (int) (config('fawaterak.FAWATERAK_TIMEOUT') ?: 30);
    }

    /**
     * Unauthenticated request. Used by the OAuth token endpoint itself.
     *
     * @param  string  $method
     * @param  string  $path
     * @param  array  $data
     * @param  array  $headers
     * @return array
     */
    public function raw($method, $path, array $data = [], array $headers = [])
    {
        $request = Http::withHeaders(array_merge([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ], $headers))->timeout($this->timeout());

        $method = strtolower($method);

        $response = $request->{$method}($this->url($path), $data);

        return $this->normalize($response);
    }

    /**
     * OAuth protected request (every /api/v3/* endpoint).
     *
     * On a 401 the cached token is discarded and the call is retried once with
     * a freshly issued token before giving up.
     *
     * @param  string  $method
     * @param  string  $path
     * @param  array  $data
     * @return array
     *
     * @throws FawaterakAuthenticationException
     */
    public function oauth($method, $path, array $data = [])
    {
        $response = $this->raw($method, $path, $data, [
            'Authorization' => 'Bearer ' . $this->auth()->token(),
        ]);

        if ($response['status'] !== 401) {
            return $response;
        }

        // The token was rejected. Drop it, issue a new one and try once more.
        $this->auth()->forget();

        $response = $this->raw($method, $path, $data, [
            'Authorization' => 'Bearer ' . $this->auth()->token(true),
        ]);

        if ($response['status'] === 401) {
            throw FawaterakAuthenticationException::fromResponse($response['body'], 401);
        }

        return $response;
    }

    /**
     * Vendor API key protected request (the legacy v2 tokenization endpoints).
     *
     * @param  string  $method
     * @param  string  $path
     * @param  array  $data
     * @return array
     */
    public function vendor($method, $path, array $data = [])
    {
        return $this->raw($method, $path, $data, [
            'Authorization' => 'Bearer ' . (string) config('fawaterak.FAWATERAK_API_KEY'),
        ]);
    }

    /**
     * Normalise an HTTP response.
     *
     * @param  \Illuminate\Http\Client\Response  $response
     * @return array
     */
    protected function normalize($response)
    {
        $body = $response->json();

        return [
            'ok' => $response->successful(),
            'status' => $response->status(),
            'body' => is_array($body) ? $body : [],
            'raw' => $response->body(),
        ];
    }

    /**
     * Whether a normalised response reports success.
     *
     * Fawaterak answers with HTTP 200 and status => "error" in some branches,
     * so both signals are checked.
     *
     * @param  array  $response
     * @return bool
     */
    public function succeeded(array $response)
    {
        if (! ($response['ok'] ?? false)) {
            return false;
        }

        $status = $response['body']['status'] ?? 'success';

        return $status === 'success' || $status === true;
    }
}
