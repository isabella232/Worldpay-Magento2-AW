<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Sapient\AccessWorldpay\Model\Authorisation;

/**
 * Description of VaultService
 *
 * @author aatrai
 */
use Exception;

class VaultService extends \Magento\Framework\DataObject
{
    protected $checkoutSession;
    public function __construct(
        \Sapient\AccessWorldpay\Model\Mapping\Service $mappingservice,
        \Sapient\AccessWorldpay\Model\Request\PaymentServiceRequest $paymentservicerequest,
        \Sapient\AccessWorldpay\Model\Response\DirectResponse $directResponse,
        \Sapient\AccessWorldpay\Model\Payment\UpdateAccessWorldpaymentFactory $updateWorldPayPayment,
        \Sapient\AccessWorldpay\Model\Payment\Service $paymentservice,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Sapient\AccessWorldpay\Helper\Data $worldpayHelper,
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger,
        \Sapient\AccessWorldpay\Helper\Registry $registryhelper
    ) {
        $this->mappingservice = $mappingservice;
        $this->paymentservicerequest = $paymentservicerequest;
        $this->directResponse = $directResponse;
        $this->paymentservice = $paymentservice;
        $this->updateWorldPayPayment = $updateWorldPayPayment;
        $this->checkoutSession = $checkoutSession;
        $this->worldpayHelper = $worldpayHelper;
        $this->wplogger = $wplogger;
        $this->registryhelper = $registryhelper;
    }
    public function authorizePayment(
        $mageOrder,
        $quote,
        $orderCode,
        $orderStoreId,
        $paymentDetails,
        $payment
    ) {
        
        if ($this->worldpayHelper->is3DSecureEnabled()) {
            $directOrderParams = $this->mappingservice->collectVaultOrderParameters(
                $orderCode,
                $quote,
                $orderStoreId,
                $paymentDetails
            );
            
            $this->checkoutSession->setDirectOrderParams($directOrderParams);
            $payment->setIsTransactionPending(1);
            $threeDSecureConfig = $this->get3DS2ConfigValues();
            $this->checkoutSession->set3DS2Config($threeDSecureConfig);
        } else {
        
            $directOrderParams = $this->mappingservice->collectVaultOrderParameters(
                $orderCode,
                $quote,
                $orderStoreId,
                $paymentDetails
            );

            $response = $this->paymentservicerequest->order($directOrderParams);
            $directResponse = $this->directResponse->setResponse($response);
            $output = $this->checkforError($directResponse);
            if ($output) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __($this->worldpayHelper->getCreditCardSpecificException('CCAM18'))
                );
            } else {
                $orderId = $quote->getReservedOrderId();
                $this->updateWorldPayPayment->create()->updateAccessWorldpayPayment(
                    $orderId,
                    $orderCode,
                    $directResponse,
                    $payment
                );
                $this->_applyPaymentUpdate($directResponse, $payment);
            }
        }
    }
    
    public function checkforError($response)
    {
        $responseXml = $response->getXml();
        $responseArray = json_decode(json_encode($responseXml), true);
        if (isset($responseArray['outcome']) && $responseArray['outcome'] !== 'authorized') {
            $this->wplogger->error('Payment '. strtoupper($responseArray['outcome']));
            return true;
        }
        return false;
    }

    private function _applyPaymentUpdate(
        \Sapient\AccessWorldpay\Model\Response\DirectResponse $directResponse,
        $payment
    ) {
        $paymentUpdate = $this->paymentservice->createPaymentUpdateFromWorldPayXml($directResponse->getXml());
        $paymentUpdate->apply($payment);
        $this->_abortIfPaymentError($paymentUpdate);
    }
    
    public function capturePayment(
        $mageOrder,
        $quote,
        $response,
        $payment
    ) {
        $directResponse = $this->directResponse->setResponse($response);
        $this->updateWorldPayPayment->create()->updatePaymentSettlement($response);
        $this->_applyPaymentUpdate($directResponse, $payment);
    }
    
    public function refundPayment(
        $mageOrder,
        $quote,
        $response,
        $payment
    ) {
        $directResponse = $this->directResponse->setResponse($response);
        $this->_applyPaymentUpdate($directResponse, $payment);
    }
    
    private function _abortIfPaymentError($paymentUpdate)
    {

        if ($paymentUpdate instanceof \Sapient\WorldPay\Model\Payment\Update\Refused) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Payment REFUSED')
            );
        }

        if ($paymentUpdate instanceof \Sapient\WorldPay\Model\Payment\Update\Cancelled) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Payment CANCELLED')
            );
        }

        if ($paymentUpdate instanceof \Sapient\WorldPay\Model\Payment\Update\Error) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Payment ERROR')
            );
        }
    }
    
    // get 3ds2 params from the configuration and set to checkout session
    public function get3DS2ConfigValues()
    {
        $data = [];
        $data['challengeWindowType'] = $this->worldpayHelper->getChallengeWindowSize();
        return $data;
    }
}
