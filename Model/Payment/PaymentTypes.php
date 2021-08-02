<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Payment;

use Sapient\AccessWorldpay\Api\PaymentTypeInterface;

class PaymentTypes implements PaymentTypeInterface
{

    public function __construct(
        \Sapient\AccessWorldpay\Model\Authorisation\PaymentOptionsService $paymentoptionsservice
    ) {
        $this->paymentoptionsservice = $paymentoptionsservice;
    }
   
    public function getPaymentType($countryId)
    {
        $responsearray = [];
        $result = $this->paymentoptionsservice->collectPaymentOptions($countryId, $paymenttype = null);
        if (!empty($result)) {
            $responsearray = $result;
        }
        return json_encode($responsearray);
    }
}
