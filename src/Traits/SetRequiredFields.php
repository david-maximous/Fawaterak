<?php
namespace DavidMaximous\Fawaterak\Traits;

use DavidMaximous\Fawaterak\Exceptions\MissingPaymentInfoException;

trait SetRequiredFields
{
    /**
     * Check required fields and throw Exception if null
     *
     * @param  array $required_fields
     * @return void
     */
    public function checkRequiredFields($required_fields)
    {

        $amount = $this->amount ?? null;
        $first_name = $this->first_name ?? null;
        $last_name = $this->last_name ?? null;
        $user_email = $this->user_email ?? null;
        $user_phone = $this->user_phone ?? null;
        $method = $this->method ?? null;

        foreach($required_fields as $field){
            $this->{$field} = $this->{$field} ?? ${$field};
            if (is_null($this->{$field})) throw new MissingPaymentInfoException($field);
        }
    }

}
