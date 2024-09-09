<?php

namespace DavidMaximous\Fawaterak\Facades;

use Illuminate\Support\Facades\Facade;

class FawaterakPaymentsFacade extends Facade
{
    /**
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'fawaterak';
    }
}
