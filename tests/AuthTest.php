<?php

namespace DavidMaximous\Fawaterak\Tests;

use DavidMaximous\Fawaterak\Classes\FawaterakAuth;
use DavidMaximous\Fawaterak\Exceptions\FawaterakAuthenticationException;
use Illuminate\Support\Facades\Http;

class AuthTest extends TestCase
{
    public function test_it_issues_an_access_token_with_client_credentials()
    {
        Http::fake(['*/oauth/token' => $this->tokenResponse()]);

        $token = (new FawaterakAuth())->token();

        $this->assertSame(self::ACCESS_TOKEN, $token);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/oauth/token')
                && $request['grant_type'] === 'client_credentials'
                && $request['client_id'] === 'client-id'
                && $request['client_secret'] === 'client-secret';
        });
    }

    public function test_it_caches_the_token_and_does_not_call_the_api_twice()
    {
        Http::fake(['*/oauth/token' => $this->tokenResponse()]);

        $auth = new FawaterakAuth();
        $auth->token();
        $auth->token();
        $auth->token();

        Http::assertSentCount(1);
    }

    public function test_an_expired_token_is_reissued()
    {
        // A token that is already past its safety margin.
        Http::fake(['*/oauth/token' => $this->tokenResponse(null, 1)]);

        $auth = new FawaterakAuth();
        $auth->token();

        $cached = $auth->cached();
        $this->assertNotNull($cached);

        // Force expiry.
        $this->app['cache']->store()->put($auth->cacheKey(), array_merge($cached, [
            'expires_at' => time() - 10,
            'refresh_token' => null,
        ]), 600);

        $auth->token();

        Http::assertSentCount(2);
    }

    public function test_it_uses_the_refresh_token_when_the_access_token_is_gone()
    {
        Http::fake(['*/oauth/token' => $this->tokenResponse()]);

        $auth = new FawaterakAuth();
        $auth->token();

        $cached = $auth->cached();
        $this->app['cache']->store()->put($auth->cacheKey(), array_merge($cached, [
            'expires_at' => time() - 10,
        ]), 600);

        $auth->token();

        $refreshRequest = null;

        foreach (Http::recorded() as $pair) {
            if (($pair[0]['grant_type'] ?? null) === 'refresh_token') {
                $refreshRequest = $pair[0];
            }
        }

        $this->assertNotNull($refreshRequest, 'Expected a refresh_token grant to be sent.');
        $this->assertSame('test_refresh_token', $refreshRequest['refresh_token']);
    }

    public function test_it_falls_back_to_client_credentials_when_the_refresh_token_is_rejected()
    {
        // 1: the initial token, 2: a rejected refresh, 3: a fresh exchange.
        Http::fake([
            '*/oauth/token' => Http::sequence()
                ->push(['token_type' => 'Bearer', 'expires_in' => 3600, 'access_token' => self::ACCESS_TOKEN, 'refresh_token' => 'test_refresh_token'])
                ->push(['error' => 'invalid_grant', 'error_description' => 'The refresh token is invalid.'], 401)
                ->push(['token_type' => 'Bearer', 'expires_in' => 3600, 'access_token' => 'second_token']),
        ]);

        $auth = new FawaterakAuth();
        $auth->token();

        $cached = $auth->cached();
        $this->app['cache']->store()->put($auth->cacheKey(), array_merge($cached, [
            'expires_at' => time() - 10,
        ]), 600);

        $this->assertSame('second_token', $auth->token());
    }

    public function test_invalid_credentials_throw()
    {
        Http::fake([
            '*/oauth/token' => Http::response([
                'error' => 'invalid_client',
                'error_description' => 'Client authentication failed',
            ], 401),
        ]);

        $this->expectException(FawaterakAuthenticationException::class);

        (new FawaterakAuth())->token();
    }

    public function test_missing_credentials_throw()
    {
        config()->set('fawaterak.FAWATERAK_CLIENT_ID', null);
        config()->set('fawaterak.FAWATERAK_CLIENT_SECRET', null);

        $this->expectException(FawaterakAuthenticationException::class);

        (new FawaterakAuth())->token();
    }

    public function test_forget_clears_the_cached_token()
    {
        Http::fake(['*/oauth/token' => $this->tokenResponse()]);

        $auth = new FawaterakAuth();
        $auth->token();

        $this->assertNotNull($auth->cached());

        $auth->forget();

        $this->assertNull($auth->cached());
    }

    public function test_the_cache_key_is_scoped_to_the_environment()
    {
        $auth = new FawaterakAuth();
        $staging = $auth->cacheKey();

        config()->set('fawaterak.FAWATERAK_URL', 'https://app.fawaterk.com/');

        $this->assertNotSame($staging, (new FawaterakAuth())->cacheKey());
    }
}
