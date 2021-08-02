<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use Sapient\AccessWorldpay\Model\PaymentMethods\CreditCards as WorldPayCCPayment;
use Magento\Checkout\Model\Cart;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Asset\Source;
use Sapient\AccessWorldpay\Model\SavedTokenFactory;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Configuration provider for worldpayment rendering payment page.
 */
class AccessWorldpayConfigProvider implements ConfigProviderInterface
{
    /**
     * @var string[]
     */
    protected $methodCodes = [
        'worldpay_cc',
        'worldpay_wallets'
    ];

    /**
     * @var array
     */
    private $icons = [];

    /**
     * @var \Magento\Payment\Model\Method\AbstractMethod[]
     */
    protected $methods = [];
    /**
     * @var \Sapient\AccessWorldpay\Model\PaymentMethods\Creditcards
     */
    protected $payment ;
    /**
     * @var \Sapient\AccessWorldpay\Helper\Data
     */
    protected $worldpayHelper;
    /**
     * @var Magento\Checkout\Model\Cart
     */
    protected $cart;
    /**
     * @var \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger
     */
    protected $wplogger;
    
    const CC_VAULT_CODE = "worldpay_cc_vault";
    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @param \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger
     * @param \Sapient\AccessWorldpay\Helper\Data $helper
     * @param PaymentHelper $paymentHelper
     * @param WorldPayCCPayment $payment
     * @param Cart $cart
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Backend\Model\Session\Quote $adminquotesession
     * @param \Magento\Backend\Model\Auth\Session $backendAuthSession
     * @param SavedTokenFactory $savedTokenFactory
     */
    public function __construct(
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger,
        \Sapient\AccessWorldpay\Helper\Data $helper,
        PaymentHelper $paymentHelper,
        WorldPayCCPayment $payment,
        Cart $cart,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Backend\Model\Session\Quote $adminquotesession,
        \Sapient\AccessWorldpay\Model\Utilities\PaymentMethods $paymentmethodutils,
        \Magento\Backend\Model\Auth\Session $backendAuthSession,
        Repository $assetRepo,
        RequestInterface $request,
        Source $assetSource,
        SerializerInterface $serializer,
        SavedTokenFactory $savedTokenFactory
    ) {

        $this->wplogger = $wplogger;
        foreach ($this->methodCodes as $code) {
            $this->methods[$code] = $paymentHelper->getMethodInstance($code);
        }
        $this->cart = $cart;
        $this->payment = $payment;
        $this->worldpayHelper = $helper;
        $this->customerSession = $customerSession;
        $this->backendAuthSession = $backendAuthSession;
        $this->adminquotesession = $adminquotesession;
        $this->paymentmethodutils = $paymentmethodutils;
        $this->assetRepo = $assetRepo;
        $this->request = $request;
        $this->assetSource = $assetSource;
        $this->serializer = $serializer;
        $this->savedTokenFactory = $savedTokenFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        $config = [];
        $params = ['_secure' => $this->request->isSecure()];
        foreach ($this->methodCodes as $code) {
            if ($this->methods[$code]->isAvailable()) {
                $config['payment']['total'] = $this->cart->getQuote()->getGrandTotal();
                $config['payment']['minimum_amount'] = $this->payment->getMinimumAmount();
                if ($code=='worldpay_cc') {
                    $config['payment']['ccform']["availableTypes"][$code] = $this->getCcTypes();
                } elseif ($code=='worldpay_wallets') {
                    $config['payment']['ccform']["availableTypes"][$code] = $this->getWalletsTypes($code);
                }
                $config['payment']['ccform']["hasVerification"][$code] = true;
                $config['payment']['ccform']["hasSsCardType"][$code] = false;
                $config['payment']['ccform']["months"][$code] = $this->getMonths();
                $config['payment']['ccform']["years"][$code] = $this->getYears();
                $config['payment']['ccform']["cvvImageUrl"][$code] = $this->assetRepo->
                        getUrlWithParams('Sapient_AccessWorldpay::images/cc/cvv.png', $params);
                $config['payment']['ccform']["ssStartYears"][$code] = $this->getStartYears();
                $config['payment']['ccform']['intigrationmode'] = $this->getIntigrationMode();
                $config['payment']['ccform']['myaccountexceptions'] = $this->getMyAccountException();
                $config['payment']['ccform']['creditcardexceptions'] = $this->getCreditCardException();
                $config['payment']['ccform']['generalexceptions'] = $this->getGeneralException();
                $config['payment']['ccform']['cctitle'] = $this->getCCtitle();
                $config['payment']['ccform']['isCvcRequired'] = $this->getCvcRequired();
                $config['payment']['ccform']['saveCardAllowed'] = $this->worldpayHelper->getSaveCard();
                $config['payment']['ccform']['tokenizationAllowed'] = $this->worldpayHelper->getTokenization();
                $config['payment']['ccform']['paymentMethodSelection'] = $this->getPaymentMethodSelection();
                $config['payment']['ccform']['paymentTypeCountries'] = $this->paymentmethodutils->
                        getPaymentTypeCountries();
                $config['payment']['ccform']['is3DSecureEnabled'] = $this->worldpayHelper->is3DSecureEnabled();
                $config['payment']['ccform']['savedCardList'] = $this->getSaveCardList();
                $config['payment']['ccform']['savedCardCount'] = count($this->getSaveCardList());
                $config['payment']['ccform']['savedCardEnabled'] = $this->getIsSaveCardAllowed();
                $config['payment']['ccform']['wpicons'] = $this->getIcons();
                $config['payment']['ccform']['websdk'] = $this->worldpayHelper->getWebSdkJsPath();
                $config['payment']['ccform']['walletstitle'] = $this->getWalletstitle();

                /*Labels*/
                $config['payment']['ccform']['myaccountlabels'] = $this->getMyAccountLabels();
                $config['payment']['ccform']['checkoutlabels'] = $this->getCheckoutLabels();
                $config['payment']['ccform']['adminlabels'] = $this->getAdminLabels();
                /* Merchant Identity for SessionHref call*/
                $config['payment']['ccform']['merchantIdentity'] = $this->worldpayHelper->getMerchantIdentity();
                /* Disclaimer  */
                $config['payment']['ccform']['disclaimerMessage'] = $this->worldpayHelper->getDisclaimerMessage();
                $config['payment']['ccform']['isDisclaimerMessageEnabled'] = $this->worldpayHelper
                        ->isDisclaimerMessageEnable();
                $config['payment']['ccform']['isDisclaimerMessageMandatory'] = $this->worldpayHelper
                        ->isDisclaimerMessageMandatory();
                /* GooglePay */
                $config['payment']['ccform']['isGooglePayEnable'] = $this->worldpayHelper->isGooglePayEnable();
                $config['payment']['ccform']['googlePaymentMethods'] = $this->worldpayHelper->googlePaymentMethods();
                $config['payment']['ccform']['googleAuthMethods'] = $this->worldpayHelper->googleAuthMethods();
                $config['payment']['ccform']['googleGatewayMerchantname'] = $this->worldpayHelper->
                        googleGatewayMerchantname();
                $config['payment']['ccform']['googleGatewayMerchantid'] = $this->worldpayHelper->
                        googleGatewayMerchantid();
                $config['payment']['ccform']['googleMerchantname'] = $this->worldpayHelper->googleMerchantname();
                $config['payment']['ccform']['googleMerchantid'] = $this->worldpayHelper->googleMerchantid();
                if ($this->worldpayHelper->getEnvironmentMode() == 'Live Mode') {
                    $config['payment']['general']['environmentMode'] = "PRODUCTION";
                } else {
                    $config['payment']['general']['environmentMode'] = "TEST";
                }
                
                /* Apple Configuration */
                $config['payment']['ccform']['appleMerchantid'] = $this->worldpayHelper->appleMerchantId();
            }
        }
        return $config;
    }

