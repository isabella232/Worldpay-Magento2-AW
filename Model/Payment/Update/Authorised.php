<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Payment\Update;

use Sapient\AccessWorldpay\Model\Payment\State;
use Sapient\AccessWorldpay\Model\Payment\Update\Base;
use Sapient\AccessWorldpay\Model\Payment\Update;

class Authorised extends Base implements Update
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

    /**
     * @param $payment
     * @param $order
     */
    public function apply($payment, $order = null)
    {
        if (empty($order)) {
            $this->_applyUpdate($payment);
            $this->_worldPayPayment->updateAccessWorldpayPayment($this->_paymentState);
        } else {
            $this->_assertValidPaymentStatusTransition($order, $this->_getAllowedPaymentStatuses($order));
            $this->_applyUpdate($order->getPayment(), $order);
            $this->_worldPayPayment->updateAccessWorldpayPayment($this->_paymentState);
        }
    }

    private function _applyUpdate($payment, $order = null)
    {
        $payment->setTransactionId(time());
        $payment->setIsTransactionClosed(0);
        if (!empty($order)
            && ($order->getPaymentStatus() == State::STATUS_SENT_FOR_AUTHORISATION)) {
            //$currencycode = $this->_paymentState->getCurrency();
            //$currencysymbol = $this->_configHelper->getCurrencySymbol($currencycode);
            //$amount = $this->_amountAsInt($this->_paymentState->getAmount());
            $magentoorder = $order->getOrder();
            $magentoorder->addStatusToHistory($magentoorder->getStatus(), 'Authorized amount of ');
            $transaction = $payment->addTransaction('authorization', null, false, null);
            $transaction->save();
            $magentoorder->save();
        }
    }

    /**
     * @param \Sapient\AccessWorldpay\Model\Order $order
     * @return array
     */
    private function _getAllowedPaymentStatuses(\Sapient\AccessWorldpay\Model\Order $order)
    {
        if (!empty($order) && $order->hasWorldPayPayment()) {
            if ($this->_isDirectIntegrationMode($order)) {
                 return [
                \Sapient\AccessWorldpay\Model\Payment\State::STATUS_SENT_FOR_AUTHORISATION,
                \Sapient\AccessWorldpay\Model\Payment\State::STATUS_AUTHORISED
                 ];
            }
            return [\Sapient\AccessWorldpay\Model\Payment\State::STATUS_SENT_FOR_AUTHORISATION];
        } else {
            throw new \Magento\Framework\Exception\LocalizedException(__('No Payment'));
        }
    }

    /**
     * check if integration mode is direct
     * @return bool
     */
    private function _isDirectIntegrationMode(\Sapient\AccessWorldpay\Model\Order $order)
    {
        return $this->_configHelper->getIntegrationModelByPaymentMethodCode(
            $order->getPaymentMethodCode(),
            $order->getStoreId()
        )
            === \Sapient\AccessWorldpay\Model\PaymentMethods\AbstractMethod::DIRECT_MODEL;
    }

    /**
     * check if integration mode is redirect
     * @return bool
     */
    private function _isRedirectIntegrationMode(\Sapient\AccessWorldpay\Model\Order $order)
    {
        return $this->_configHelper->getIntegrationModelByPaymentMethodCode(
            $order->getPaymentMethodCode(),
            $order->getStoreId()
        )
            === \Sapient\AccessWorldpay\Model\PaymentMethods\AbstractMethod::REDIRECT_MODEL;
    }
}
