<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\PaymentMethods;

/**
 * WorldPay CreditCards class extended from WorldPay Abstract Payment Method.
 */
class CreditCards extends \Sapient\AccessWorldpay\Model\PaymentMethods\AbstractMethod
{
    /**
     * Payment code
     * @var string
     */
    protected $_code = 'worldpay_cc';
    protected $_isGateway = true;
    protected $_canAuthorize = true;
    protected $_canUseInternal = false;
    protected $_canUseCheckout = true;

    /**
     * Authorize payment abstract method
     *
     * @param \Magento\Framework\DataObject|InfoInterface $payment
     * @param float $amount
     * @return $this
     */
    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $this->_wplogger->info('WorldPay Payment CreditCards Authorise Method Executed:');
        parent::authorize($payment, $amount);
        return $this;
    }

    public function getAuthorisationService($storeId)
    {
         $checkoutpaymentdata = $this->paymentdetailsdata;
         $integrationModel = $this->worlpayhelper->getCcIntegrationMode();
        if ($integrationModel == 'web_sdk') {
            //return $integrationModel === 'web_sdk';
            return $this->websdkservice;
        } elseif ($this->_isRedirectIntegrationModeEnabled($storeId)) {
            if ($this->_isEmbeddedIntegrationModeEnabled($storeId)) {
                return $this->hostedpaymentpageservice;
            }

            return $this->redirectservice;
        }
        return $this->directservice;
    }

    /**
     * @param int storeId
     * @return bool
     */
    private function _isRedirectIntegrationModeEnabled($storeId)
    {
        $integrationModel = $this->worlpayhelper->getCcIntegrationMode($storeId);

        return $integrationModel === 'redirect';
    }

    /**
     * @return bool
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {

        if ($this->worlpayhelper->isWorldPayEnable() && $this->worlpayhelper->isCreditCardEnabled()) {
            return true;
        }
        return false;
    }

    /**
     * @param int storeId
     * @return bool
     */
    private function _isEmbeddedIntegrationModeEnabled($storeId)
    {
        return $this->worlpayhelper->isIframeIntegration($storeId);
    }

    public function getTitle()
    {
        return $this->worlpayhelper->getCcTitle();
    }
    
    private function _isWebSdkIntegrationModeEnabled()
    {
        $integrationModel = $this->worlpayhelper->getIntegrationModelByPaymentMethodCode('worldpay_cc');
        if ($integrationModel == 'web_sdk') {
            return $integrationModel === 'web_sdk';
        }
    }
}
