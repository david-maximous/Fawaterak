<?php

namespace DavidMaximous\Fawaterak\Traits;

trait SetRefundVariables
{
    public $refund_type = null;
    public $refund_id = null;
    public $reason = null;
    public $refundable_amount = null;
    public $comment = null;

    /**
     * Sets the refund type.
     *
     * @param  string|int  $value  FawaterakRefund::INVOICE, PAYMENT_LINK,
     *                             COLLECTION_LINK or INTEGRATION_TRANSACTION.
     * @return $this
     */
    public function setRefundType($value)
    {
        $this->refund_type = (string) $value;

        return $this;
    }

    /**
     * Sets the id of the invoice or transaction being refunded.
     *
     * @param  int  $value
     * @return $this
     */
    public function setRefundId($value)
    {
        $this->refund_id = $value;

        return $this;
    }

    /**
     * Sets the refund reason. Must be one of the values returned by reasons().
     *
     * @param  string  $value
     * @return $this
     */
    public function setReason($value)
    {
        $this->reason = $value;

        return $this;
    }

    /**
     * Sets the amount to refund, partial or full.
     *
     * @param  float  $value
     * @return $this
     */
    public function setRefundableAmount($value)
    {
        $this->refundable_amount = $value;

        return $this;
    }

    /**
     * Sets an optional internal note.
     *
     * @param  string  $value
     * @return $this
     */
    public function setComment($value)
    {
        $this->comment = $value;

        return $this;
    }

    public function getRefundType()
    {
        return $this->refund_type;
    }

    public function getRefundId()
    {
        return $this->refund_id;
    }

    public function getReason()
    {
        return $this->reason;
    }

    public function getRefundableAmount()
    {
        return $this->refundable_amount;
    }

    public function getComment()
    {
        return $this->comment;
    }
}
