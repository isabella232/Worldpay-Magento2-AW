<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Sapient\AccessWorldpay\Model\Payment;

use Sapient\AccessWorldpay\Api\DdcRequestInterface;

class DdcRequest implements DdcRequestInterface
{
    public function __construct(
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger,
        \Sapient\AccessWorldpay\Helper\Data $worldpayHelper,
        \Sapient\AccessWorldpay\Model\Request\PaymentServiceRequest $paymentservicerequest,
        \Sapient\AccessWorldpay\Model\Payment\Service $paymentservice,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Quote\Model\Quote $quote
    ) {
        $this->wplogger = $wplogger;
        $this->worldpayHelper = $worldpayHelper;
        $this->_paymentservicerequest = $paymentservicerequest;
        $this->paymentservice = $paymentservice;
        $this->checkoutSession = $checkoutSession;
        $this->quote = $quote;
        $this->customerSession = $customerSession;
    }
    
    public function createDdcRequest($cartId, $paymentData)
    {
         $aditionalData = $paymentData['additional_data'];
         $incrementId = '';
        if (isset($paymentData['additional_data']['publicHash'])) {
            $this->wplogger->info("Inititializing device data for Instant Purchase for cusomerID-".
                    $this->getCustomerId());
            $summary = explode(' ', $paymentData['additional_data']['summary']);
            $index = array_search('ending:', $summary)+1;
            $token_url = $this->worldpayHelper->getTokenFromVault(
                $paymentData['additional_data']['publicHash'],
                $this->getCustomerId()
            );
            $incrementId = $this->getCustomerId().$summary[$index];
            $payment = [
                'token_url' => $token_url
                ];
        } elseif (isset($paymentData['additional_data']['tokenId'])
                 && $paymentData['additional_data']['tokenId'] != '') {
            $this->wplogger->info("Inititializing device data for checkout for customer with cartId-".$cartId);
            $tokendata = $this->worldpayHelper->getSelectedSavedCardTokenData(
                $paymentData['additional_data']['tokenId']
            );
            $token_url= $tokendata[0]['token'];
             
            $payment = [
                'token_url' => $token_url
                ];
                 
        } else {
            $this->wplogger->info("Inititializing device data for checkout for customer with cartId-".$cartId);
            if (isset($paymentData['additional_data']['sessionHref'])
                && $paymentData['additional_data']['sessionHref'] != '') {
                 $payment = [
                     'sessionHref' => $aditionalData['sessionHref']
                 ];
            } else {
                $payment = [
                'cardNumber' => $aditionalData['cc_number'],
                'paymentType' => $aditionalData['cc_type'],
                'cardHolderName' => $aditionalData['cc_name'],
                'expiryMonth' => $aditionalData['cc_exp_month'],
                'expiryYear' => $aditionalData['cc_exp_year'],
               //'cseEnabled' => $fullRequest->payment->cseEnabled
                ];
            }
               //cvc disabled
            if (isset($aditionalData['cc_cid']) && !$aditionalData['cc_cid'] == '') {
                $payment['cvc'] = $aditionalData['cc_cid'];
            }
        }
                $orderParams = [];
               // $this->quote->reserveOrderId()->save();
                
                //entity ref
                $payment['entityRef'] = $this->worldpayHelper->getMerchantEntityReference();
                $customerId = $this->getCustomerId();
                $orderParams['orderCode'] = $incrementId?$incrementId. '-' . time():$cartId. '-' . time();
                $orderParams['paymentDetails'] = $payment;
                $ddcresponse = $this->_paymentservicerequest->_createDeviceDataCollection($orderParams);
        if (isset($ddcresponse['outcome']) && $ddcresponse['outcome'] === 'initialized') {
            $this->checkoutSession->setDdcUrl($ddcresponse['deviceDataCollection']['url']);
            $this->checkoutSession->setDdcJwt($ddcresponse['deviceDataCollection']['jwt']);
            $this->checkoutSession->set3Dsparams($ddcresponse);
            //$this->checkoutSession->setDirectOrderParams($directOrderParams);
            $this->checkoutSession->setAuthOrderId($orderParams['orderCode']);
        } else {
            if ($ddcresponse['message']==='Requested token does not exist') {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __($this->worldpayHelper->getCreditCardSpecificException('CCAM9'))
                );
            } else {
                throw new \Magento\Framework\Exception\LocalizedException(__($ddcresponse['message']));
            }
        }
    }
    
    public function getCustomerId()
    {
        if ($this->customerSession->isLoggedIn()) {
            return $this->customerSession->getCustomer()->getId();
        }
    }
}
