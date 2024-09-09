<?php

namespace DavidMaximous\Fawaterak\Exceptions;

class MissingPaymentInfoException extends \Exception
{
    public function __construct($missing_payment_parameter)
    {
        parent::__construct($missing_payment_parameter . ' is required to use Fawaterak');
    }
}
