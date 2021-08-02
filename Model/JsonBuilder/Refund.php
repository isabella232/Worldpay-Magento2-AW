<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\JsonBuilder;

/**
 * Build xml for Refund request
 */
class Refund
{

    const EXPONENT = 2;

    private $merchantCode;
    private $orderCode;
    private $currencyCode;
    private $amount;
    private $refundReference;
    private $requestType;

    /**
     * Build xml for processing Request
     * @param string $merchantCode
     * @param string $orderCode
     * @param string $currencyCode
     * @param float $amount
     * @param string $refundReference
     * @param string $requestType
     * @return SimpleXMLElement $xml
     */
    public function build($merchantCode, $orderCode, $currencyCode, $amount, $refundReference, $requestType)
    {
        $this->merchantCode = $merchantCode;
        $this->orderCode = $orderCode;
        $this->currencyCode = $currencyCode;
        $this->amount = $amount;
        $this->refundReference = $refundReference;
        $this->requestType = $requestType;

        $jsonData = $this->_addRefundElement();
        return json_encode($jsonData);
    }

   /**
    * Add tag refund to Json
    *
    * @return array $refundData
    */
    private function _addRefundElement()
    {
        if ($this->requestType == 'partial_refund') {
            $refundData = [];

            $refundData['value'] = $this->_addValue();
            $refundData['reference'] = 'Partial-refund-for-'.$this->orderCode;
        } else {
            $refundData = '';
        }
        return $refundData;
    }
    
    /**
     * Add amount to Json
     *
     */
    private function _addValue()
    {
        $data  = [];
        $data['amount'] = $this->_amountAsInt($this->amount);
        $data['currency'] = $this->currencyCode;
        return $data;
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
