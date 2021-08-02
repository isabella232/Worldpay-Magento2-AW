<?php

namespace Sapient\AccessWorldpay\Helper;

use Sapient\AccessWorldpay\Model\Config\Source\HppIntegration as HPPI;
use Sapient\AccessWorldpay\Model\Config\Source\IntegrationMode as IM;
use Magento\Framework\Serialize\SerializerInterface;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    protected $_scopeConfig;
    protected $wplogger;
        
        /**
         * @var SerializerInterface
         */
    private $serializer;
    const MERCHANT_CONFIG = 'worldpay/merchant_config/';
    const INTEGRATION_MODE = 'worldpay/cc_config/integration_mode';

    public function __construct(
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Locale\CurrencyInterface $localeCurrency,
        \Sapient\AccessWorldpay\Model\Utilities\PaymentMethods $paymentlist,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Sapient\AccessWorldpay\Model\SavedTokenFactory $savecard,
        SerializerInterface $serializer,
        \Magento\Vault\Model\PaymentTokenManagement $paymentTokenManagement,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory
    ) {
        $this->_scopeConfig = $scopeConfig;
        $this->wplogger = $wplogger;
        $this->paymentlist = $paymentlist;
        $this->localecurrency = $localeCurrency;
        $this->_checkoutSession = $checkoutSession;
        $this->orderFactory = $orderFactory;
        $this->_savecard = $savecard;
        $this->serializer = $serializer;
        $this->paymentTokenManagement = $paymentTokenManagement;
        $this->orderCollectionFactory = $orderCollectionFactory;
    }
    public function isWorldPayEnable()
    {
        return (bool) $this->_scopeConfig->getValue(
            'worldpay/general_config/enable_worldpay',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getEnvironmentMode()
    {
        return $this->_scopeConfig->getValue(
            'worldpay/general_config/environment_mode',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
    public function getTestUrl()
    {
        return  $this->_scopeConfig->getValue(
            'worldpay/general_config/test_url',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
    public function getLiveUrl()
    {
        return  $this->_scopeConfig->getValue(
            'worldpay/general_config/live_url',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
    public function getMerchantCode()
    {
        return  $this->_scopeConfig->getValue(
            'worldpay/general_config/merchant_code',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
    public function getMerchantIdentity()
    {
        return  $this->_scopeConfig->getValue(
            'worldpay/general_config/merchant_identity',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
    public function getXmlUsername()
    {
        return  $this->_scopeConfig->getValue(
            'worldpay/general_config/xml_username',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
    public function getXmlPassword()
    {
        return  $this->_scopeConfig->getValue(
            'worldpay/general_config/xml_password',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
    public function getMerchantEntityReference()
    {
        return  $this->_scopeConfig->getValue(
            'worldpay/general_config/merchant_entity',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
    public function isMacEnabled()
    {
        return  $this->_scopeConfig->getValue(
            'worldpay/general_config/mac_enabled',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
    public function getMacSecret()
    {
        return  $this->_scopeConfig->getValue(
            'worldpay/general_config/mac_secret',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
    
    public function isLoggerEnable()
    {
        return (bool) $this->_scopeConfig->getValue(
            'worldpay/general_config/enable_logging',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
    
    public function isCreditCardEnabled()
    {
        return (bool) $this->_scopeConfig->getValue(
            'worldpay/cc_config/enabled',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
    public function getCcTitle()
    {
        return  $this->_scopeConfig->getValue(
            'worldpay/cc_config/title',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
    public function getCcTypes($paymentconfig = "cc_config")
    {
        $allCcMethods =  [
            'AMEX-SSL'=>'American Express','VISA-SSL'=>'Visa',
            'ECMC-SSL'=>'MasterCard','DISCOVER-SSL'=>'Discover',
            'DINERS-SSL'=>'Diners','MAESTRO-SSL'=>'Maestro','AIRPLUS-SSL'=>'AirPlus',
            'AURORE-SSL'=>'Aurore','CB-SSL'=>'Carte Bancaire',
            'CARTEBLEUE-SSL'=>'Carte Bleue','DANKORT-SSL'=>'Dankort',
            'GECAPITAL-SSL'=>'GE Capital','JCB-SSL'=>'Japanese Credit Bank',
            'LASER-SSL'=>'Laser Card','UATP-SSL'=>'UATP',
        ];
        $configMethods =   explode(',', $this->_scopeConfig->getValue(
            'worldpay/'.$paymentconfig.'/paymentmethods',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        ));
        $activeMethods = [];
        foreach ($configMethods as $method) {
            $activeMethods[$method] = $allCcMethods[$method];
        }
        return $activeMethods;
    }
    
    public function isCcRequireCVC()
    {
            return (bool) $this->_scopeConfig->getValue(
                'worldpay/cc_config/require_cvc',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            );
    }
    
    public function getSaveCard()
    {
        return (bool) $this->_scopeConfig->getValue(
            'worldpay/cc_config/saved_card',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
    
    public function getTokenization()
    {
        return (bool) true;
    }
    
    public function getCcIntegrationMode()
    {
        if ($this->isWebSdkIntegrationMode()) {
            return $this->_scopeConfig->getValue(
                'worldpay/cc_config/integration_mode',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            );
        } else {
            return IM::OPTION_VALUE_DIRECT;
        }
    }
    
    public function getPaymentMethodSelection()
    {
        return  $this->_scopeConfig->getValue(
            'worldpay/general_config/payment_method_selection',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function isWebSdkIntegrationMode()
    {
        return $this->_scopeConfig->
                getValue(
                    'worldpay/cc_config/integration_mode',
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                ) == IM::OPTION_VALUE_WEBSDK;
    }

    public function getIntegrationModelByPaymentMethodCode($paymentMethodCode, $storeId)
    {
        if ($this->isWebSdkIntegrationMode() && $paymentMethodCode == 'worldpay_cc') {
            return $this->_scopeConfig->getValue(
                'worldpay/cc_config/integration_mode',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            );
        } else {
            return IM::OPTION_VALUE_DIRECT;
        }
    }

    public function isIframeIntegration($storeId = null)
    {
        return $this->_scopeConfig->getValue(
            'worldpay/cc_config/hpp_integration',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        ) == HPPI::OPTION_VALUE_IFRAME;
    }

    public function getRedirectIntegrationMode($storeId = null)
    {
        return $this->_scopeConfig->getValue(
            'worldpay/cc_config/hpp_integration',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getCustomPaymentEnabled($storeId = null)
    {
        return $this->_scopeConfig->getValue(
            'worldpay/custom_paymentpages/enabled',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getInstallationId($storeId = null)
    {
        return $this->_scopeConfig->getValue(
            'worldpay/custom_paymentpages/installation_id',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getDynamicIntegrationType($paymentMethodCode)
    {
        return 'ECOMMERCE';
    }

    public function updateErrorMessage($message, $orderid)
    {
        $updatemessage = [
            'Payment REFUSED' => sprintf($this->getCreditCardSpecificexception('CCAM11'), $orderid),
            'Gateway error' => $this->getCreditCardSpecificexception('CCAM12')
            
        ];
        if (array_key_exists($message, $updatemessage)) {
            return $updatemessage[$message];
        }

        if (empty($message)) {

            $message = $this->getCreditCardSpecificexception('CCAM12');
        }
        return $message;
    }
    
    public function getAccessWorldpayAuthCookie()
    {
        return $this->_checkoutSession->getAccessWorldpayAuthCookie();
    }

    public function setAccessWorldpayAuthCookie($value)
    {
         return $this->_checkoutSession->setAccessWorldpayAuthCookie($value);
    }

    public function is3DSecureEnabled()
    {
        return (bool) $this->_scopeConfig->getValue(
            'worldpay/3ds_config/do_3Dsecure',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
    public function getChallengeWindowSize()
    {
            return $this->_scopeConfig->getValue(
                'worldpay/3ds_config/challenge_window_size',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            );
    }
    public function getDefaultCountry($storeId = null)
    {
        return $this->_scopeConfig->getValue(
            'shipping/origin/country_id',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getLocaleDefault($storeId = null)
    {
        return $this->_scopeConfig->getValue(
            'general/locale/code',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getCurrencySymbol($currencycode)
    {
        return $this->localecurrency->getCurrency($currencycode)->getSymbol();
    }

    public function getQuantityUnit($product)
    {
        return 'product';
    }

    public function checkStopAutoInvoice($code, $type)
    {
        return $this->paymentlist->checkStopAutoInvoice($code, $type);
    }

    public function isThreeDSRequest()
    {
        return $this->_checkoutSession->getIs3DSRequest();
    }
    
    public function getWebSdkJsPath()
    {
        $envMode = $this->getEnvironmentMode();
        if ($envMode == 'Test Mode') {
            return $this->_scopeConfig->getValue(
                'worldpay/cc_config/test_websdk_url',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            );
        } else {
            return $this->_scopeConfig->getValue(
                'worldpay/cc_config/live_websdk_url',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            );
        }
    }

    public function getOrderDescription()
    {
        return $this->_scopeConfig->getValue(
            'worldpay/general_config/order_description',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
    public function instantPurchaseEnabled()
    {
        $instantPurchaseEnabled = false;
        $caseSensitiveVal = trim($this->getCcIntegrationMode());
        $caseSensVal  = strtoupper($caseSensitiveVal);
        $isSavedCardEnabled = $this->getSaveCard();
        if ($isSavedCardEnabled) {
            $instantPurchaseEnabled = (bool) $this->_scopeConfig->
                getValue(
                    'worldpay/quick_checkout_config/instant_purchase',
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                );
        }
        return $instantPurchaseEnabled;
    }
        
    public function getOrderByOrderId($orderId)
    {
        return $this->orderFactory->create()->load($orderId);
    }
        
    public function getPaymentTitleForOrders(
        $order,
        $paymentCode,
        \Sapient\AccessWorldpay\Model\AccessWorldpaymentFactory $worldpaypayment
    ) {
        $order_id = $order->getIncrementId();
        $wpp = $worldpaypayment->create();
        $item = $wpp->loadByPaymentId($order_id);
        if ($paymentCode == 'worldpay_cc' || $paymentCode == 'worldpay_cc_vault') {
            return $this->getCcTitle() . "\n" . $item->getPaymentType();
        } elseif ($paymentCode == 'worldpay_apm') {
            return $this->getApmTitle() . "\n" . $item->getPaymentType();
        } elseif ($paymentCode == 'worldpay_wallets') {
            return $this->getWalletsTitle() . "\n" . $item->getPaymentType();
        } elseif ($paymentCode == 'worldpay_moto') {
            return $this->getMotoTitle() . "\n" . $item->getPaymentType();
        }
    }
    
    public function getCardType($cardNumber)
    {
        switch ($cardNumber) {
            case (preg_match('/^4/', $cardNumber) >= 1):
                return 'VISA-SSL';
            case (preg_match('/^(5[1-5][0-9]{0,2}|222[1-9]|22[3-9][0-9]|2[3-6][0-9]{0,2}|27[01][0-9]|2720)[0-9]{0,12}/', $cardNumber) >= 1):
                return 'ECMC-SSL';
            case (preg_match('/^3[47]/', $cardNumber) >= 1):
                return 'AMEX-SSL';
            case (preg_match('/^36/', $cardNumber) >= 1):
                return 'DINERS-SSL';
            case (preg_match('/^30[0-5]/', $cardNumber) >= 1):
                return 'DINERS-SSL';
            case (preg_match('/^6(?:011|5)/', $cardNumber) >= 1):
                return 'DISCOVER-SSL';
            case (preg_match('/^35(2[89]|[3-8][0-9])/', $cardNumber) >= 1):
                return 'JCB-SSL';
            case (preg_match('/^62|88/', $cardNumber) >= 1):
                return 'CHINAUNIONPAY-SSL';
            case (preg_match('/^([6011]{4})([0-9]{12})/', $cardNumber) >= 1):
                return 'DISCOVER-SSL';
            case (preg_match('/^(5[06789]|6)[0-9]{0,}/', $cardNumber) >= 1):
                return 'MAESTRO-SSL';
            default:
                break;
        }
    }
    
    /**
     * Get Disclaimer Message
     */
    public function getDisclaimerMessage()
    {
        return $this->_scopeConfig->getValue(
            'worldpay/cc_config/configure_disclaimer/stored_credentials_disclaimer_message',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * IsDiscliamer Setting Enabled
     */
    public function isDisclaimerMessageEnable()
    {
        return (bool) $this->_scopeConfig->getValue(
            'worldpay/cc_config/configure_disclaimer/stored_credentials_message_enable',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * IsDiscliamer Message Mandatory
     */
    public function isDisclaimerMessageMandatory()
    {
        return (bool) $this->_scopeConfig->getValue(
            'worldpay/cc_config/configure_disclaimer/stored_credentials_flag',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
    
    /**
     * Returns saved card token data
     *
     * @param $customerId , $tokenId
     *
     * @return saved card token value
     */
    public function getSelectedSavedCardTokenData($tokenId)
    {
        $selectedsavedCard = $this->_savecard->create()->getCollection()
                        ->addFieldToSelect(['token','cardonfile_auth_link','card_brand'])
                        ->addFieldToFilter('token_id', ['eq' => $tokenId]);
        
        $tokenData = $selectedsavedCard->getData();
        return $tokenData;
    }
    
    public function getMyAccountException()
    {
                return $this->_scopeConfig->getValue(
                    'worldpay_exceptions/my_account_alert_codes/response_codes',
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                );
    }
    
    public function getMyAccountSpecificexception($exceptioncode)
    {

        $ccdata=$this->serializer->unserialize($this->getMyAccountException());
        if (is_array($ccdata) || is_object($ccdata)) {
            foreach ($ccdata as $key => $valuepair) {
                if ($key == $exceptioncode) {
                    return $valuepair['exception_module_messages']?$valuepair['exception_module_messages']:
                        $valuepair['exception_messages'];
                }
            }
        }
    }
   
    public function getCreditCardException()
    {
                return $this->_scopeConfig->getValue(
                    'worldpay_exceptions/ccexceptions/cc_exception',
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                );
    }
    public function getCreditCardSpecificexception($exceptioncode)
    {

        $ccdata=$this->serializer->unserialize($this->getCreditCardException());
        if (is_array($ccdata) || is_object($ccdata)) {
            foreach ($ccdata as $key => $valuepair) {
                if ($key == $exceptioncode) {
                    return $valuepair['exception_module_messages']?$valuepair['exception_module_messages']:
                        $valuepair['exception_messages'];
                }
            }
        }
    }
    
    public function getGeneralException()
    {
               return $this->_scopeConfig->getValue('worldpay_exceptions/adminexceptions/'
                       . 'general_exception', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }
    
    public function getTokenFromVault($hash, $customerId)
    {
        $vaultData = $this->paymentTokenManagement->getByPublicHash($hash, $customerId);
        $tokenId = $vaultData['gateway_token'];
        $tokenData = $this->getSelectedSavedCardTokenData($tokenId);
        return $tokenData[0]['token'];
    }
    
    public function getMyAccountLabels()
    {
                return $this->_scopeConfig->getValue(
                    'worldpay_custom_labels/my_account_labels/my_account_label',
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                );
    }
    
    public function getAccountLabelbyCode($labelCode)
    {
        $aLabels = $this->serializer->unserialize($this->getMyAccountLabels());
        if (is_array($aLabels) || is_object($aLabels)) {
            foreach ($aLabels as $key => $valuepair) {
                if ($key == $labelCode) {
                    return $valuepair['wpay_custom_label']?$valuepair['wpay_custom_label']:
                    $valuepair['wpay_label_desc'];
                }
            }
        }
    }
    
    public function getCheckoutLabels()
    {
                return $this->_scopeConfig->getValue(
                    'worldpay_custom_labels/checkout_labels/checkout_label',
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                );
    }
    
    public function getCheckoutLabelbyCode($labelCode)
    {
        $aLabels = $this->serializer->unserialize($this->getCheckoutLabels());
        if (is_array($aLabels) || is_object($aLabels)) {
            foreach ($aLabels as $key => $valuepair) {
                if ($key == $labelCode) {
                    return $valuepair['wpay_custom_label']?$valuepair['wpay_custom_label']:
                    $valuepair['wpay_label_desc'];
                }
            }
        }
    }
    
    public function getAdminLabels()
    {
                return $this->_scopeConfig->getValue(
                    'worldpay_custom_labels/admin_labels/admin_label',
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                );
    }

    public function checkIfTokenExists($token)
    {
        $selectedsavedCard = $this->_savecard->create()->getCollection()
                        ->addFieldToSelect('token')
                        ->addFieldToFilter('token', ['eq' => $token]);
        
        $tokenData = $selectedsavedCard->getData();
        if (!empty($tokenData)) {
            return true;
        }
        return false;
    }

    public function isWalletsEnabled()
    {
        return  $this->_scopeConfig->getValue(
            'worldpay/wallets_config/enabled',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getWalletsTitle()
    {
        return  $this->_scopeConfig->getValue(
            'worldpay/wallets_config/title',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
    
    public function isGooglePayEnable()
    {
        return (bool) $this->_scopeConfig->getValue(
            'worldpay/wallets_config/google_pay_wallets_config/enabled',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
    
    public function googlePaymentMethods()
    {
        return $this->_scopeConfig->getValue(
            'worldpay/wallets_config/google_pay_wallets_config/paymentmethods',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
    
    public function googleAuthMethods()
    {
        return $this->_scopeConfig->getValue(
            'worldpay/wallets_config/google_pay_wallets_config/authmethods',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
    
    public function googleGatewayMerchantname()
    {
        return $this->_scopeConfig->getValue(
            'worldpay/wallets_config/google_pay_wallets_config/gateway_merchantname',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
    
    public function googleGatewayMerchantid()
    {
        return $this->_scopeConfig->getValue(
            'worldpay/wallets_config/google_pay_wallets_config/gateway_merchantid',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
    
    public function googleMerchantname()
    {
        return $this->_scopeConfig->getValue(
            'worldpay/wallets_config/google_pay_wallets_config/google_merchantname',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
    
    public function googleMerchantid()
    {
        return $this->_scopeConfig->getValue(
            'worldpay/wallets_config/google_pay_wallets_config/google_merchantid',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
    
    public function isApplePayEnable()
    {
        return (bool) $this->_scopeConfig->getValue(
            'worldpay/wallets_config/apple_pay_wallets_config/enabled',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function appleMerchantId()
    {
        return $this->_scopeConfig->getValue(
            'worldpay/wallets_config/apple_pay_wallets_config/merchant_name',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
    
    public function getWalletsTypes($code)
    {
        $activeMethods = [];
        if ($this->isGooglePayEnable()) {
            $activeMethods['PAYWITHGOOGLE-SSL'] = 'Google Pay';
        }
        if ($this->isApplePayEnable()) {
            $activeMethods['APPLEPAY-SSL'] = 'Apple Pay';
        }
        return $activeMethods;
    }

    /**
     * Get the first order details of customer by email
     *
     * @return array Order Item data
     */
    public function getOrderDetailsByEmailId($customerEmailId)
    {
        $itemData = $this->orderCollectionFactory->create()->addAttributeToFilter(
            'customer_email',
            $customerEmailId
        )->getFirstItem()->getData();
        return $itemData;
    }
    
    /**
     * Get the orders count of customer by email
     *
     * @return array List of order data
     */
    public function getOrdersCountByEmailId($customerEmailId)
    {
        $lastDayInterval = new \DateTime('yesterday');
        $lastYearInterval = new  \DateTime('-12 months');
        $lastSixMonthsInterval = new  \DateTime('-6 months');
        $ordersCount = [];
        
        $ordersCount['last_day_count'] = $this->getOrderIdsCount($customerEmailId, $lastDayInterval);
        $ordersCount['last_year_count'] = $this->getOrderIdsCount($customerEmailId, $lastYearInterval);
        $ordersCount['last_six_months_count'] = $this->getOrderIdsCount($customerEmailId, $lastSixMonthsInterval);
        return $ordersCount;
    }
    
    /**
     * Get the list of orders of customer by email
     *
     * @return array List of order IDs
     */
    public function getOrderIdsCount($customerEmailId, $interval)
    {
        $orders = $this->orderCollectionFactory->create();
        $orders->distinct(true);
        $orders->addFieldToSelect(['entity_id','increment_id','created_at']);
        $orders->addFieldToFilter('main_table.customer_email', $customerEmailId);
        $orders->addFieldToFilter('main_table.created_at', ['gteq' => $interval->format('Y-m-d H:i:s')]);
        $orders->join(['wp' => 'worldpay_payment'], 'wp.order_id=main_table.increment_id', ['payment_type']);
        $orders->join(['og' => 'sales_order_grid'], 'og.entity_id=main_table.entity_id', '');

        return count($orders);
    }

    /**
     * Returns cards count that are saved within 24 hrs
     *
     * @param $customerId
     *
     * @return array count of saved cards
     */
    public function getSavedCardsCount($customerId)
    {
        $now = new \DateTime();
        $lastDay = new  \DateInterval(sprintf('P%dD', 1));
        $savedCards = $this->_savecard->create()->getCollection()
                        ->addFieldToSelect(['id'])
                        ->addFieldToFilter('customer_id', ['eq' => $customerId])
                        ->addFieldToFilter('created_at', ['lteq' => $now->format('Y-m-d H:i:s')]);
        return count($savedCards->getData());
    }
    
    public function getOrderSyncInterval()
    {
        return $this->_scopeConfig->getValue(
            'worldpay/order_sync_status/order_sync_interval',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getSyncOrderStatus()
    {
        return $this->_scopeConfig->getValue(
            'worldpay/order_sync_status/order_status',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
}
