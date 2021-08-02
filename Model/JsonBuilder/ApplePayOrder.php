<?php


namespace Sapient\AccessWorldpay\Model\JsonBuilder;

class ApplePayOrder
{
    const EXPONENT = 2;
    
    private $merchantCode;
    private $orderCode;
    private $orderDescription;
    private $currencyCode;
    private $amount;
  
    protected $shopperEmail;
    protected $protocolVersion;
    protected $signature;
    private $data;
    private $ephemeralPublicKey;
    protected $publicKeyHash;
    private $transactionId;
    
    public function build(
        $merchantCode,
        $orderCode,
        $orderDescription,
        $currencyCode,
        $amount,
        $shopperEmail,
        $protocolVersion,
        $signature,
        $data,
        $ephemeralPublicKey,
        $publicKeyHash,
        $transactionId
    ) {
        $this->merchantCode = $merchantCode;
        $this->orderCode = $orderCode;
        $this->orderDescription = $orderDescription;
        $this->currencyCode = $currencyCode;
        $this->amount = $amount;
         $this->shopperEmail = $shopperEmail;
         $this->protocolVersion = $protocolVersion;
         $this->signature = $signature;
         $this->data = $data;
         $this->ephemeralPublicKey = $ephemeralPublicKey;
         $this->publicKeyHash = $publicKeyHash;
         $this->transactionId = $transactionId;
                     
        $jsonData = $this->_addOrderElement();
        return $jsonData;
    }
    
    private function _addOrderElement()
    {
        $orderData = [];
       
        $orderData['transactionReference'] = $this->_addTransactionRef();
        $orderData['merchant'] = $this->_addMerchantInfo();
        $orderData['instruction'] = $this->_addInstructionInfo();
        
        return $orderData;
    }
    
    private function _addTransactionRef()
    {
        return $this->orderCode;
    }
    
    private function _addMerchantInfo()
    {
        $merchantData = ["entity" => $this->merchantCode['entityRef']];
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
        $paymentData = ["type" => "card/wallet+applepay",
                "walletToken" => $this->getApplePayToken()];
            return $paymentData;
    }
    
    private function getApplePayToken()
    {
        $appleToken = [
                "version"=>$this->protocolVersion,
                "data"=>$this->data,
                "signature"=>$this->signature,
                "header"=> $this->getPublicKeyHash()
                ];
        return $appleToken;
    }
    private function getPublicKeyHash()
    {
        return $applePublicHash = [
               "transactionId"=>$this->transactionId,
               "ephemeralPublicKey"=>$this->ephemeralPublicKey,
               "publicKeyHash"=>$this->publicKeyHash,
               ];
    }
    
    private function _amountAsInt($amount)
    {
        return round($amount, self::EXPONENT, PHP_ROUND_HALF_EVEN) * pow(10, self::EXPONENT);
    }
}
