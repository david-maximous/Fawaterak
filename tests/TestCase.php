<?php

namespace DavidMaximous\Fawaterak\Tests;

use DavidMaximous\Fawaterak\FawaterakServiceProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    const VENDOR_KEY = 'test_vendor_key';

    const ACCESS_TOKEN = 'test_access_token';

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/payments/redirect/{status?}', function () {
            return 'ok';
        })->name('payment-redirect');

        // The name is applied after the route is added to the collection.
        $this->app['router']->getRoutes()->refreshNameLookups();
    }

    protected function getPackageProviders($app)
    {
        return [FawaterakServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('cache.default', 'array');

        $app['config']->set('fawaterak.FAWATERAK_URL', 'https://staging.fawaterk.com/');
        $app['config']->set('fawaterak.FAWATERAK_API_KEY', self::VENDOR_KEY);
        $app['config']->set('fawaterak.FAWATERAK_CLIENT_ID', 'client-id');
        $app['config']->set('fawaterak.FAWATERAK_CLIENT_SECRET', 'client-secret');
        $app['config']->set('fawaterak.FAWATERAK_REDIRECT_URL', 'payment-redirect');
        $app['config']->set('fawaterak.FAWATERAK_METHODS_CACHE_TTL', 0);
    }

    /**
     * A successful /oauth/token response.
     *
     * @param  string|null  $refreshToken
     * @param  int  $expiresIn
     * @return \Illuminate\Http\Client\Response|\GuzzleHttp\Promise\PromiseInterface
     */
    protected function tokenResponse($refreshToken = 'test_refresh_token', $expiresIn = 31536000)
    {
        return Http::response(array_filter([
            'token_type' => 'Bearer',
            'expires_in' => $expiresIn,
            'access_token' => self::ACCESS_TOKEN,
            'refresh_token' => $refreshToken,
        ]));
    }

    /**
     * Fake the token endpoint plus one API endpoint.
     *
     * @param  string  $pattern
     * @param  mixed  $response
     * @return void
     */
    protected function fakeApi($pattern, $response)
    {
        Http::fake([
            '*/oauth/token' => $this->tokenResponse(),
            $pattern => $response,
        ]);
    }

    /**
     * The signature Fawaterak would send for a string.
     *
     * @param  string  $stringToSign
     * @return string
     */
    protected function sign($stringToSign)
    {
        return hash_hmac('sha256', $stringToSign, self::VENDOR_KEY, false);
    }

    /**
     * Find the first faked request matching a path.
     *
     * @param  string  $needle
     * @return Request|null
     */
    protected function requestFor($needle)
    {
        foreach (Http::recorded() as $pair) {
            /** @var Request $request */
            $request = $pair[0];

            if (str_contains($request->url(), $needle)) {
                return $request;
            }
        }

        return null;
    }

    /**
     * The decoded body of the first request matching a path.
     *
     * @param  string  $needle
     * @return array
     */
    protected function bodyFor($needle)
    {
        $request = $this->requestFor($needle);

        return $request ? $request->data() : [];
    }
}
