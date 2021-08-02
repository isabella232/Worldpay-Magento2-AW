<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * HistoryNotification resource
 */
class HistoryNotification extends AbstractDb
{
    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('worldpay_notification_history', 'id');
    }
}
