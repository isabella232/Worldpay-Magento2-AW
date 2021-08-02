<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\ResourceModel;

/**
 * AccessWorldpayment resource
 */
class AccessWorldpayment extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    
    /**
     * Model initialization
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('worldpay_payment', 'id');
    }

    /**
     * Load worldpayment detail by order_id
     *
     * @param int $orderId
     * @return int $id
     */
    public function loadByPaymentId($orderId)
    {
        $table = $this->getMainTable();
        $where = $this->getConnection()->quoteInto("order_id = ?", $orderId);
        $sql = $this->getConnection()->select()->from($table, ['id'])->where($where);
        $id = $this->getConnection()->fetchOne($sql);
        return $id;
    }

    /**
     * Load worldpayment detail by worldpay_order_id
     *
     * @param string $order_id
     * @return int $id
     */
    public function loadByAccessWorldpayOrderId($order_id)
    {
        $table = $this->getMainTable();
        $where = $this->getConnection()->quoteInto("worldpay_order_id = ?", $order_id);
        $sql = $this->getConnection()->select()->from($table, ['id'])->where($where);
        $id = $this->getConnection()->fetchOne($sql);
        return $id;
    }
}
