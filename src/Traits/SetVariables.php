<?php
namespace DavidMaximous\Fawaterak\Traits;

trait SetVariables
{
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
     * Sets method
     *
     * @param  string  $value
     * @return $this
     */
    public function setMethod($value)
    {
        $this->method = $value;
        return $this;
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
     * set passed vaiables to pay function to be global
     * @param $amount
     * @param null $first_name
     * @param null $last_name
     * @param null $user_email
     * @param null $user_phone
     * @param null $method
     * @param null $item_name
     * @param null $quantity
     * @param null $currency
     * @param null $language
     * @param array|null $payload
     * @return void
     */
    public function setPassedVariablesToGlobal($amount, $first_name, $last_name, $user_email, $user_phone, $method, $item_name, $quantity, $currency, $language, ?array $payload)
    {
        if($amount!=null)$this->setAmount($amount);
        if($first_name!=null)$this->setFirstName($first_name);
        if($last_name!=null)$this->setLastName($last_name);
        if($user_email!=null)$this->setUserEmail($user_email);
        if($user_phone!=null)$this->setUserPhone($user_phone);
        if($method!=null)$this->setMethod($method);
        if($item_name!=null)$this->setItemName($item_name);
        if($quantity!=null)$this->setQuantity($quantity);
        if($currency!=null)$this->setCurrency($currency);
        if($language!=null)$this->setLanguage($language);
        if($payload!=null)$this->setPayload($payload);
    }


}
