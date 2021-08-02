<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Api;
 
interface HistoryInterface
{
    /**
     * Retrive order Notification
     *
     * @api
     * @param Integer $order OrderId.
     * @return json
     */
    public function getHistory($order);
}
