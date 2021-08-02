<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Response;

class AdminhtmlResponse extends \Sapient\AccessWorldpay\Model\Response\ResponseAbstract
{
    public function parseCaptureResponse($xml)
    {
        $document = new \SimpleXmlElement($xml);
        return $document;
    }
    
    public function parseRefundResponse($xml)
    {
        $document = new \SimpleXmlElement($xml);
        return $document;
    }

    public function parseInquiryResponse($xml)
    {
        $document = new \SimpleXmlElement($xml);
        return $document;
    }
}
