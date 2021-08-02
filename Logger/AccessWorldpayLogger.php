<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Logger;

class AccessWorldpayLogger extends \Monolog\Logger
{
    public function addRecord($level, $message, array $context = [])
    {
        $ObjectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $logEnabled = (bool) $ObjectManager->get(
            \Magento\Framework\App\Config\ScopeConfigInterface::class
        )
                ->getValue('worldpay/general_config/enable_logging');
        if ($logEnabled) {
            return parent::addRecord($level, $message, $context);
        }
    }
}
