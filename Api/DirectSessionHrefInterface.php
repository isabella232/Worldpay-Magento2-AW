<?php

namespace Sapient\AccessWorldpay\Api;

interface DirectSessionHrefInterface
{
     /**
      * Create SessionHref for Direct Integration
      *
      * @api
      * @param string $id
      * @param mixed $paymentData
      * @return null|string
      */
    public function createSessionHref($id, $paymentData);
}
