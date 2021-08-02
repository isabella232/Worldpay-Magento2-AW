<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Payment;

interface Update
{
    public function apply($payment);
    public function getTargetOrderCode();
}
