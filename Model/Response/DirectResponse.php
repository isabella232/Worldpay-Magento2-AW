<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Response;

class DirectResponse extends \Sapient\AccessWorldpay\Model\Response\ResponseAbstract
{
    const PAYMENT_AUTHORISED = 'AUTHORISED';

    /**
     * @param SimpleXmlElement
     */
    protected $_responseXml;
}
