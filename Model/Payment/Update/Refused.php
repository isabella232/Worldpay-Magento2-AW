<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Payment\Update;

use Sapient\AccessWorldpay\Model\Payment\Update;
use Sapient\AccessWorldpay\Model\Payment\Update\Base;

class Refused extends Base implements Update
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
            $this->_assertValidPaymentStatusTransition($order, $this->_getAllowedPaymentStatuses());
            $this->_worldPayPayment->updateAccessWorldpayPayment($this->_paymentState);
            $order->cancel();
        }
    }

    /**
     * @return array
     */
    protected function _getAllowedPaymentStatuses()
    {
        return [
            \Sapient\AccessWorldpay\Model\Payment\State::STATUS_SENT_FOR_AUTHORISATION,
            \Sapient\AccessWorldpay\Model\Payment\State::STATUS_AUTHORISED
        ];
    }
}
