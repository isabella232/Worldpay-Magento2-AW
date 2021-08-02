<?php

namespace Sapient\AccessWorldpay\Model\PaymentMethods;

class PaymentOperations extends \Sapient\AccessWorldpay\Model\PaymentMethods\AbstractMethod
{

    public function updateOrderStatus($order)
    {
        if (!empty($order)) {
            $payment = $order->getPayment();
            $mageOrder = $order->getOrder();

            $worldPayPayment = $this->worldpaypaymentmodel->loadByPaymentId($mageOrder->getIncrementId());
            if (isset($worldPayPayment)) {
                $paymentStatus = preg_replace('/\s+/', '_', trim($worldPayPayment->getPaymentStatus()));
                $this->_wplogger->info('Updating order status');
                $this->updateOrder(strtoupper($paymentStatus), $mageOrder);
            } else {
                $this->_wplogger->info('No Payment');
                throw new \Magento\Framework\Exception\LocalizedException(__('No Payment'));
            }
        } else {
            $this->_wplogger->info('No Payment');
            throw new \Magento\Framework\Exception\LocalizedException(__('No Payment'));
        }
    }

    public function updateOrder($paymentStatus, $mageOrder)
    {
        switch ($paymentStatus) {
            case 'SENT_FOR_SETTLEMENT':
                $mageOrder->setState(\Magento\Sales\Model\Order::STATE_PROCESSING, true);
                $mageOrder->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);
                $mageOrder->save();
                break;
            case 'REFUNDED':
                $mageOrder->setState(\Magento\Sales\Model\Order::STATE_CLOSED, true);
                $mageOrder->setStatus(\Magento\Sales\Model\Order::STATE_CLOSED);
                $mageOrder->save();
                break;
            case 'REFUNDED_BY_MERCHANT':
                $mageOrder->setState(\Magento\Sales\Model\Order::STATE_CLOSED, true);
                $mageOrder->setStatus(\Magento\Sales\Model\Order::STATE_CLOSED);
                $mageOrder->save();
                break;
            case 'CANCELLED':
                $mageOrder->setState(\Magento\Sales\Model\Order::STATE_CANCELED, true);
                $mageOrder->setStatus(\Magento\Sales\Model\Order::STATE_CANCELED);
                $mageOrder->save();
                break;
            case 'VOIDED':
                $mageOrder->setState(\Magento\Sales\Model\Order::STATE_CLOSED, true);
                $mageOrder->setStatus(\Magento\Sales\Model\Order::STATE_CLOSED);
                $mageOrder->save();
                break;
            case 'REFUSED':
                $mageOrder->setState(\Magento\Sales\Model\Order::STATE_CANCELED, true);
                $mageOrder->setStatus(\Magento\Sales\Model\Order::STATE_CANCELED);
                $mageOrder->save();
                break;
            default:
                break;
        }
    }
}
