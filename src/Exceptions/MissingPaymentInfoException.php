<?php

namespace DavidMaximous\Fawaterak\Exceptions;

class MissingPaymentInfoException extends \Exception
{
    /**
     * The field that was missing.
     *
     * @var string
     */
    public $field;

    public function __construct($missing_payment_parameter)
    {
        $this->field = $missing_payment_parameter;

        parent::__construct($this->buildMessage($missing_payment_parameter));
    }

    /**
     * Use the translated message when the translator is available, and fall
     * back to the original 1.x sentence otherwise (the exception may be built
     * before the application container is booted).
     *
     * @param  string  $field
     * @return string
     */
    protected function buildMessage($field)
    {
        try {
            if (function_exists('app') && app()->bound('translator')) {
                $message = __('fawaterak::messages.MISSING_REQUIRED_FIELD', ['FIELD' => $field]);

                if (is_string($message) && $message !== 'fawaterak::messages.MISSING_REQUIRED_FIELD') {
                    return $message;
                }
            }
        } catch (\Throwable $e) {
            // fall through to the default message
        }

        return $field . ' is required to use Fawaterak';
    }
}
