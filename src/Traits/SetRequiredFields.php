<?php

namespace DavidMaximous\Fawaterak\Traits;

use DavidMaximous\Fawaterak\Exceptions\MissingPaymentInfoException;

trait SetRequiredFields
{
    /**
     * Check required fields and throw an exception when one is missing.
     *
     * Each entry is a property name on the current object. Dot notation is
     * supported for nested array properties, e.g. "order.currency".
     *
     * @param  array  $required_fields
     * @return void
     *
     * @throws MissingPaymentInfoException
     */
    public function checkRequiredFields($required_fields)
    {
        foreach ((array) $required_fields as $field) {
            if ($this->isBlankField($this->readField($field))) {
                throw new MissingPaymentInfoException($field);
            }
        }
    }

    /**
     * Check required keys inside an already built array payload.
     *
     * @param  array  $payload
     * @param  array  $required_keys  Dot notation supported.
     * @return void
     *
     * @throws MissingPaymentInfoException
     */
    public function checkRequiredKeys(array $payload, array $required_keys)
    {
        foreach ($required_keys as $key) {
            if ($this->isBlankField($this->readFromArray($payload, $key))) {
                throw new MissingPaymentInfoException($key);
            }
        }
    }

    /**
     * Read a property, supporting "property.nested.key" notation.
     *
     * @param  string  $field
     * @return mixed
     */
    protected function readField($field)
    {
        if (strpos($field, '.') === false) {
            return $this->{$field} ?? null;
        }

        $segments = explode('.', $field);
        $value = $this->{array_shift($segments)} ?? null;

        return $this->readFromArray(is_array($value) ? $value : [], implode('.', $segments));
    }

    /**
     * Read a dot notated key out of an array.
     *
     * @param  array  $array
     * @param  string  $key
     * @return mixed
     */
    protected function readFromArray(array $array, $key)
    {
        $value = $array;

        foreach (explode('.', $key) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * A field counts as missing when it is null, an empty string or an
     * empty array. Zero and false are valid values.
     *
     * @param  mixed  $value
     * @return bool
     */
    protected function isBlankField($value)
    {
        if (is_null($value)) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_array($value)) {
            return count($value) === 0;
        }

        return false;
    }
}
