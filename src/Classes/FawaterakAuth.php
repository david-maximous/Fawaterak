<?php

namespace DavidMaximous\Fawaterak\Classes;

use DavidMaximous\Fawaterak\Exceptions\FawaterakAuthenticationException;
use Illuminate\Support\Facades\Cache;

/**
 * OAuth 2.0 client credentials token manager.
 *
 * The access token is fetched on first use and cached until shortly before it
 * expires, so the developer never has to think about tokens.
 */
class FawaterakAuth
{
    /**
     * Seconds subtracted from expires_in, so a token is never used at the very
     * edge of its lifetime.
     */
    const EXPIRY_MARGIN = 60;

    /**
     * @var FawaterakClient
     */
    protected $client;

    public function __construct(?FawaterakClient $client = null)
    {
        $this->client = $client ?: new FawaterakClient($this);
    }

    /**
     * Are OAuth credentials configured?
     *
     * @return bool
     */
    public function isConfigured()
    {
        return ! empty($this->clientId()) && ! empty($this->clientSecret());
    }

    /**
     * A usable access token.
     *
     * Reads the cache first, then tries the stored refresh token, and finally
     * performs a full client credentials exchange.
     *
     * @param  bool  $force  Skip the cache and issue a brand new token.
     * @return string
     *
     * @throws FawaterakAuthenticationException
     */
    public function token($force = false)
    {
        if (! $this->isConfigured()) {
            throw FawaterakAuthenticationException::missingCredentials();
        }

        if (! $force) {
            $cached = $this->cached();

            if ($cached && ! empty($cached['access_token']) && $this->stillValid($cached)) {
                return $cached['access_token'];
            }

            // The access token is gone but a refresh token may still work.
            if ($cached && ! empty($cached['refresh_token'])) {
                try {
                    $refreshed = $this->refresh($cached['refresh_token']);

                    return $refreshed['access_token'];
                } catch (FawaterakAuthenticationException $e) {
                    // Refresh token revoked or rotated away, fall back below.
                }
            }
        }

        $issued = $this->issue();

        return $issued['access_token'];
    }

    /**
     * Exchange the client id and secret for a new access token.
     *
     * @return array
     *
     * @throws FawaterakAuthenticationException
     */
    public function issue()
    {
        if (! $this->isConfigured()) {
            throw FawaterakAuthenticationException::missingCredentials();
        }

        $response = $this->client->raw('post', 'oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
        ]);

        return $this->store($response);
    }

    /**
     * Exchange a refresh token for a new access token.
     *
     * @param  string|null  $refreshToken
     * @return array
     *
     * @throws FawaterakAuthenticationException
     */
    public function refresh($refreshToken = null)
    {
        if (! $this->isConfigured()) {
            throw FawaterakAuthenticationException::missingCredentials();
        }

        $refreshToken = $refreshToken ?: ($this->cached()['refresh_token'] ?? null);

        if (empty($refreshToken)) {
            throw new FawaterakAuthenticationException(
                __('fawaterak::messages.AUTH_FAILED'),
                'invalid_request',
                'No refresh token is stored.'
            );
        }

        $response = $this->client->raw('post', 'oauth/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
        ]);

        return $this->store($response);
    }

    /**
     * Forget the cached tokens.
     *
     * @return void
     */
    public function forget()
    {
        $this->cache()->forget($this->cacheKey());
    }

    /**
     * The currently cached token payload, if any.
     *
     * @return array|null
     */
    public function cached()
    {
        $cached = $this->cache()->get($this->cacheKey());

        return is_array($cached) ? $cached : null;
    }

    /**
     * Persist a token response and return it.
     *
     * @param  array  $response
     * @return array
     *
     * @throws FawaterakAuthenticationException
     */
    protected function store(array $response)
    {
        $body = $response['body'];

        if (! $response['ok'] || empty($body['access_token'])) {
            throw FawaterakAuthenticationException::fromResponse($body, $response['status']);
        }

        $expiresIn = (int) ($body['expires_in'] ?? 3600);
        $ttl = max(60, $expiresIn - self::EXPIRY_MARGIN);

        $payload = [
            'access_token' => $body['access_token'],
            'refresh_token' => $body['refresh_token'] ?? ($this->cached()['refresh_token'] ?? null),
            'token_type' => $body['token_type'] ?? 'Bearer',
            'expires_in' => $expiresIn,
            'expires_at' => time() + $ttl,
        ];

        $this->cache()->put($this->cacheKey(), $payload, $ttl);

        return $payload;
    }

    /**
     * @param  array  $cached
     * @return bool
     */
    protected function stillValid(array $cached)
    {
        return isset($cached['expires_at']) && $cached['expires_at'] > time();
    }

    /**
     * The cache repository to use.
     *
     * @return \Illuminate\Contracts\Cache\Repository
     */
    protected function cache()
    {
        $store = config('fawaterak.FAWATERAK_CACHE_STORE');

        return $store ? Cache::store($store) : Cache::store();
    }

    /**
     * Cache key scoped to the environment and the OAuth client, so staging and
     * production tokens never collide.
     *
     * @return string
     */
    public function cacheKey()
    {
        $prefix = config('fawaterak.FAWATERAK_CACHE_PREFIX') ?: 'fawaterak_token_';

        return $prefix . sha1($this->client->baseUrl() . '|' . $this->clientId());
    }

    /**
     * @return string|null
     */
    protected function clientId()
    {
        return config('fawaterak.FAWATERAK_CLIENT_ID');
    }

    /**
     * @return string|null
     */
    protected function clientSecret()
    {
        return config('fawaterak.FAWATERAK_CLIENT_SECRET');
    }
}
