<?php

namespace DavidMaximous\Fawaterak\Traits;

use DavidMaximous\Fawaterak\Exceptions\MissingPaymentInfoException;
use Illuminate\Support\Facades\Route;

trait SetVariables
{
    /* -----------------------------------------------------------------
     |  1.x properties (unchanged)
     | -----------------------------------------------------------------
     */
    public $amount = null;
    public $first_name = null;
    public $last_name = null;
    public $user_email = null;
    public $user_phone = null;
    public $method = null;
    public $item_name = null;
    public $quantity = null;
    public $currency = null;
    public $payload = null;
    public $language = null;

    /* -----------------------------------------------------------------
     |  API v3 properties
     | -----------------------------------------------------------------
     */
    public $address = null;
    public $customer_number = null;
    public $customer_unique_id = null;
    public $save_customer = null;
    public $cart_items = null;
    public $cart_total = null;
    public $success_url = null;
    public $fail_url = null;
    public $pending_url = null;
    public $back_url = null;
    public $webhook_url = null;
    public $send_email = null;
    public $send_sms = null;
    public $due_date = null;
    public $tr_number = null;
    public $redirect_option = null;
    public $auth_and_capture = null;
    public $tax_data = null;
    public $discount_data = null;
    public $list_style = null;
    public $mobile_wallet_number = null;

    /**
     * Friendly aliases kept from 1.x. getTrPaymentmethods() is the
     * authoritative source of payment method ids.
     *
     * @var array
     */
    protected $method_aliases = [
        'card' => '2',
        'visa' => '2',
        'mastercard' => '2',
        'fawry' => '3',
        'mwallet' => '4',
        'meeza' => '4',
        'aman' => '12',
        'basata' => '14',
        'masary' => '14',
        'applepay' => '42',
    ];

    /* -----------------------------------------------------------------
     |  Setters
     | -----------------------------------------------------------------
     */

    /**
     * Sets amount
     *
     * @param  float  $value
     * @return $this
     */
    public function setAmount($value)
    {
        $this->amount = $value;

        return $this;
    }

    /**
     * Sets first name
     *
     * @param  string  $value
     * @return $this
     */
    public function setFirstName($value)
    {
        $this->first_name = $value;

        return $this;
    }

    /**
     * Sets last name
     *
     * @param  string  $value
     * @return $this
     */
    public function setLastName($value)
    {
        $this->last_name = $value;

        return $this;
    }

    /**
     * Sets user email
     *
     * @param  string  $value
     * @return $this
     */
    public function setUserEmail($value)
    {
        $this->user_email = $value;

        return $this;
    }

    /**
     * Sets user phone
     *
     * @param  string  $value
     * @return $this
     */
    public function setUserPhone($value)
    {
        $this->user_phone = $value;

        return $this;
    }

    /**
     * Sets the payment method.
     *
     * Accepts a friendly alias ("card", "fawry", "mwallet", "aman", "basata",
     * "applepay") or a numeric payment_method_id from listPaymentMethods().
     *
     * @param  string|int  $value
     * @return $this
     *
     * @throws MissingPaymentInfoException
     */
    public function setMethod($value)
    {
        if (is_null($value) || $value === '') {
            $this->method = null;

            return $this;
        }

        if (is_numeric($value)) {
            $this->method = (string) $value;

            return $this;
        }

        $key = strtolower(trim((string) $value));

        if (! isset($this->method_aliases[$key])) {
            throw new MissingPaymentInfoException('method');
        }

        $this->method = $this->method_aliases[$key];

        return $this;
    }

    /**
     * Alias of setMethod() using the API field name.
     *
     * @param  int|string  $value
     * @return $this
     */
    public function setPaymentMethodId($value)
    {
        return $this->setMethod($value);
    }

    /**
     * Sets item name
     *
     * @param  string  $value
     * @return $this
     */
    public function setItemName($value)
    {
        $this->item_name = $value;

        return $this;
    }

    /**
     * Sets quantity
     *
     * @param  int  $value
     * @return $this
     */
    public function setQuantity($value)
    {
        $this->quantity = $value;

        return $this;
    }

