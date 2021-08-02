<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Payment\Update;

class Factory
{
    /** @var \Sapient\AccessWorldpay\Helper\Data */
    private $_configHelper;
    /**
     * Constructor
     * @param \Sapient\AccessWorldpay\Helper\Data $configHelper
     * @param \Sapient\AccessWorldpay\Model\Payment\WorldPayPayment $worldPayPayment
     */
    public function __construct(
        \Sapient\AccessWorldpay\Helper\Data $configHelper,
        \Sapient\AccessWorldpay\Model\Payment\WorldPayPayment $worldpaymentmodel
    ) {
            $this->_configHelper = $configHelper;
            $this->worldpaymentmodel = $worldpaymentmodel;
    }

    /**
     * @param \Sapient\AccessWorldpay\Model\Payment\State $paymentState
     * @return object
     */
    public function create(\Sapient\AccessWorldpay\Model\Payment\State $paymentState)
    {   
        switch ($paymentState->getPaymentStatus()) {
            case \Sapient\AccessWorldpay\Model\Payment\State::STATUS_AUTHORISED:
                return new \Sapient\AccessWorldpay\Model\Payment\Update\Authorised(
                    $paymentState,
                    $this->worldpaymentmodel,
                    $this->_configHelper
                );
                
            case \Sapient\AccessWorldpay\Model\Payment\State::STATUS_CAPTURED:
                return new \Sapient\AccessWorldpay\Model\Payment\Update\Captured(
                    $paymentState,
                    $this->worldpaymentmodel,
                    $this->_configHelper
                );
            case \Sapient\AccessWorldpay\Model\Payment\State::STATUS_SENT_FOR_SETTLEMENT:
                $state = \Sapient\AccessWorldpay\Model\Payment\State::STATUS_SENT_FOR_SETTLEMENT;
                $reference = $paymentState->getJournalReference($state);
                if(isset($reference) && strtoupper($reference) == 'PARTIAL CAPTURE') {
                  return new \Sapient\AccessWorldpay\Model\Payment\Update\PartialCaptured(
                    $paymentState,
                    $this->worldpaymentmodel,
                    $this->_configHelper
                );  
                }
                return new \Sapient\AccessWorldpay\Model\Payment\Update\Captured(
                    $paymentState,
                    $this->worldpaymentmodel,
                    $this->_configHelper
                );
                
            case \Sapient\AccessWorldpay\Model\Payment\State::STATUS_PARTIAL_CAPTURED:
                return new \Sapient\AccessWorldpay\Model\Payment\Update\PartialCaptured(
                    $paymentState,
                    $this->worldpaymentmodel,
                    $this->_configHelper
                );
                
            case \Sapient\AccessWorldpay\Model\Payment\State::STATUS_REFUNDED:
                return new \Sapient\AccessWorldpay\Model\Payment\Update\Refunded(
                    $paymentState,
                    $this->worldpaymentmodel,
                    $this->_configHelper
                );
            
            case \Sapient\AccessWorldpay\Model\Payment\State::STATUS_PARTIAL_REFUNDED:
                return new \Sapient\AccessWorldpay\Model\Payment\Update\PartialRefunded(
                    $paymentState,
                    $this->worldpaymentmodel,
                    $this->_configHelper
                );
                
            case \Sapient\AccessWorldpay\Model\Payment\State::STATUS_CANCELLED:
                return new \Sapient\AccessWorldpay\Model\Payment\Update\Cancelled(
                    $paymentState,
                    $this->worldpaymentmodel,
                    $this->_configHelper
                );

            case \Sapient\AccessWorldpay\Model\Payment\State::STATUS_REFUSED:
                return new \Sapient\AccessWorldpay\Model\Payment\Update\Refused(
                    $paymentState,
                    $this->worldpaymentmodel,
                    $this->_configHelper
                );

            case \Sapient\AccessWorldpay\Model\Payment\State::STATUS_ERROR:
                return new \Sapient\AccessWorldpay\Model\Payment\Update\Error(
                    $paymentState,
                    $this->worldpaymentmodel,
                    $this->_configHelper
                );

            case \Sapient\AccessWorldpay\Model\Payment\State::STATUS_PENDING_PAYMENT:
                return new \Sapient\AccessWorldpay\Model\Payment\Update\PendingPayment(
                    $paymentState,
                    $this->worldpaymentmodel,
                    $this->_configHelper
                );

            default:
                return new \Sapient\AccessWorldpay\Model\Payment\Update\Defaultupdate(
                    $paymentState,
                    $this->worldpaymentmodel,
                    $this->_configHelper
                );
        }
    }
}
