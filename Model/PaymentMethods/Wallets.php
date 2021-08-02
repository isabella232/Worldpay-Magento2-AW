<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\PaymentMethods;

/**
 * WorldPay Wallets class extended from WorldPay Abstract Payment Method.
 */
class Wallets extends \Sapient\AccessWorldpay\Model\PaymentMethods\AbstractMethod
{
    /**
     * Payment code
     * @var string
     */
    protected $_code = 'worldpay_wallets';
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
        $this->_wplogger->info('WorldPay Wallets Payment Method Executed:');
        parent::authorize($payment, $amount);
        return $this;
    }

    public function getAuthorisationService($storeId)
    {
        return $this->walletService;
    }

    /**
     * check if Wallets is enabled
     * @return bool
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        if ($this->worlpayhelper->isWorldPayEnable() && $this->worlpayhelper->isWalletsEnabled()) {
            return true;
        }
        return false;
    }

    public function getTitle()
    {
        return $this->worlpayhelper->getWalletsTitle();
    }
}
