<?php

namespace DavidMaximous\Fawaterak\Traits;

trait SetTokenizationVariables
{
    public $customer_first_name = null;
    public $customer_last_name = null;
    public $customer_email = null;
    public $customer_phone = null;
    public $customer_token = null;
    public $card_cvv = null;
    public $card_token_unique_id = null;
    public $delete_token_from_bank = null;
    public $deduct_total_amount = null;
    public $allowed_card_types = null;
    public $frequency = null;
    public $token_webhook_url = null;

    /**
     * Sets the customer first name.
     *
     * @param  string  $value
     * @return $this
     */
    public function setCustomerFirstName($value)
    {
        $this->customer_first_name = $value;

        return $this;
    }

    /**
     * Sets the customer last name.
     *
     * @param  string  $value
     * @return $this
     */
    public function setCustomerLastName($value)
    {
        $this->customer_last_name = $value;

        return $this;
    }

    /**
     * Sets the customer email.
     *
     * @param  string  $value
     * @return $this
     */
    public function setCustomerEmail($value)
    {
        $this->customer_email = $value;

        return $this;
    }

    /**
     * Sets the customer phone.
     *
     * @param  string  $value
     * @return $this
     */
    public function setCustomerPhone($value)
    {
        $this->customer_phone = $value;

        return $this;
    }

    /**
     * Sets the saved card token received from the tokenization webhook.
     *
     * @param  string  $value
     * @return $this
     */
    public function setCustomerToken($value)
    {
        $this->customer_token = $value;

        return $this;
    }

    /**
     * Sets the card CVV. Only required when CVV validation is enabled
     * for your account.
     *
     * @param  string  $value
     * @return $this
     */
    public function setCardCvv($value)
    {
        $this->card_cvv = $value;

        return $this;
    }

    /**
     * Sets the card token unique id, used when deleting a token.
     *
     * @param  string  $value
     * @return $this
     */
    public function setCardTokenUniqueId($value)
    {
        $this->card_token_unique_id = $value;

        return $this;
    }

    /**
     * Also delete the token from the bank / provider.
     *
     * @param  string  $value  The provider token.
     * @return $this
     */
    public function setDeleteTokenFromBank($value)
    {
        $this->delete_token_from_bank = $value;

        return $this;
    }

    /**
     * Deduct the full order amount instead of authorising and voiding it.
     *
     * @param  bool  $value
     * @return $this
     */
    public function setDeductTotalAmount($value = true)
    {
        $this->deduct_total_amount = (bool) $value;

        return $this;
    }

    /**
     * Restrict the card types shown on the card screen.
     *
     * @param  array  $value  Any of visa, mastercard, meeza.
     * @return $this
     */
    public function setAllowedCardTypes(array $value)
    {
        $this->allowed_card_types = array_values($value);

        return $this;
    }

    /**
     * Recurring frequency stored with the invoice, e.g. "monthly".
     *
     * @param  string  $value
     * @return $this
     */
    public function setFrequency($value)
    {
        $this->frequency = $value;

        return $this;
    }

    /**
     * Webhook URL that receives the created card token.
     *
     * @param  string  $value
     * @return $this
     */
    public function setTokenWebhookUrl($value)
    {
        $this->token_webhook_url = $value;

        return $this;
    }

    /**
     * Shortcut for the order amount and currency.
     *
     * @param  float  $amount
     * @param  string  $currency
     * @return $this
     */
    public function setOrder($amount, $currency = 'EGP')
    {
        $this->amount = $amount;
        $this->currency = $currency;

        return $this;
    }

    /* -----------------------------------------------------------------
     |  Getters
     | -----------------------------------------------------------------
     */

    public function getCustomerFirstName()
    {
        return $this->customer_first_name;
    }

    public function getCustomerLastName()
    {
        return $this->customer_last_name;
    }

    public function getCustomerEmail()
    {
        return $this->customer_email;
    }

    public function getCustomerPhone()
    {
        return $this->customer_phone;
    }

    public function getCustomerToken()
    {
        return $this->customer_token;
    }

    public function getCardCvv()
    {
        return $this->card_cvv;
    }

    public function getCardTokenUniqueId()
    {
        return $this->card_token_unique_id;
    }

    public function getDeleteTokenFromBank()
    {
        return $this->delete_token_from_bank;
    }

    public function getDeductTotalAmount()
    {
        return $this->deduct_total_amount;
    }

    public function getAllowedCardTypes()
    {
        return $this->allowed_card_types;
    }

    public function getFrequency()
    {
        return $this->frequency;
    }

    public function getTokenWebhookUrl()
    {
        return $this->token_webhook_url;
    }

    /**
     * The customerData block shared by the tokenization endpoints.
     *
     * @return array
     */
    public function resolveCustomerData()
    {
        return [
            'customer_unique_id' => $this->customer_unique_id,
            'customer_first_name' => $this->customer_first_name ?: $this->first_name,
            'customer_last_name' => $this->customer_last_name ?: $this->last_name,
            'customer_email' => $this->customer_email ?: $this->user_email,
            'customer_phone' => $this->customer_phone ?: $this->user_phone,
        ];
    }
}
