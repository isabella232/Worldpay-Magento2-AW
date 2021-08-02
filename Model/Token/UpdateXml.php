<?php
/**
 * @copyright 2017 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Token;

use Sapient\AccessWorldpay\Model\SavedToken;

/**
 * read from WP's token update response
 */
class UpdateXml implements UpdateInterface
{
    /**
     * @var SimpleXMLElement
     */
    private $_xml;

    /**
     * @param SimpleXMLElement $xml
     */
    public function __construct(\SimpleXMLElement $xml)
    {
        $this->_xml = $xml;
    }

    /**
     * @return string
     */
    public function getTokenCode()
    {
        return (string)$this->_xml->reply->ok->updateTokenReceived['paymentTokenID'];
    }

    /**
     * @return bool
     */
    public function isSuccess()
    {
        return isset($this->_xml->reply->ok);
    }
}
