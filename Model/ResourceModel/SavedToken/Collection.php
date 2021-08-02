<?php
/**
 * @copyright 2017 Sapient
 */
namespace Sapient\AccessWorldpay\Model\ResourceModel\SavedToken;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * SavedToken collection
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
            \Sapient\AccessWorldpay\Model\SavedToken::class,
            \Sapient\AccessWorldpay\Model\ResourceModel\SavedToken::class
        );
    }
}