    /**
     * Sets currency
     *
     * @param  string  $value
     * @return $this
     */
    public function setCurrency($value)
    {
        $this->currency = $value;

        return $this;
    }

    /**
     * Sets payload
     *
     * @param  array  $value
     * @return $this
     */
    public function setPayload($value)
    {
        $this->payload = $value;

        return $this;
    }

    /**
     * Sets language
     *
     * @param  string  $value
     * @return $this
     */
    public function setLanguage($value)
    {
        $this->language = $value;

        return $this;
    }

    /**
     * Sets the customer address.
     *
     * @param  string  $value
     * @return $this
     */
    public function setAddress($value)
    {
        $this->address = $value;

        return $this;
    }

    /**
     * Sets the merchant customer number.
     *
     * @param  string  $value
     * @return $this
     */
    public function setCustomerNumber($value)
    {
        $this->customer_number = $value;

        return $this;
    }

    /**
     * Sets the customer id used in your own system.
     * Required when save_customer is enabled.
     *
     * @param  string  $value
     * @return $this
     */
    public function setCustomerUniqueId($value)
    {
        $this->customer_unique_id = $value;

        return $this;
    }

    /**
     * Save the customer to your Fawaterak customers list.
     *
     * @param  bool  $value
     * @return $this
     */
    public function setSaveCustomer($value = true)
    {
        $this->save_customer = (bool) $value;

        return $this;
    }

    /**
     * Sets the full cart, replacing the single item shortcut.
     *
     * @param  array  $value  [['name' => ..., 'price' => ..., 'quantity' => ...], ...]
     * @return $this
     */
    public function setCartItems(array $value)
    {
        $this->cart_items = array_values($value);

        return $this;
    }

    /**
     * Appends one item to the cart.
     *
     * @param  string  $name
     * @param  float  $price
     * @param  int  $quantity
     * @return $this
     */
    public function addCartItem($name, $price, $quantity = 1)
    {
        $this->cart_items = $this->cart_items ?: [];

        $this->cart_items[] = [
            'name' => $name,
            'price' => $price,
            'quantity' => $quantity,
        ];

        return $this;
    }

    /**
     * Overrides the calculated cart total.
     *
     * @param  float  $value
     * @return $this
     */
    public function setCartTotal($value)
    {
        $this->cart_total = $value;

        return $this;
    }

    /**
     * Redirect URL after a successful payment. Route name or absolute URL.
     *
     * @param  string  $value
     * @return $this
     */
    public function setSuccessUrl($value)
    {
        $this->success_url = $value;

        return $this;
    }

    /**
     * Redirect URL after a failed payment. Route name or absolute URL.
     *
     * @param  string  $value
     * @return $this
     */
    public function setFailUrl($value)
    {
        $this->fail_url = $value;

        return $this;
    }

    /**
     * Redirect URL for pending payments. Route name or absolute URL.
     *
     * @param  string  $value
     * @return $this
     */
    public function setPendingUrl($value)
    {
        $this->pending_url = $value;

        return $this;
    }

    /**
     * Back / cancel URL on the hosted checkout. Route name or absolute URL.
     *
     * @param  string  $value
     * @return $this
     */
    public function setBackUrl($value)
    {
        $this->back_url = $value;

        return $this;
    }

    /**
     * Server to server webhook URL for this transaction. Overrides the
     * dashboard default. Add "_json" to the path for a JSON body.
     *
     * @param  string  $value
     * @return $this
     */
    public function setWebhookUrl($value)
    {
        $this->webhook_url = $value;

        return $this;
    }

    /**
     * Email the checkout link to the customer.
     *
     * @param  bool  $value
     * @return $this
     */
    public function setSendEmail($value = true)
    {
        $this->send_email = (bool) $value;

        return $this;
    }

    /**
     * SMS the checkout link to the customer.
     *
     * @param  bool  $value
     * @return $this
     */
    public function setSendSMS($value = true)
    {
        $this->send_sms = (bool) $value;

        return $this;
    }

