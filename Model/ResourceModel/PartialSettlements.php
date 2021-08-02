<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\ResourceModel;

/**
 * AccessWorldpayment resource
 */
class PartialSettlements extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    
    /**
     * Model initialization
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('awp_oms_partial_settlements', 'entity_id');
    }

    /**
     * Load worldpayment detail by worldpay_order_id
     *
     * @param string $order_id
     * @return int $id
     */
    public function loadByAccessWorldpayOrderCode($order_id)
    {
        $table = $this->getMainTable();
        $where = $this->getConnection()->quoteInto("awp_order_code = ?", $order_id);
        $sql = $this->getConnection()->select()->from($table, ['entity_id'])->where($where);
        $id = $this->getConnection()->fetchOne($sql);
        return $id;
    }
    
    /**
     * Load worldpayment detail by order_increment_id
     *
     * @param string $order_increment_id
     * @return int $id
     */
    public function loadByOrderIncrementId($order_increment_id)
    {
        $table = $this->getMainTable();
        $where = $this->getConnection()->quoteInto("order_increment_id = ?", $order_increment_id);
        $sql = $this->getConnection()->select()->from($table, ['entity_id'])->where($where);
        $id = $this->getConnection()->fetchOne($sql);
        return $id;
    }
}
