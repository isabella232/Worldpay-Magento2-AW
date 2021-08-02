<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\ResourceModel\HistoryNotification;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * HistoryNotification collection
 */
class Collection extends AbstractCollection
{
    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(
            \Sapient\AccessWorldpay\Model\HistoryNotification::class,
            \Sapient\AccessWorldpay\Model\ResourceModel\HistoryNotification::class
        );
    }
}
