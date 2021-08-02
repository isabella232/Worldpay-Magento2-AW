<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Payment;

class Service
{

    /** @var \Sapient\AccessWorldpay\Model\Request\PaymentServiceRequest */
    protected $_paymentServiceRequest;
    /** @var \Sapient\AccessWorldpay\Model\Payment\Update\Factory */
    protected $_paymentUpdateFactory;
    /** @var \Sapient\AccessWorldpay\Model\Request\PaymentServiceRequest */
    protected $_redirectResponse;
    protected $_paymentModel;
    protected $_helper;
    /**
     * Constructor
     * @param \Sapient\AccessWorldpay\Model\Payment\State $paymentState
     * @param \Sapient\AccessWorldpay\Model\Payment\WorldPayPayment $worldPayPayment
     * @param \Sapient\AccessWorldpay\Helper\Data $configHelper
     */
    public function __construct(
        \Sapient\AccessWorldpay\Model\Payment\Update\Factory $paymentupdatefactory,
        \Sapient\AccessWorldpay\Model\Request\PaymentServiceRequest $paymentservicerequest,
        \Sapient\AccessWorldpay\Model\Response\DirectResponse $directResponse,
        \Sapient\AccessWorldpay\Model\AccessWorldpayment $worldpayPayment
    ) {
        $this->paymentupdatefactory = $paymentupdatefactory;
        $this->paymentservicerequest = $paymentservicerequest;
        $this->worldpayPayment = $worldpayPayment;
        $this->directResponse = $directResponse;
    }

    public function createPaymentUpdateFromWorldPayXml($xml)
    {
        if (isset($xml->errorName) && $xml->errorName=='entityIsNotConfigured') {
            throw new \Magento\Framework\Exception\LocalizedException(__($xml->message));
        }
        return $this->_getPaymentUpdateFactory()
            ->create(new \Sapient\AccessWorldpay\Model\Payment\StateJson($xml));
    }

    protected function _getPaymentUpdateFactory()
    {
        if ($this->_paymentUpdateFactory === null) {
            $this->_paymentUpdateFactory = $this->paymentupdatefactory;
        }

        return $this->_paymentUpdateFactory;
    }

    public function createPaymentUpdateFromWorldPayResponse(\Sapient\AccessWorldpay\Model\Payment\State $state)
    {
        return $this->_getPaymentUpdateFactory()
            ->create($state);
    }
  
    public function setGlobalPaymentByPaymentUpdate($paymentUpdate)
    {
        $this->worldpayPayment->loadByAccessWorldpayOrderId($paymentUpdate->getTargetOrderCode());
    }
    
    public function getPaymentUpdateXmlForOrder(\Sapient\AccessWorldpay\Model\Order $order)
    {
        $worldPayPayment = $order->getWorldPayPayment();

        if (!$worldPayPayment) {
            return false;
        }
        $orderid = $order->getOrder()->getIncrementId();
        $xml = $this->paymentservicerequest->eventInquiry($orderid);
        $response = $this->directResponse->setResponse($xml);
        return $response->getXml();
    }
}
