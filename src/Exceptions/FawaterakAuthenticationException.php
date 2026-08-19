<?php

namespace DavidMaximous\Fawaterak\Exceptions;

class FawaterakAuthenticationException extends \Exception
{
    /**
     * OAuth error code returned by Fawaterak (e.g. invalid_grant).
     *
     * @var string|null
     */
    public $error;

    /**
     * Human readable description returned by Fawaterak.
     *
     * @var string|null
     */
    public $errorDescription;

    public function __construct($message = null, $error = null, $errorDescription = null, $code = 0)
    {
        $this->error = $error;
        $this->errorDescription = $errorDescription;

        parent::__construct($message ?: 'Fawaterak authentication failed', $code);
    }

    /**
     * Build the exception from an /oauth/token error body.
     *
     * @param  array  $body
     * @param  int  $status
     * @return static
     */
    public static function fromResponse(array $body, $status = 401)
    {
        $description = $body['error_description'] ?? $body['message'] ?? null;

        $message = __('fawaterak::messages.AUTH_FAILED');

        if ($description) {
            $message .= ': ' . $description;
        }

        return new static($message, $body['error'] ?? null, $description, $status);
    }

    /**
     * Missing client id / client secret in the configuration.
     *
     * @return static
     */
    public static function missingCredentials()
    {
        return new static(__('fawaterak::messages.AUTH_MISSING_CREDENTIALS'), 'missing_credentials');
    }
}
