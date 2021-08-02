<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\ResourceModel\OmsData;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * AccessWorldpay payment collection
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
            \Sapient\AccessWorldpay\Model\OmsData::class,
            \Sapient\AccessWorldpay\Model\ResourceModel\OmsData::class
        );
    }
}