    public function getCreditCardException()
    {
        $ccdata= $this->unserializeValue($this->worldpayHelper->getCreditCardException());
        $result=[];
        $data=[];
        if (is_array($ccdata) || is_object($ccdata)) {
            foreach ($ccdata as $key => $value) {

                $result['exception_code']=$key;
                $result['exception_messages'] = $value['exception_messages'];
                $result['exception_module_messages'] = $value['exception_module_messages'];
                array_push($data, $result);
            
            }
        }
        return $data;
    }
    protected function unserializeValue($value)
    {
        if (is_string($value) && !empty($value)) {
            return $this->serializer->unserialize($value);
        } else {
            return [];
        }
    }
    public function getGeneralException()
    {
        $generaldata=$this->unserializeValue($this->worldpayHelper->getGeneralException());
        $result=[];
        $data=[];
        if (is_array($generaldata) || is_object($generaldata)) {
            foreach ($generaldata as $key => $value) {

                $result['exception_code']=$key;
                $result['exception_messages'] = $value['exception_messages'];
                $result['exception_module_messages'] = $value['exception_module_messages'];
                array_push($data, $result);
            
            }
        }
        return $data;
    }
    /**
     * @return String
     */
    public function getIntigrationMode()
    {
        return $this->worldpayHelper->getCcIntegrationMode();
    }

    /**
     * @return Array
     */
    public function getCcTypes($paymentconfig = "cc_config")
    {
        $options = $this->worldpayHelper->getCcTypes($paymentconfig);
        $isSavedCardEnabled = $this->getIsSaveCardAllowed();
        if ($isSavedCardEnabled && !empty($this->getSaveCardList())) {
            $options['savedcard'] = 'Use Saved Card';
        }
        return $options;
    }

    /**
     * @return boolean
     */
    public function getIsSaveCardAllowed()
    {
        if ($this->worldpayHelper->getSaveCard()) {
            return true;
        }
        return false;
    }

    public function getMonths()
    {
        return [
            "01" => "01 - January",
            "02" => "02 - February",
            "03" => "03 - March",
            "04" => "04 - April",
            "05" => "05 - May",
            "06" => "06 - June",
            "07" => "07 - July",
            "08" => "08 - August",
            "09" => "09 - September",
            "10"=> "10 - October",
            "11"=> "11 - November",
            "12"=> "12 - December"
        ];
    }

