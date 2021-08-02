<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Sapient\AccessWorldpay\Api;

/**
 *
 * @author aatrai
 */
interface DdcRequestInterface
{
    /**
     * Create DDcRequest
     *
     * @api
     * @param string $cartId
     * @param mixed $paymentData
     * @return null|string
     */
    public function createDdcRequest($cartId, $paymentData);
}
