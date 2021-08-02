<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\JsonBuilder;

use Sapient\AccessWorldpay\Model\JsonBuilder\Config\ThreeDSecureConfig;
use Sapient\AccessWorldpay\Logger\AccessWorldpayLogger;

/**
 * Build xml for Direct Order request
 */
class DirectOrder
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
  
    /**
     * Build xml for processing Request
     *
     * @param string $merchantCode
     * @param string $orderCode
     * @param string $orderDescription
     * @param string $currencyCode
     * @param float $amount
     * @param array $paymentDetails
     * @param array $cardAddress
     * @param string $shopperEmail
     * @param string $acceptHeader
     * @param string $userAgentHeader
     * @param string $shippingAddress
     * @param float $billingAddress
     * @param string $shopperId
     * @return SimpleXMLElement $xml
     */
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

    /**
     * Add order and its child tag to xml
     *
     * @return array $order
     */
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

    /**
     * Add description  to json Obj
     *
     * @param
     */
    private function _addTransactionRef()
    {
        return $this->orderCode;
    }
    
    /**
     * Add description  to json Obj
     *
     * @param
     */
    private function _addMerchantInfo()
    {
        $merchantData = ["entity" => $this->paymentDetails['entityRef']];
        return $merchantData;
    }
    
    /**
     * Add description  to json Obj
     *
     * @param
     */
    private function _addInstructionInfo()
    {
        $instruction = [];
        $instruction['narrative'] = $this->_addNarrativeInfo();
        $instruction['value'] = $this->_addValueInfo();
        $instruction['paymentInstrument'] = $this->_addPaymentInfo();
        
        return $instruction;
    }
    
    /**
     * Add description  to json Obj
     *
     * @param
     */
    private function _addNarrativeInfo()
    {
        $narrationData = ["line1" => "trading name"];
        return $narrationData;
    }
    
    /**
     * Add description  to json Obj
     *
     * @param
     */
    private function _addValueInfo()
    {
        $valueData = ["currency" => $this->currencyCode, "amount" => $this->_amountAsInt($this->amount)];
        return $valueData;
    }
    
    /**
     * Add description  to json Obj
     *
     * @param
     */
    private function _addPaymentInfo()
    {
        $paymentData = [];
        if (isset($this->paymentDetails['tokenId']) && isset($this->paymentDetails['cvc'])) {
            $paymentData['type'] = 'card/tokenized';
            $paymentData['href'] = $this->paymentDetails['tokenHref'];
            if (isset($this->paymentDetails['cardOnFileAuthorization'])
                    && $this->paymentDetails['paymentType'] == 'TOKEN-SSL') {
                $paymentData['type'] = 'card/token';
                $paymentData['href'] = $this->paymentDetails['tokenHref'];
                $paymentData['cvc'] = $this->paymentDetails['cvc'];
            }
            return $paymentData;
        } elseif ($this->paymentDetails['paymentType'] == 'TOKEN-SSL') {
            $paymentData['type'] = "card/token";
            $paymentData['href'] = isset($this->paymentDetails['token_url']) ?
                    $this->paymentDetails['token_url'] : $this->paymentDetails['tokenHref'];
            return $paymentData;
        } elseif (isset($this->paymentDetails['cardHolderName'])) {
            $paymentData['type'] = "card/plain";
            $paymentData['cardHolderName'] = $this->paymentDetails['cardHolderName'];
            $paymentData['cardNumber'] = $this->paymentDetails['cardNumber'];
            $paymentData['cardExpiryDate'] = ["month" => (int)$this->paymentDetails['expiryMonth'],
                                              "year" => (int)$this->paymentDetails['expiryYear']];
            return $paymentData;
        } else {
            $obj = \Magento\Framework\App\ObjectManager::getInstance();
            $quote = $obj->get(\Magento\Checkout\Model\Session::class)->getQuote()
                    ->load($this->quoteId);
            $addtionalData = $quote->getPayment()->getOrigData();
            $ccData = $addtionalData['additional_information'];
        
            $paymentData['type'] =  "card/plain";
            $paymentData['cardHolderName'] = $ccData['cc_name'];
            $paymentData['cardNumber'] = $ccData['cc_number'];
            $paymentData['cardExpiryDate'] = ["month" => (int)$ccData['cc_exp_month'],
                                                   "year" => (int)$ccData['cc_exp_year']];
            return $paymentData;
        }
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

    /**
     * @param float $amount
     * @return int
     */
    private function _amountAsInt($amount)
    {
        return round($amount, self::EXPONENT, PHP_ROUND_HALF_EVEN) * pow(10, self::EXPONENT);
    }
}