    /**
     * Sets the due date. Defaults to +2 days on the Fawaterak side.
     *
     * @param  string|\DateTimeInterface  $value
     * @return $this
     */
    public function setDueDate($value)
    {
        if ($value instanceof \DateTimeInterface) {
            $value = $value->format('Y-m-d H:i:s');
        }

        $this->due_date = $value;

        return $this;
    }

    /**
     * Sets your own transaction number.
     *
     * @param  string  $value
     * @return $this
     */
    public function setTrNumber($value)
    {
        $this->tr_number = $value;

        return $this;
    }

    /**
     * Force hosted checkout even when direct dispatch would apply.
     *
     * @param  bool  $value
     * @return $this
     */
    public function setRedirectOption($value = true)
    {
        $this->redirect_option = (bool) $value;

        return $this;
    }

    /**
     * Enable auth and capture on supported card flows.
     *
     * @param  int|bool  $value  0 or 1
     * @return $this
     */
    public function setAuthAndCapture($value = 1)
    {
        $this->auth_and_capture = (int) ((bool) $value);

        return $this;
    }

    /**
     * Sets the tax applied at creation time.
     *
     * @param  string  $title
     * @param  float  $value  Percentage
     * @return $this
     */
    public function setTax($title, $value)
    {
        $this->tax_data = ['title' => $title, 'value' => $value];

        return $this;
    }

    /**
     * Sets the discount applied at creation time.
     *
     * @param  string  $type  "pcg" (percentage) or "literal" (fixed amount)
     * @param  float  $value
     * @return $this
     */
    public function setDiscount($type, $value)
    {
        $this->discount_data = ['type' => $type, 'value' => $value];

        return $this;
    }

    /**
     * Payment method list layout on hosted checkout.
     *
     * @param  string  $value  "h" horizontal or "v" vertical
     * @return $this
     */
    public function setListStyle($value)
    {
        $this->list_style = $value === 'v' ? 'v' : 'h';

        return $this;
    }

    /**
     * Mobile wallet number, required by some direct dispatch methods.
     *
     * @param  string  $value
     * @return $this
     */
    public function setMobileWalletNumber($value)
    {
        $this->mobile_wallet_number = $value;

        return $this;
    }

    /* -----------------------------------------------------------------
     |  Getters
     | -----------------------------------------------------------------
     */

    public function getAmount()
    {
        return $this->amount;
    }

    public function getFirstName()
    {
        return $this->first_name;
    }

    public function getLastName()
    {
        return $this->last_name;
    }

    public function getUserEmail()
    {
        return $this->user_email;
    }

