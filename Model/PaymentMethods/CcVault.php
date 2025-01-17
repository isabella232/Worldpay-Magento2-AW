<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Sapient\AccessWorldpay\Model\PaymentMethods;

use Sapient\AccessWorldpay\Logger\AccessWorldpayLogger;
use Exception;
use Magento\Sales\Model\Order\Payment\Transaction;
use Sapient\AccessWorldpay\Model\PaymentMethods\CreditCards;
use Sapient\AccessWorldpay\Model\SavedTokenFactory;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Payment\Gateway\Command;
use Magento\Payment\Gateway\Config\ValueHandlerPoolInterface;
use Magento\Payment\Gateway\ConfigFactoryInterface;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Sales\Api\Data\OrderPaymentExtensionInterfaceFactory;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order\Payment;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use Magento\Vault\Block\Form;
use Magento\Vault\Model\VaultPaymentInterface;

class CcVault extends \Magento\Vault\Model\Method\Vault
{
    protected $_code = 'worldpay_cc_vault';
    protected $_isGateway = true;
    protected $_canAuthorize = true;
    protected $_canUseInternal = false;
    protected $_canUseCheckout = true;
    protected $_canCapture = true;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $_canVoid = true;
    protected $_canCapturePartial = true;

    const DIRECT_MODEL = 'direct';
    protected static $paymentDetails;

    public function __construct(
        ConfigInterface $config,
        ConfigFactoryInterface $configFactory,
        ObjectManagerInterface $objectManager,
        MethodInterface $vaultProvider,
        ManagerInterface $eventManager,
        ValueHandlerPoolInterface $valueHandlerPool,
        Command\CommandManagerPoolInterface $commandManagerPool,
        PaymentTokenManagementInterface $tokenManagement,
        OrderPaymentExtensionInterfaceFactory $paymentExtensionFactory,
        $code,
        AccessWorldpayLogger $logger,
        \Sapient\AccessWorldpay\Model\Authorisation\VaultService $vaultService,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Sapient\AccessWorldpay\Helper\Data $worldpayhelper,
        \Sapient\AccessWorldpay\Model\AccessWorldpaymentFactory $worldpaypayment,
        \Sapient\AccessWorldpay\Model\AccessWorldpayment $worldpaypaymentmodel,
        \Sapient\AccessWorldpay\Model\Utilities\PaymentMethods $paymentutils,
        \Sapient\AccessWorldpay\Model\Request\PaymentServiceRequest $paymentservicerequest,
        \Sapient\AccessWorldpay\Model\Response\AdminhtmlResponse $adminhtmlresponse,
        SavedTokenFactory $savedTokenFactory,
        \Magento\Framework\Registry $registry
    ) {
        parent::__construct(
            $config,
            $configFactory,
            $objectManager,
            $vaultProvider,
            $eventManager,
            $valueHandlerPool,
            $commandManagerPool,
            $tokenManagement,
            $paymentExtensionFactory,
            $code
        );
        $this->logger = $logger;
        $this->vaultService = $vaultService;
        $this->quoteRepository = $quoteRepository;
        $this->worlpayhelper = $worldpayhelper;
        $this->worldpaypayment = $worldpaypayment;
        $this->worldpaypaymentmodel = $worldpaypaymentmodel;
        $this->paymentutils = $paymentutils;
        $this->paymentservicerequest = $paymentservicerequest;
        $this->adminhtmlresponse = $adminhtmlresponse;
        $this->registry = $registry;
        $this->savedTokenFactory = $savedTokenFactory;
    }

    public function initialize($paymentAction, $stateObject)
    {
        $payment = $this->getInfoInstance();
        $order = $payment->getOrder();
        $amount = $payment->formatAmount($order->getBaseTotalDue(), true);
        $payment->setBaseAmountAuthorized($amount);
        $payment->setAmountAuthorized($order->getTotalDue());
        $data = $payment->getMethodInstance()->getCode();
        $payment->getMethodInstance()->authorize($payment, $amount);
        $this->_addtransaction($payment, $amount);
        $stateObject->setStatus('pending');
        $stateObject->setState(\Magento\Sales\Model\Order::STATE_NEW);
        $stateObject->setIsNotified(false);
    }

