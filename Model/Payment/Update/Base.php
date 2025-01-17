<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Payment\Update;

use Exception;
use \Magento\Framework\Exception\LocalizedException;

class Base
{
    /** @var \Sapient\AccessWorldpay\Model\Payment\State */
    protected $_paymentState;
    /** @var \Sapient\AccessWorldpay\Model\Payment\WorldPayPayment */
    protected $_worldPayPayment;

    const STATUS_PROCESSING = 'processing';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_CLOSED = 'closed';

    /**
     * Constructor
     * @param \Sapient\AccessWorldpay\Model\Payment\State $paymentState
     * @param \Sapient\AccessWorldpay\Model\Payment\WorldPayPayment $worldPayPayment
     */
    public function __construct(
        \Sapient\AccessWorldpay\Model\Payment\State $paymentState,
        \Sapient\AccessWorldpay\Model\Payment\WorldPayPayment $worldPayPayment
    ) {

        $this->_paymentState = $paymentState;
        $this->_worldPayPayment = $worldPayPayment;
    }
    /**
     * @return string ordercode
     */
    public function getTargetOrderCode()
    {
        return $this->_paymentState->getOrderCode();
    }

    /**
     * check payment Status
     * @param object $order
     * @param array $allowedPaymentStatuses
     * @return null
     * @throws Exception
     */
    protected function _assertValidPaymentStatusTransition($order, $allowedPaymentStatuses)
    {
        $this->_assertPaymentExists($order);
        
        $existingPaymentStatus = $order->getPaymentStatus();
        
        $newPaymentStatus = $this->_paymentState->getPaymentStatus();
        
        if (in_array($existingPaymentStatus, $allowedPaymentStatuses)) {
            return;
        }

        if ($existingPaymentStatus == $newPaymentStatus) {
            throw new \Magento\Framework\Exception\LocalizedException(__('same state'));
        }

        throw new \Magento\Framework\Exception\LocalizedException(__('invalid state transition'));
    }

    /**
     * check if order is not placed throgh worldpay payment
     * @throws Exception
     */
    private function _assertPaymentExists($order)
    {
        if (!$order->hasWorldPayPayment()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('No payment'));
        }
    }

    /*
    * convert worldpay amount to magento amount
    */
    protected function _amountAsInt($amount)
    {
        return round($amount, 2, PHP_ROUND_HALF_EVEN) / pow(10, 2);
    }
}