    /**
     * @return Array
     */
    public function getYears()
    {
        $years = [];
        for ($i=0; $i<=10; $i++) {
            $year = (string)($i+date('Y'));
            $years[$year] = $year;
        }
        return $years;
    }

    /**
     * @return Array
     */
    public function getStartYears()
    {
        $years = [];
        for ($i=5; $i>=0; $i--) {
            $year = (string)(date('Y')-$i);
            $years[$year] = $year;
        }
        return $years;
    }

    /**
     * @return String
     */
    public function getCCtitle()
    {
        return $this->worldpayHelper->getCcTitle();
    }

    /**
     * @return boolean
     */
    public function getCvcRequired()
    {
        return $this->worldpayHelper->isCcRequireCVC();
    }

    /**
     * @return string
     */
    public function getPaymentMethodSelection()
    {
        return $this->worldpayHelper->getPaymentMethodSelection();
    }

    public function getIcons()
    {
        if (!empty($this->icons)) {
            return $this->icons;
        }
        $ccTypes = $this->worldpayHelper->getCcTypes();
        $walletsTypes = $this->worldpayHelper->getWalletsTypes('worldpay_wallets');
        $allTypePayments = array_unique(array_merge($ccTypes, $walletsTypes));
        foreach (array_keys($allTypePayments) as $code) {
            if (!array_key_exists($code, $this->icons)) {
                $asset = $this->createAsset('Sapient_AccessWorldpay::images/cc/' . strtolower($code) . '.png');
                $placeholder = $this->assetSource->findSource($asset);
                if ($placeholder) {
                    list($width, $height) = getimagesize($asset->getSourceFile());
                    $this->icons[$code] = [
                        'url' => $asset->getUrl(),
                        'width' => $width,
                        'height' => $height
                    ];
                }
            }
        }
        return $this->icons;
    }
    /**
     * Create a file asset that's subject of fallback system
     *
     * @param string $fileId
     * @param array $params
     * @return \Magento\Framework\View\Asset\File
     */
    public function createAsset($fileId, array $params = [])
    {
        $params = array_merge(['_secure' => $this->request->isSecure()], $params);
        return $this->assetRepo->createAsset($fileId, $params);
    }
    public function getMyAccountException()
    {
        $generaldata=$this->unserializeValue($this->worldpayHelper->getMyAccountException());
        $result=[];
        $data=[];
        if (is_array($generaldata) || is_object($generaldata)) {
            foreach ($generaldata as $key => $value) {

                $result['exception_code']=$key;
                $result['exception_messages'] = $value['exception_messages'];
                $result['exception_module_messages'] = $value['exception_module_messages'];
                array_push($data, $result);
            
            }
        }
        return $data;
    }
    
    public function getMyAccountLabels()
    {
        $generaldata=$this->unserializeValue($this->worldpayHelper->getMyAccountLabels());
        $result=[];
        $data=[];
        if (is_array($generaldata) || is_object($generaldata)) {
            foreach ($generaldata as $key => $value) {

                $result['wpay_label_code']=$key;
                $result['wpay_label_desc'] = $value['wpay_label_desc'];
                $result['wpay_custom_label'] = $value['wpay_custom_label'];
                array_push($data, $result);
            
            }
        }
        return $data;
    }
    
    public function getCheckoutLabels()
    {
        $generaldata=$this->unserializeValue($this->worldpayHelper->getCheckoutLabels());
        $result=[];
        $data=[];
        if (is_array($generaldata) || is_object($generaldata)) {
            foreach ($generaldata as $key => $value) {

                $result['wpay_label_code']=$key;
                $result['wpay_label_desc'] = $value['wpay_label_desc'];
                $result['wpay_custom_label'] = $value['wpay_custom_label'];
                array_push($data, $result);
            
            }
        }
        return $data;
    }
    public function getAdminLabels()
    {
        $generaldata=$this->unserializeValue($this->worldpayHelper->getAdminLabels());
        $result=[];
        $data=[];
        if (is_array($generaldata) || is_object($generaldata)) {
            foreach ($generaldata as $key => $value) {

                $result['wpay_label_code']=$key;
                $result['wpay_label_desc'] = $value['wpay_label_desc'];
                $result['wpay_custom_label'] = $value['wpay_custom_label'];
                array_push($data, $result);
            
            }
        }
        return $data;
    }
    
    /**
     * Get Saved card List of customer
     */
    public function getSaveCardList()
    {
        $savedCardsList = [];
        $isSavedCardEnabled = $this->getIsSaveCardAllowed();
        if ($isSavedCardEnabled && $this->customerSession->isLoggedIn()) {
            $savedCardsList = $this->savedTokenFactory->create()->getCollection()
            ->addFieldToFilter('customer_id', $this->customerSession->getCustomerId())->getData();
        }
        return $savedCardsList;
    }
    
    public function getWalletsTypes($code)
    {
        return $this->worldpayHelper->getWalletsTypes($code);
    }
    
    public function getWalletstitle()
    {
        return $this->worldpayHelper->getWalletsTitle();
    }
}
