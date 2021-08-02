<?php


namespace Sapient\AccessWorldpay\Model\JsonBuilder;

class WebSdkOrder
{
    const EXPONENT = 2;
    
    private $merchantCode;
    private $orderCode;
    private $orderDescription;
    private $currencyCode;
    private $amount;
    protected $paymentDetails;
    private $cardAddress;
    protected $shopperEmail;
    protected $acceptHeader;
    protected $userAgentHeader;
    private $shippingAddress;
    private $billingAddress;
    protected $paResponse = null;
    private $echoData = null;
    private $shopperId;
    private $quoteId;
    private $threeDSecureConfig;
    
    public function build(
        $merchantCode,
        $orderCode,
        $orderDescription,
        $currencyCode,
        $amount,
        $paymentDetails,
        $cardAddress,
        $shopperEmail,
        $acceptHeader,
        $userAgentHeader,
        $shippingAddress,
        $billingAddress,
        $shopperId,
        $quoteId,
        $threeDSecureConfig
    ) {
        $this->merchantCode = $merchantCode;
        $this->orderCode = $orderCode;
        $this->orderDescription = $orderDescription;
        $this->currencyCode = $currencyCode;
        $this->amount = $amount;
        $this->paymentDetails = $paymentDetails;
        $this->cardAddress = $cardAddress;
        $this->shopperEmail = $shopperEmail;
        $this->acceptHeader = $acceptHeader;
        $this->userAgentHeader = $userAgentHeader;
        $this->shippingAddress = $shippingAddress;
        $this->billingAddress = $billingAddress;
        $this->shopperId = $shopperId;
        $this->quoteId = $quoteId;
        $this->threeDSecureConfig =$threeDSecureConfig;
        
        $jsonData = $this->_addOrderElement();

        return json_encode($jsonData);
    }
    
    private function _addOrderElement()
    {
        $orderData = [];
       
        $orderData['transactionReference'] = $this->_addTransactionRef();
        $orderData['merchant'] = $this->_addMerchantInfo();
        $orderData['instruction'] = $this->_addInstructionInfo();
        if ($this->threeDSecureConfig != '') {
            $orderData['customer'] = $this->_addCustomer();
        }
        return $orderData;
    }
    
    private function _addCustomer()
    {
        $customer =[];
        $customer["authentication"] = $this->_addAuthenticationData();
        
        return $customer;
    }
    
    private function _addAuthenticationData()
    {
        $authenticationData =[];
        $authenticationData["version"] = $this->threeDSecureConfig['authentication']['version'];
        $authenticationData["type"] = "3DS";
        $authenticationData["eci"] = $this->threeDSecureConfig['authentication']['eci'];
        if (isset($this->threeDSecureConfig['authentication']['authenticationValue'])) {
            $authenticationData["authenticationValue"] = $this->
                    threeDSecureConfig['authentication']['authenticationValue'];
        }
        if (isset($this->threeDSecureConfig['authentication']['transactionId'])) {
            $authenticationData["transactionId"] = $this->
                    threeDSecureConfig['authentication']['transactionId'];
        }
        
        return $authenticationData;
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
        $instruction['narrative'] = $this->_addNarrativeInfo();
        $instruction['value'] = $this->_addValueInfo();
        $instruction['paymentInstrument'] = $this->_addPaymentInfo();
        
        return $instruction;
    }
    
    private function _addNarrativeInfo()
    {
        $narrationData = ["line1" => "trading name"];
        return $narrationData;
    }
    
    private function _addValueInfo()
    {
        $valueData = ["currency" => $this->currencyCode, "amount" => $this->_amountAsInt($this->amount)];
        return $valueData;
    }
    
    private function _addPaymentInfo()
    {
        $paymentData = [];
        if (isset($this->paymentDetails['verifiedToken'])) {
            $paymentData['type'] = 'card/token';
            $paymentData['href'] = $this->paymentDetails['verifiedToken'];
            return $paymentData;
        } elseif (isset($this->paymentDetails['tokenId']) && isset($this->paymentDetails['cvcHref'])) {
            $paymentData['type'] = 'card/tokenized';
            $paymentData['href'] = $this->paymentDetails['tokenHref'];
            if (isset($this->paymentDetails['cardOnFileAuthorization'])) {
                $paymentData1 = [];
                $paymentData1['type'] = 'card/checkout';
                $paymentData1['tokenHref'] = $this->paymentDetails['tokenHref'];
                $paymentData1['cvcHref'] = $this->paymentDetails['cvcHref'];
                return $paymentData1;
            }
            return $paymentData;
        }
    }
    
    private function _amountAsInt($amount)
    {
        return round($amount, self::EXPONENT, PHP_ROUND_HALF_EVEN) * pow(10, self::EXPONENT);
    }
}
