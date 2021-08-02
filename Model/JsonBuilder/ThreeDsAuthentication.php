<?php

namespace Sapient\AccessWorldpay\Model\JsonBuilder;

class ThreeDsAuthentication
{
    
    const EXPONENT = 2;
    private $orderCode;
    private $paymentDetails;
    private $billingAddress;
    private $currencyCode;
    private $amount;
    private $acceptHeader;
    private $userAgentHeader;
    private $riskData;
    
    public function build(
        $orderCode,
        $paymentDetails,
        $billingAddress,
        $currencyCode,
        $amount,
        $acceptHeader,
        $userAgentHeader,
        $riskData
    ) {
        $this->orderCode = $orderCode;
        $this->paymentDetails = $paymentDetails;
        $this->billingAddress = $billingAddress;
        $this->currencyCode = $currencyCode;
        $this->amount = $amount;
        $this->acceptHeader = $acceptHeader;
        $this->userAgentHeader = $userAgentHeader;
        $this->riskData = $riskData;
        $jsonData = $this->_addOrderElement();
        return json_encode($jsonData);
    }
    
    private function _addOrderElement()
    {
        $orderData = [];
       
        $orderData['transactionReference'] = $this->_addTransactionRef();
        $orderData['merchant'] = $this->_addMerchantInfo();
        $orderData['instruction'] = $this->_addInstructionInfo();
        $orderData['deviceData'] = $this->_addDeviceData();
        $orderData['challenge'] = $this->_addUrl();
        if (isset($this->riskData)) {
            $orderData['riskData'] = $this->_addRiskData();
        }
        
        return $orderData;
    }
    
    private function _addTransactionRef()
    {
        return $this->orderCode;
    }
    
    private function _addMerchantInfo()
    {
        $merchantData = ["entity" => $this->paymentDetails['entityRef']];
        return $merchantData;
    }
    
    private function _addInstructionInfo()
    {
        $instruction = [];
        $instruction['paymentInstrument'] = $this->_addPaymentInfo();
        //$instruction['billingAddress'] = $this ->_addBillingAddress();
        $instruction['value'] = $this->_addValue();
        return $instruction;
    }
    
    private function _addPaymentInfo()
    {
        if (isset($this->paymentDetails['token_url'])) {
            $tokenurl = $this->paymentDetails['token_url'];
        } else {
            $tokenurl = $this->paymentDetails['tokenHref'];
        }
        $paymentData = ["type" => "card/tokenized",
                        "href" => $tokenurl
                             ];
        return $paymentData;
    }
    
    private function _addBillingAddress()
    {
        $billingData = ["address1" => $this->billingAddress['street'],
                             "postalCode" => $this->billingAddress['postalCode'],
                             "city" =>$this->billingAddress['city'],
                             "countryCode" => $this->billingAddress['countryCode']];
        return $billingData;
    }
    
    private function _addValue()
    {
        $valueData = ["currency" =>$this->currencyCode,
            "amount" =>$this->_amountAsInt($this->amount)];
        return $valueData;
    }
    
    private function _amountAsInt($amount)
    {
        return round($amount, self::EXPONENT, PHP_ROUND_HALF_EVEN) * pow(10, self::EXPONENT);
    }
    
    private function _addDeviceData()
    {
        if (isset($this->paymentDetails['collectionReference'])) {
            $deviceData ["collectionReference"] = $this->paymentDetails['collectionReference'];
        }
        $deviceData[ "acceptHeader" ]= $this->acceptHeader;
        $deviceData[ "userAgentHeader"] = $this->userAgentHeader;
        return $deviceData;
    }
    
    private function _addUrl()
    {
        $urlData = [ "returnUrl" => $this->paymentDetails['url']];
        return $urlData;
    }
    
    private function _addRiskData()
    {
        $riskData = [
           "account" => $this->addRiskAccountData(),
           "transaction" => $this->addRiskTransactionData(),
           "shipping" => $this->addRiskShippingData()
           ];
           
        return $riskData;
    }
    
    private function addRiskAccountData()
    {
        $account = [
           "type"  => $this->riskData['type'],
           "email" => $this->riskData['email'],
           "history" => [
             "createdAt" =>$this->riskData['createdAt'],
             "modifiedAt" =>$this->riskData['modifiedAt']
           ],
        ];
        
        return $account;
    }
    
    private function addRiskTransactionData()
    {
        $transaction = [
           "firstName"  => $this->riskData['firstName'],
           "lastName" => $this->riskData['lastName']
        ];
        
        return $transaction;
    }
    
    private function addRiskShippingData()
    {
        $shipping = [
         "nameMatchesAccountName" => $this->riskData['nameMatchesAccountName'] ,
         "email" => $this->riskData['email']
        ];
        
        return $shipping;
    }
}
