<?php
declare(strict_types=1);

namespace Sapient\AccessWorldpay\Model\Payment;

class Hpp extends \Magento\Payment\Model\Method\AbstractMethod
{

    protected $_code = "hpp";
    protected $_isOffline = true;

    public function isAvailable(
        \Magento\Quote\Api\Data\CartInterface $quote = null
    ) {
        return parent::isAvailable($quote);
    }
}
