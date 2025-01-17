<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Logger;

use Monolog\Logger;

class AccessWorldpayHandler extends \Magento\Framework\Logger\Handler\Base
{
    /**
     * Logging level
     * @var int
     */
    protected $loggerType = Logger::INFO;

    /**
     * File name
     * @var string
     */
    protected $fileName = '/var/log/worldpay.log';
}
