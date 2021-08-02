<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Payment\Update;

use Sapient\AccessWorldpay\Model\Payment\Update;
use Sapient\AccessWorldpay\Model\Payment\Update\Base;

class PendingPayment extends Base implements Update
{
    /** @var \Sapient\AccessWorldpay\Helper\Data */
    private $_configHelper;
    /**
     * Constructor
     * @param \Sapient\AccessWorldpay\Model\Payment\State $paymentState
     * @param \Sapient\AccessWorldpay\Model\Payment\WorldPayPayment $worldPayPayment
     * @param \Sapient\AccessWorldpay\Helper\Data $configHelper
     */
    public function __construct(
        \Sapient\AccessWorldpay\Model\Payment\State $paymentState,
        \Sapient\AccessWorldpay\Model\Payment\WorldPayPayment $worldPayPayment,
        \Sapient\AccessWorldpay\Helper\Data $configHelper
    ) {
        $this->_paymentState = $paymentState;
        $this->_worldPayPayment = $worldPayPayment;
        $this->_configHelper = $configHelper;
    }

    public function apply($payment, $order = null)
    {
        if (!empty($order)) {
            $this->_worldPayPayment->updateAccessWorldpayPayment($this->_paymentState);
             $order->pendingPayment();
        }
    }
}
