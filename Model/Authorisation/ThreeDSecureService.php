<?php

namespace Sapient\AccessWorldpay\Model\Authorisation;

use Exception;

class ThreeDSecureService extends \Magento\Framework\DataObject
{
    const CART_URL = 'checkout/cart';
    
    public function __construct(
        \Sapient\AccessWorldpay\Model\Request\PaymentServiceRequest $paymentservicerequest,
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger,
        \Sapient\AccessWorldpay\Model\Response\DirectResponse $directResponse,
        \Sapient\AccessWorldpay\Model\Payment\Service $paymentservice,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Sapient\AccessWorldpay\Model\Order\Service $orderservice,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Sapient\AccessWorldpay\Model\Payment\UpdateAccessWorldpaymentFactory $updateWorldPayPayment,
        \Magento\Customer\Model\Session $customerSession,
        //\Sapient\AccessWorldpay\Model\Token\WorldpayToken $worldpaytoken,
        \Sapient\AccessWorldpay\Helper\Data $worldpayHelper
    ) {
        $this->paymentservicerequest = $paymentservicerequest;
        $this->wplogger = $wplogger;
        $this->directResponse = $directResponse;
        $this->paymentservice = $paymentservice;
        $this->checkoutSession = $checkoutSession;
        $this->urlBuilders    = $urlBuilder;
        $this->orderservice = $orderservice;
        $this->_messageManager = $messageManager;
        $this->updateWorldPayPayment = $updateWorldPayPayment;
        $this->customerSession = $customerSession;
        //$this->worldpaytoken = $worldpaytoken;
        $this->worldpayHelper = $worldpayHelper;
    }
    
    public function authenticate3Ddata($authenticationurl, $directOrderParams)
    {
        $response = $this->paymentservicerequest->authenticate3Ddata($authenticationurl, $directOrderParams);
        $this->checkoutSession->set3DschallengeData($response);
        return $response;
    }
}
