<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Payment\Update;

use Sapient\AccessWorldpay\Model\Payment\Update;
use Sapient\AccessWorldpay\Model\Payment\Update\Base;

class PartialRefunded extends Base implements Update
{
    /** @var \Sapient\AccessWorldpay\Helper\Data */
    private $_configHelper;
    const REFUND_COMMENT = 'Refund request PROCESSED by the bank.';
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
        $reference = $this->_paymentState->getJournalReference(
            \Sapient\AccessWorldpay\Model\Payment\State::STATUS_PARTIAL_REFUNDED
        );
        if(isset($reference) && !empty($order)) {
        $message = self::REFUND_COMMENT . ' Reference: ' . $reference;
        $order->refund($reference, $message);
        }
        $this->_worldPayPayment->updateAccessWorldpayPayment($this->_paymentState);
    }
}