    protected function _addtransaction($payment, $amount)
    {
        $order = $payment->getOrder();
        $formattedAmount = $order->getBaseCurrency()->formatTxt($amount);

        if ($payment->getIsTransactionPending()) {
            $message = 'Sent for authorization %1.';
        } else {
            $message = 'Authorized amount of %1.';
        }

        $message = __($message, $formattedAmount);

        $transaction = $payment->addTransaction(Transaction::TYPE_AUTH);
        $message = $payment->prependMessage($message);
        $payment->addTransactionCommentsToOrder($transaction, $message);
    }

    private function _generateOrderCode($quote)
    {
        return $quote->getReservedOrderId() . '-' . time();
    }

    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $this->logger->info('Vault Authorize function executed');
        $payment->setAdditionalInformation('method', $payment->getMethod());
        self::$paymentDetails = $payment->getAdditionalInformation();
        //$paymentDetails = self::$paymentDetails;
        self::$paymentDetails ['token_url'] = $this->getTokenUrl($payment->getAdditionalInformation());
        
        $mageOrder = $payment->getOrder();
        $quote = $this->quoteRepository->get($mageOrder->getQuoteId());
        
        try {
            $orderCode = $this->_generateOrderCode($quote);
            $this->_createWorldPayPayment($payment, $orderCode, $quote->getStoreId(), $quote->getReservedOrderId());
            $authorisationService = $this->getAuthorisationService($quote->getStoreId());
             
             $authorisationService->authorizePayment(
                 $mageOrder,
                 $quote,
                 $orderCode,
                 $quote->getStoreId(),
                 self::$paymentDetails,
                 $payment
             );
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            $this->logger->error('Authorising payment failed.');
            $errormessage = $this->worlpayhelper->updateErrorMessage($e->getMessage(), $quote->getReservedOrderId());
            $this->logger->error($errormessage);
            throw new \Magento\Framework\Exception\LocalizedException(
                __($errormessage)
            );
        }
         return $this;
    }

    public function getAuthorisationService($storeId)
    {
        return $this->vaultService;
    }

    private function _createWorldPayPayment(
        \Magento\Payment\Model\InfoInterface $payment,
        $orderCode,
        $storeId,
        $orderId,
        $interactionType = 'ECOM'
    ) {
        $paymentdetails = self::$paymentDetails;
        $integrationType =$this->worlpayhelper->getIntegrationModelByPaymentMethodCode($payment->getMethod(), $storeId);
        $wpp = $this->worldpaypayment->create();
        $wpp->setData('order_id', $orderId);
        $wpp->setData('payment_status', \Sapient\AccessWorldpay\Model\Payment\State::STATUS_SENT_FOR_AUTHORISATION);
        $wpp->setData('worldpay_order_id', $orderCode);
        $wpp->setData('store_id', $storeId);
        $wpp->setData('merchant_id', $this->worlpayhelper->getMerchantCode($paymentdetails['cc_type']));
        $wpp->setData('3d_verified', false);
        $wpp->setData('payment_model', $integrationType);
        $wpp->setData('payment_type', $paymentdetails['cc_type']);
        $wpp->setData('interaction_type', $interactionType);
        $wpp->save();
    }

    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $this->logger->info('Vault capture function executed');
        $mageOrder = $payment->getOrder();
        $quote = $this->quoteRepository->get($mageOrder->getQuoteId());
        $worldPayPayment = $this->worldpaypaymentmodel->loadByPaymentId($quote->getReservedOrderId());
        $paymenttype = $worldPayPayment->getPaymentType();
        
        if ($this->paymentutils->checkCaptureRequest($payment->getMethod(), $paymenttype)) {
            $xml = $this->paymentservicerequest->capture(
                $payment->getOrder(),
                $worldPayPayment,
                $payment->getMethod()
            );
            
             $xml = new \SimpleXmlElement($xml);
            if ($xml && isset($xml->_links)) {
                $xml->addChild('outcome', 'CAPTURED');
                
                $response = $xml->asXML();
                $authorisationService = $this->getAuthorisationService($quote->getStoreId());
                $authorisationService->capturePayment(
                    $mageOrder,
                    $quote,
                    $response,
                    $payment
                );
            }
        }
        $payment->setTransactionId(time());
        return $this;
    }

    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $this->logger->info('Vault refund payment model function executed');
        if ($payment->getOrder()) {
            $order = $payment->getOrder();
            $mageOrder = $payment->getOrder();
            $quote = $this->quoteRepository->get($mageOrder->getQuoteId());
            $worldPayPayment = $this->worldpaypaymentmodel->loadByPaymentId($mageOrder->getIncrementId());
            $payment->getCreditmemo()->save();
            $xml = $this->paymentservicerequest->refund(
                $payment->getOrder(),
                $worldPayPayment,
                $payment->getMethod(),
                $amount,
                $payment->getCreditmemo()->getIncrementId()
            );

            $xml = new \SimpleXmlElement($xml);
            if ($xml && isset($xml->_links)) {
                $xml->addChild('outcome', 'REFUNDED');
                $xml->addChild('reference', $payment->getCreditmemo()->getIncrementId());
                
                $response = $xml->asXML();
                $authorisationService = $this->getAuthorisationService($quote->getStoreId());
                $authorisationService->refundPayment(
                    $mageOrder,
                    $quote,
                    $response,
                    $payment
                );
                return $this;
            }

        }
        $gatewayError = 'No matching order found in WorldPay to refund';
        $errorMsg = 'Please visit your WorldPay merchant interface and refund the order manually.';
        throw new \Magento\Framework\Exception\LocalizedException(
            __($gatewayError.' '.$errorMsg)
        );
    }
    public function canRefund()
    {
        $payment = $this->getInfoInstance()->getOrder()->getPayment();
        $mageOrder = $payment->getOrder();
        $quote = $this->quoteRepository->get($mageOrder->getQuoteId());
        $wpPayment = $this->worldpaypaymentmodel->loadByPaymentId($quote->getReservedOrderId());

        if ($wpPayment) {
            return $this->_isRefundAllowed($wpPayment->getPaymentStatus());
        }

        return parent::canRefund();
    }
    private function _isRefundAllowed($state)
    {
        $allowed = in_array(
            $state,
            [
                \Sapient\AccessWorldpay\Model\Payment\State::STATUS_CAPTURED,
                \Sapient\AccessWorldpay\Model\Payment\State::STATUS_SETTLED,
                \Sapient\AccessWorldpay\Model\Payment\State::STATUS_SETTLED_BY_MERCHANT,
                \Sapient\AccessWorldpay\Model\Payment\State::STATUS_SENT_FOR_REFUND,
                \Sapient\AccessWorldpay\Model\Payment\State::STATUS_REFUNDED,
                \Sapient\AccessWorldpay\Model\Payment\State::STATUS_REFUNDED_BY_MERCHANT,
                \Sapient\AccessWorldpay\Model\Payment\State::STATUS_REFUND_FAILED
            ]
        );
        return $allowed;
    }

    public function getTitle()
    {
        if ($order = $this->registry->registry('current_order')) {
            return $this->worlpayhelper->getPaymentTitleForOrders($order, $this->_code, $this->worldpaypayment);
        } elseif ($invoice = $this->registry->registry('current_invoice')) {
            $order = $this->worlpayhelper->getOrderByOrderId($invoice->getOrderId());
            return $this->worlpayhelper->getPaymentTitleForOrders($order, $this->_code, $this->worldpaypayment);
        } elseif ($creditMemo = $this->registry->registry('current_creditmemo')) {
            $order = $this->worlpayhelper->getOrderByOrderId($creditMemo->getOrderId());
            return $this->worlpayhelper->getPaymentTitleForOrders($order, $this->_code, $this->worldpaypayment);
        } else {
            return $this->worlpayhelper->getCcTitle();
        }
    }
    
    public function getTokenUrl($additionalInformation)
    {
        $savedToken = $this->savedTokenFactory->create();
        $token = $savedToken->loadByTokenCode($additionalInformation['token']);
        return $token ['token'];
    }
}
