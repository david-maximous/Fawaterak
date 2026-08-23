<?php

namespace DavidMaximous\Fawaterak\Exceptions;

class FawaterakRequestException extends \Exception
{
    /**
     * HTTP status code returned by Fawaterak.
     *
     * @var int
     */
    public $httpStatus;

    /**
     * Decoded response body.
     *
     * @var array
     */
    public $body;

    public function __construct($message = null, $httpStatus = 0, array $body = [])
    {
        $this->httpStatus = $httpStatus;
        $this->body = $body;

        parent::__construct($message ?: 'Fawaterak request failed', $httpStatus);
    }

    /**
     * Field level validation errors, when Fawaterak returned a 422 payload.
     *
     * The API may return "message" either as a plain string or as a map of
     * field => [messages], so both shapes are normalised here.
     *
     * @return array
     */
    public function errors()
    {
        if (isset($this->body['errors']) && is_array($this->body['errors'])) {
            return $this->body['errors'];
        }

        if (isset($this->body['message']) && is_array($this->body['message'])) {
            return $this->body['message'];
        }

        return [];
    }
}
