<?php

namespace DavidMaximous\Fawaterak\Classes;

use DavidMaximous\Fawaterak\Traits\FetchesPaymentMethods;
use DavidMaximous\Fawaterak\Traits\HandlesFawaterakResponses;
use DavidMaximous\Fawaterak\Traits\SetRequiredFields;
use DavidMaximous\Fawaterak\Traits\SetVariables;

class BaseController
{
    use SetVariables, SetRequiredFields, HandlesFawaterakResponses, FetchesPaymentMethods;

    /**
     * Shared HTTP client.
     *
     * @var FawaterakClient|null
     */
    protected $client;

    /**
     * Base URL, kept public for backwards compatibility.
     *
     * @var string
     */
    public $fawaterak_url;

    /**
     * Vendor API key, kept public for backwards compatibility.
     *
     * @var string
     */
    public $fawaterak_api_key;

    /**
     * Legacy redirect route name, kept public for backwards compatibility.
     *
     * @var string
     */
    public $fawaterak_redirect_url;

    public function __construct(?FawaterakClient $client = null)
    {
        $this->client = $client;

        $this->fawaterak_url = config('fawaterak.FAWATERAK_URL');
        $this->fawaterak_api_key = config('fawaterak.FAWATERAK_API_KEY');
        $this->fawaterak_redirect_url = config('fawaterak.FAWATERAK_REDIRECT_URL');
    }

    /**
     * The HTTP client, resolved lazily.
     *
     * @return FawaterakClient
     */
    public function client()
    {
        if (! $this->client) {
            $this->client = new FawaterakClient();
        }

        return $this->client;
    }

    /**
     * Swap the HTTP client (useful in tests).
     *
     * @param  FawaterakClient  $client
     * @return $this
     */
    public function setClient(FawaterakClient $client)
    {
        $this->client = $client;

        return $this;
    }

    /**
     * The OAuth token manager.
     *
     * @return FawaterakAuth
     */
    public function auth()
    {
        return $this->client()->auth();
    }

    /**
     * The language to send to Fawaterak.
     *
     * @param  string|null  $lang
     * @return string
     */
    protected function resolveLang($lang = null)
    {
        if ($lang) {
            return $lang;
        }

        $locale = null;

        if (function_exists('app') && app()->bound('translator')) {
            $locale = app()->getLocale();
        }

        if (in_array($locale, ['en', 'ar'], true)) {
            return $locale;
        }

        return config('fawaterak.FAWATERAK_LANG') ?: 'en';
    }
}
