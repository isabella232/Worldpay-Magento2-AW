<?php
/**
 * @copyright 2017 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Payment\Update;

use Sapient\AccessWorldpay\Model\Payment\Update;
use Sapient\AccessWorldpay\Model\Payment\Update\Base;

class Captured extends Base implements Update
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
            $order->capture();
            $this->_worldPayPayment->updateAccessWorldpayPayment($this->_paymentState);
        } else {
            $this->_worldPayPayment->updateAccessWorldpayPayment($this->_paymentState);
        }
    }

    /**
     * @return array
     */
    protected function _getAllowedPaymentStatuses()
    {
        return [
            \Sapient\AccessWorldpay\Model\Payment\State::STATUS_SENT_FOR_AUTHORISATION,
            \Sapient\AccessWorldpay\Model\Payment\State::STATUS_PENDING_PAYMENT,
            \Sapient\AccessWorldpay\Model\Payment\State::STATUS_AUTHORISED
        ];
    }
}