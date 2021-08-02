<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model;

/**
 * Resource Model
 */
class HistoryNotification extends \Magento\Framework\Model\AbstractModel
{
    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(\Sapient\AccessWorldpay\Model\ResourceModel\HistoryNotification::class);
    }
}
