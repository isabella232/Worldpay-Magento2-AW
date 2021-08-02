<?php

namespace Sapient\AccessWorldpay\Model\JsonBuilder;

/**
 * Build json for RedirectOrder request
 */
class WalletOrder
{
    const EXPONENT = 2;
    
    private $merchantCode;
    private $orderCode;
    private $orderDescription;
    private $currencyCode;
    private $amount;
    private $paymentType;
    private $exponent;
    private $sessionId;
    private $cusDetails;
    private $shopperIpAddress;
    private $paymentDetails;
    private $shippingAddress;
    protected $acceptHeader;
    protected $userAgentHeader;

    /**
     * Build xml for processing Request
     *
     * @param string $merchantCode
     * @param string $orderCode
     * @param string $orderDescription
     * @param string $currencyCode
     * @param float $amount
     * @param string $paymentType
     * @return SimpleXMLElement $xml
     */
    public function build(
        $merchantCode,
        $orderCode,
        $orderDescription,
        $currencyCode,
        $amount,
        $paymentType,
        $shopperEmail,
        $acceptHeader,
        $userAgentHeader,
        $protocolVersion,
        $signature,
        $signedMessage,
        $shippingAddress,
        $billingAddress,
        $cusDetails,
        $shopperIpAddress,
        $paymentDetails
    ) {
        $this->merchantCode = $merchantCode;
        $this->orderCode = $orderCode;
        $this->orderDescription = $orderDescription;
        $this->currencyCode = $currencyCode;
        $this->amount = $amount;
        $this->paymentType = $paymentType;
        $this->shopperEmail = $shopperEmail;
        $this->acceptHeader = $acceptHeader;
        $this->userAgentHeader = $userAgentHeader;
        $this->protocolVersion = $protocolVersion;
        $this->signature = $signature;
        $this->signedMessage = $signedMessage;
        $this->shippingAddress = $shippingAddress;
        $this->billingAddress = $billingAddress;
        $this->cusDetails = $cusDetails;
        $this->shopperIpAddress = $shopperIpAddress;
        $this->paymentDetails = $paymentDetails;
        $this->exponent = self::EXPONENT;
        $jsonData = $this->_addOrderElement();
        return json_encode($jsonData);
    }
    
    /**
     * Add order tag to json
     *
     */
    private function _addOrderElement()
    {
        $orderData = [];
        $orderData['transactionReference'] = $this->_addTransactionRef();
        $orderData['merchant'] = $this->_addMerchantInfo();
        $orderData['instruction'] = $this->_addInstructionInfo();
        $orderData['shopperLanguageCode'] = "en";
        return $orderData;
    }

    /**
     * @param float $amount
     * @return int
     */
    private function _amountAsInt($amount)
    {
        return round($amount, $this->exponent, PHP_ROUND_HALF_EVEN) * pow(10, $this->exponent);
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
        $paymentData = ["type" => "card/wallet+goolepay",
                "walletToken" => json_encode($this->getGoolepayToken())];
        return $paymentData;
    }

    private function getGoolepayToken()
    {
        return ["protocolVersion"=>$this->protocolVersion,
                "signature"=>$this->signature,
                "signedMessage"=>$this->signedMessage
                ];
    }
}
