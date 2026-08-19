<?php

namespace DavidMaximous\Fawaterak\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \DavidMaximous\Fawaterak\Classes\FawaterakPayment setAmount($value)
 * @method static \DavidMaximous\Fawaterak\Classes\FawaterakPayment setFirstName($value)
 * @method static \DavidMaximous\Fawaterak\Classes\FawaterakPayment setLastName($value)
 * @method static \DavidMaximous\Fawaterak\Classes\FawaterakPayment setUserEmail($value)
 * @method static \DavidMaximous\Fawaterak\Classes\FawaterakPayment setUserPhone($value)
 * @method static \DavidMaximous\Fawaterak\Classes\FawaterakPayment setMethod($value)
 * @method static \DavidMaximous\Fawaterak\Classes\FawaterakPayment setItemName($value)
 * @method static \DavidMaximous\Fawaterak\Classes\FawaterakPayment setQuantity($value)
 * @method static \DavidMaximous\Fawaterak\Classes\FawaterakPayment setCurrency($value)
 * @method static \DavidMaximous\Fawaterak\Classes\FawaterakPayment setPayload($value)
 * @method static \DavidMaximous\Fawaterak\Classes\FawaterakPayment setLanguage($value)
 * @method static array pay($amount = null, $first_name = null, $last_name = null, $user_email = null, $user_phone = null, $method = null, $item_name = null, $quantity = null, $currency = null, $language = null, ?array $payload = [])
 * @method static array createTransaction()
 * @method static array listPaymentMethods(bool $fresh = false)
 * @method static array paymentMethodsList(?string $lang = null)
 *
 * @see \DavidMaximous\Fawaterak\Classes\FawaterakPayment
 */
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
