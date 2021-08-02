<?php

namespace Sapient\AccessWorldpay\Model\Authorisation;

use Exception;
use Magento\Framework\Exception\LocalizedException;

/**
 * Description of WalletsService
 *
 * @author sucm
 */

class WalletService extends \Magento\Framework\DataObject
{

    protected $checkoutSession;
    protected $updateWorldPayPayment;

    public function __construct(
        \Sapient\AccessWorldpay\Model\Mapping\Service $mappingservice,
        \Sapient\AccessWorldpay\Model\Request\PaymentServiceRequest $paymentservicerequest,
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger,
        \Sapient\AccessWorldpay\Model\Response\DirectResponse $directResponse,
        \Sapient\AccessWorldpay\Model\Payment\UpdateAccessWorldpaymentFactory $updateWorldPayPayment,
        \Sapient\AccessWorldpay\Model\Payment\Service $paymentservice,
        \Sapient\AccessWorldpay\Helper\Registry $registryhelper,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Sapient\AccessWorldpay\Helper\Data $worldpayHelper
    ) {
        $this->mappingservice = $mappingservice;
        $this->paymentservicerequest = $paymentservicerequest;
        $this->wplogger = $wplogger;
        $this->directResponse = $directResponse;
        $this->paymentservice = $paymentservice;
        $this->checkoutSession = $checkoutSession;
        $this->updateWorldPayPayment = $updateWorldPayPayment;
        $this->worldpayHelper = $worldpayHelper;
        $this->registryhelper = $registryhelper;
        $this->urlBuilders    = $urlBuilder;
    }

    /**
     * handles provides authorization data for redirect
     * It initiates a  XML request to WorldPay and registers worldpayRedirectUrl
     */

    public function authorizePayment(
        $mageOrder,
        $quote,
        $orderCode,
        $orderStoreId,
        $paymentDetails,
        $payment
    ) {
        if ($paymentDetails['additional_data']['cc_type'] == 'APPLEPAY-SSL') {
            $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/worldpay.log');
            $logger = new \Zend\Log\Logger();
            $logger->addWriter($writer);
            $logger->info('got it appple pay from walletservice.php');
            $applePayOrderParams = $this->mappingservice->collectWalletOrderParameters(
                $orderCode,
                $quote,
                $orderStoreId,
                $paymentDetails
            );
            $response = $this->paymentservicerequest->applePayOrder($applePayOrderParams);
            $directResponse = $this->directResponse->setResponse($response);
            $this->updateWorldPayPayment->create()->updateAccessWorldpayPayment(
                $orderStoreId,
                $orderCode,
                $directResponse,
                $payment
            );
            $this->_applyPaymentUpdate($directResponse, $payment);
        } elseif ($paymentDetails['additional_data']['cc_type'] == 'PAYWITHGOOGLE-SSL') {
            $walletOrderParams = $this->mappingservice->collectWalletOrderParameters(
                $orderCode,
                $quote,
                $orderStoreId,
                $paymentDetails
            );
            $response = $this->paymentservicerequest->walletsOrder($walletOrderParams);
            $directResponse = $this->directResponse->setResponse($response);
            // Normal order goes here.
            $this->updateWorldPayPayment->create()->updateAccessWorldpayPayment(
                $orderStoreId,
                $orderCode,
                $directResponse,
                $payment
            );
            $this->_applyPaymentUpdate($directResponse, $payment);
        }
    }

    private function _applyPaymentUpdate(
        \Sapient\AccessWorldpay\Model\Response\DirectResponse $directResponse,
        $payment
    ) {
        $paymentUpdate = $this->paymentservice->
                createPaymentUpdateFromWorldPayXml($directResponse->getXml());
        $paymentUpdate->apply($payment);
        $this->_abortIfPaymentError($paymentUpdate);
    }

    private function _abortIfPaymentError($paymentUpdate)
    {
        if ($paymentUpdate instanceof \Sapient\AccessWorldpay\Model\Payment\Update\Refused) {
             throw new \Magento\Framework\Exception\LocalizedException(
                 sprintf('Payment REFUSED')
             );
        }
        if ($paymentUpdate instanceof \Sapient\AccessWorldpay\Model\Payment\Update\Cancelled) {
            throw new \Magento\Framework\Exception\LocalizedException(
                sprintf('Payment CANCELLED')
            );
        }
        if ($paymentUpdate instanceof \Sapient\AccessWorldpay\Model\Payment\Update\Error) {
            throw new \Magento\Framework\Exception\LocalizedException(
                sprintf('Payment ERROR')
            );
        }
    }

    public function capturePayment(
        $mageOrder,
        $quote,
        $response,
        $payment
    ) {
        $directResponse = $this->directResponse->setResponse($response);
        $this->updateWorldPayPayment->create()->updatePaymentSettlement($response);
        $this->_applyPaymentUpdate($directResponse, $payment);
    }
    
    public function partialCapturePayment(
        $mageOrder,
        $quote,
        $response,
        $payment
    ) {
        $directResponse = $this->directResponse->setResponse($response);
        $this->updateWorldPayPayment->create()->updatePaymentSettlement($response);
        // Normal order goes here.
        $this->_applyPaymentUpdate($directResponse, $payment);
    }
    
    public function refundPayment(
        $mageOrder,
        $quote,
        $response,
        $payment
    ) {
        $directResponse = $this->directResponse->setResponse($response);
        $this->_applyPaymentUpdate($directResponse, $payment);
    }
    
    public function partialRefundPayment(
        $mageOrder,
        $quote,
        $response,
        $payment
    ) {
        $directResponse = $this->directResponse->setResponse($response);
        $this->_applyPaymentUpdate($directResponse, $payment);
    }
}