    public function getUserPhone()
    {
        return $this->user_phone;
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function getPaymentMethodId()
    {
        return $this->method;
    }

    public function getItemName()
    {
        return $this->item_name;
    }

    public function getQuantity()
    {
        return $this->quantity;
    }

    public function getCurrency()
    {
        return $this->currency;
    }

    public function getPayload()
    {
        return $this->payload;
    }

    public function getLanguage()
    {
        return $this->language;
    }

    public function getAddress()
    {
        return $this->address;
    }

    public function getCustomerNumber()
    {
        return $this->customer_number;
    }

    public function getCustomerUniqueId()
    {
        return $this->customer_unique_id;
    }

    public function getSaveCustomer()
    {
        return $this->save_customer;
    }

    public function getCartItems()
    {
        return $this->cart_items;
    }

    public function getCartTotal()
    {
        return $this->cart_total;
    }

    public function getSuccessUrl()
    {
        return $this->success_url;
    }

    public function getFailUrl()
    {
        return $this->fail_url;
    }

    public function getPendingUrl()
    {
        return $this->pending_url;
    }

    public function getBackUrl()
    {
        return $this->back_url;
    }

    public function getWebhookUrl()
    {
        return $this->webhook_url;
    }

    public function getSendEmail()
    {
        return $this->send_email;
    }

    public function getSendSMS()
    {
        return $this->send_sms;
    }

    public function getDueDate()
    {
        return $this->due_date;
    }

    public function getTrNumber()
    {
        return $this->tr_number;
    }

    public function getRedirectOption()
    {
        return $this->redirect_option;
    }

    public function getAuthAndCapture()
    {
        return $this->auth_and_capture;
    }

    public function getTax()
    {
        return $this->tax_data;
    }

    public function getDiscount()
    {
        return $this->discount_data;
    }

    public function getListStyle()
    {
        return $this->list_style;
    }

    public function getMobileWalletNumber()
    {
        return $this->mobile_wallet_number;
    }

    /* -----------------------------------------------------------------
     |  Helpers
     | -----------------------------------------------------------------
     */

    /**
     * set passed vaiables to pay function to be global
     *
     * @param  $amount
     * @param  null  $first_name
     * @param  null  $last_name
     * @param  null  $user_email
     * @param  null  $user_phone
     * @param  null  $method
     * @param  null  $item_name
     * @param  null  $quantity
     * @param  null  $currency
     * @param  null  $language
     * @param  array|null  $payload
     * @return void
     */
    public function setPassedVariablesToGlobal($amount, $first_name, $last_name, $user_email, $user_phone, $method, $item_name, $quantity, $currency, $language, ?array $payload)
    {
        if ($amount != null) $this->setAmount($amount);
        if ($first_name != null) $this->setFirstName($first_name);
        if ($last_name != null) $this->setLastName($last_name);
        if ($user_email != null) $this->setUserEmail($user_email);
        if ($user_phone != null) $this->setUserPhone($user_phone);
        if ($method != null) $this->setMethod($method);
        if ($item_name != null) $this->setItemName($item_name);
        if ($quantity != null) $this->setQuantity($quantity);
        if ($currency != null) $this->setCurrency($currency);
        if ($language != null) $this->setLanguage($language);
        if ($payload != null) $this->setPayload($payload);
    }

    /**
     * Reset every value, so one instance can create several transactions.
     *
     * @return $this
     */
    public function reset()
    {
        foreach ([
            'amount', 'first_name', 'last_name', 'user_email', 'user_phone', 'method',
            'item_name', 'quantity', 'currency', 'payload', 'language', 'address',
            'customer_number', 'customer_unique_id', 'save_customer', 'cart_items',
            'cart_total', 'success_url', 'fail_url', 'pending_url', 'back_url',
            'webhook_url', 'send_email', 'send_sms', 'due_date', 'tr_number',
            'redirect_option', 'auth_and_capture', 'tax_data', 'discount_data',
            'list_style', 'mobile_wallet_number',
        ] as $property) {
            $this->{$property} = null;
        }

        return $this;
    }

    /**
     * The cart items that will be sent, falling back to the single item
     * shortcut built from item name / amount / quantity.
     *
     * @return array
     */
    public function resolveCartItems()
    {
        if (! empty($this->cart_items)) {
            return array_values($this->cart_items);
        }

        return [[
            'name' => $this->item_name ?: 'default',
            'price' => $this->amount,
            'quantity' => (int) ($this->quantity ?: 1),
        ]];
    }

    /**
     * The cart total that will be sent.
     *
     * Priority: explicit setCartTotal() -> sum of the cart items -> the 1.x
     * formula (amount x quantity).
     *
     * @return float
     */
    public function resolveCartTotal()
    {
        if (! is_null($this->cart_total)) {
            return (float) $this->cart_total;
        }

        if (! empty($this->cart_items)) {
            $total = 0;

            foreach ($this->cart_items as $item) {
                $total += ((float) ($item['price'] ?? 0)) * ((int) ($item['quantity'] ?? 1));
            }

            return (float) $total;
        }

        return (float) $this->amount * (int) ($this->quantity ?: 1);
    }

    /**
     * Turn a route name or an absolute URL into a URL.
     *
     * Never throws: a misconfigured redirect must not break the payment.
     *
     * @param  string|null  $value
     * @return string|null
     */
    public function resolveUrl($value)
    {
        if (empty($value)) {
            return null;
        }

        if (preg_match('#^https?://#i', $value)) {
            return $value;
        }

        try {
            if (Route::has($value)) {
                return route($value);
            }
        } catch (\Throwable $e) {
            // Router unavailable, fall through and return the raw value.
        }

        return $value;
    }
}
