<?php


namespace Sapient\AccessWorldpay\Model\JsonBuilder;

class ThreeDsVerifiaction
{
    private $orderCode;
    private $challengeReference;
    
    public function build(
        $orderCode,
        $challengeReference
    ) {
        $this->orderCode = $orderCode;
        $this->challengeReference = $challengeReference;
        $jsonData = $this->_addOrderElement();
        return json_encode($jsonData);
    }
    
    private function _addOrderElement()
    {
        $orderData = [];
        $orderData['transactionReference'] = $this->_addTransactionRef();
        $orderData['merchant'] = $this->_addMerchantInfo();
        $orderData['challenge'] =  $this->_addChallenge();
        
        return $orderData;
    }
    
    private function _addTransactionRef()
    {
        return $this->orderCode['orderCode'];
    }
    
    private function _addMerchantInfo()
    {
        $merchantData = ["entity" => $this->orderCode['paymentDetails']['entityRef']];
        return $merchantData;
    }
    
    private function _addChallenge()
    {
        $challengeData = ["reference" => $this->challengeReference];
        return $challengeData;
    }
}
