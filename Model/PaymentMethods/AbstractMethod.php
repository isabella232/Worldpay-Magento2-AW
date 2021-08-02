<?php
namespace Sapient\AccessWorldpay\Model\PaymentMethods;

use Exception;
use Magento\Sales\Model\Order\Payment\Transaction;
use \Magento\Framework\Exception\LocalizedException;

/**
 * WorldPay Abstract class extended from Magento Abstract Payment class.
 */
abstract class AbstractMethod extends \Magento\Payment\Model\Method\AbstractMethod
{
    protected $_canVoid = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = true;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;

    /**
     * @var \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger
     */
    protected $_wplogger;
    /**
     * @var \Sapient\AccessWorldpay\Model\Authorisation\DirectService
     */
    protected $directservice;
    /**
     * @var array
     */
    protected static $paymentDetails;
    /**
     * @var \Sapient\AccessWorldpay\Model\AccessWorldpaymentFactory
     */
    protected $worldpaypayment;
    /**
     * @var \Sapient\AccessWorldpay\Helper\Data
     */
    protected $worlpayhelper;
    /**
     * @var array
     */
    protected $paymentdetailsdata;
    protected $_isInitializeNeeded = true;

    const REDIRECT_MODEL = 'redirect';
    const DIRECT_MODEL = 'direct';
    const WORLDPAY_CC_TYPE = 'worldpay_cc';
    const WORLDPAY_WALLETS_TYPE = 'worldpay_wallets';
    
    /**
     * Constructor
     *
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Payment\Model\Method\Logger $logger
     * @param \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger
     * @param \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
     * @param \Magento\Backend\Model\Session\Quote $adminsessionquote
     * @param \Sapient\AccessWorldpay\Model\Authorisation\DirectService $directservice
     * @param \Sapient\AccessWorldpay\Model\Authorisation\WalletService $walletService,
     * @param \Sapient\AccessWorldpay\Model\Authorisation\RedirectService $redirectservice
     * @param \Sapient\AccessWorldpay\Model\Authorisation\HostedPaymentPageService $hostedpaymentpageservice
     * @param \Sapient\AccessWorldpay\Model\Authorisation\WebSdkService $websdkservice
     * @param \Sapient\AccessWorldpay\Helper\Registry $registryhelper
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param \Sapient\AccessWorldpay\Helper\Data $worldpayhelper
     * @param \Sapient\AccessWorldpay\Model\AccessWorldpaymentFactory $worldpaypayment
     * @param \Sapient\AccessWorldpay\Model\AccessWorldpayment $worldpaypaymentmodel
     * @param \Magento\Framework\Pricing\Helper\Data $pricinghelper
     * @param \Sapient\AccessWorldpay\Model\Request\PaymentServiceRequest $paymentservicerequest
     * @param \Sapient\AccessWorldpay\Model\Utilities\PaymentMethods $paymentutils
     * @param \Magento\Backend\Model\Auth\Session $authSession
     * @param \Sapient\AccessWorldpay\Model\SavedTokenFactory $savedTokenFactory
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Backend\Model\Session\Quote $adminsessionquote,
        \Sapient\AccessWorldpay\Model\Authorisation\DirectService $directservice,
        \Sapient\AccessWorldpay\Model\Authorisation\WalletService $walletService,
        \Sapient\AccessWorldpay\Model\Authorisation\RedirectService $redirectservice,
        \Sapient\AccessWorldpay\Model\Authorisation\HostedPaymentPageService $hostedpaymentpageservice,
        \Sapient\AccessWorldpay\Model\Authorisation\WebSdkService $websdkservice,
        \Sapient\AccessWorldpay\Helper\Registry $registryhelper,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Sapient\AccessWorldpay\Helper\Data $worldpayhelper,
        \Sapient\AccessWorldpay\Model\AccessWorldpaymentFactory $worldpaypayment,
        \Sapient\AccessWorldpay\Model\AccessWorldpayment $worldpaypaymentmodel,
        \Magento\Framework\Pricing\Helper\Data $pricinghelper,
        \Sapient\AccessWorldpay\Model\Request\PaymentServiceRequest $paymentservicerequest,
        \Sapient\AccessWorldpay\Model\Utilities\PaymentMethods $paymentutils,
        \Sapient\AccessWorldpay\Model\Payment\PaymentTypes $paymenttypes,
        \Magento\Backend\Model\Auth\Session $authSession,
        \Sapient\AccessWorldpay\Model\SavedTokenFactory $savedTokenFactory,
        \Magento\Customer\Model\Session $customersession,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );
        $this->_wplogger = $wplogger;
        $this->directservice = $directservice;
        $this->paymentservicerequest = $paymentservicerequest;
        $this->redirectservice = $redirectservice;
        $this->directservice = $directservice;
        $this->walletService = $walletService;
        $this->hostedpaymentpageservice = $hostedpaymentpageservice;
        $this->websdkservice = $websdkservice;
        $this->quoteRepository = $quoteRepository;
        $this->registryhelper = $registryhelper;
        $this->urlbuilder = $urlBuilder;
        $this->worlpayhelper = $worldpayhelper;
        $this->worldpaypayment=$worldpaypayment;
        $this->worldpaypaymentmodel = $worldpaypaymentmodel;
        $this->pricinghelper = $pricinghelper;
        $this->paymentutils = $paymentutils;
        $this->adminsessionquote = $adminsessionquote;
        $this->authSession = $authSession;
        $this->paymenttypes = $paymenttypes;
        $this->customersession = $customersession;
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

    /**
     * Authorize payment abstract method
     *
     * @param \Magento\Framework\DataObject|InfoInterface $payment
     * @param float $amount
     */

    public function getOrderPlaceRedirectUrl()
    {
        return $this->registryhelper->getworldpayRedirectUrl();
    }

    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $mageOrder = $payment->getOrder();
        $quote = $this->quoteRepository->get($mageOrder->getQuoteId());
        if ($this->authSession->isLoggedIn()) {
            $adminquote = $this->adminsessionquote->getQuote();
            if (empty($quote->getReservedOrderId()) && !empty($adminquote->getReservedOrderId())) {
                $quote = $adminquote;
            }
        }
        /*Saved Card Token */
        $directFlow = isset(self::$paymentDetails['additional_data']['directSessionHref']) ||
                isset(self::$paymentDetails['additional_data']['sessionHref']);
        if (empty($directFlow) && !empty($this->getTokenUrl($payment->getAdditionalInformation()))) {
            $payment->setAdditionalInformation('method', $payment->getMethod());
            self::$paymentDetails['additional_data'] = $payment->getAdditionalInformation();
            self::$paymentDetails['method'] = $payment->getMethod();
            self::$paymentDetails['token_url'] = $this->getTokenUrl($payment->getAdditionalInformation());
        }
        /*GraphQl request*/
        if ($this->isGraphQlRequest($payment->getAdditionalInformation())) {
            $payment->setAdditionalInformation('method', $payment->getMethod());
            self::$paymentDetails['additional_data'] = $payment->getAdditionalInformation();
            $walletCCType = $this->getWalletCCType($payment);
            self::$paymentDetails['additional_data']['cc_type'] = ($walletCCType) ?
                $walletCCType : $this->getGraphQlCCType($payment);
            self::$paymentDetails['method'] = $payment->getMethod();
            self::$paymentDetails['is_graphql'] = 1;
            self::$paymentDetails['use_savedcard'] = $this->checkIfStoredSavedCard($payment->getAdditionalInformation());
        }
        
        $orderCode = $this->_generateOrderCode($quote);
        
        $this->paymentdetailsdata = self::$paymentDetails;
        try {
            $this->validatePaymentData(self::$paymentDetails);
//            if (self::$paymentDetails['method'] != self::WORLDPAY_WALLETS_TYPE) {
//                $this->_checkpaymentapplicable($quote);
//            }
            if (!$this->isGraphQlRequest($payment->getAdditionalInformation())) {
                $this->_checkShippingApplicable($quote);
            }
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
            $this->_wplogger->error($e->getMessage());
            $this->_wplogger->error('Authorising payment failed.');
            $errormessage = $this->worlpayhelper->updateErrorMessage($e->getMessage(), $quote->getReservedOrderId());
            $this->_wplogger->error($errormessage);
            throw new \Magento\Framework\Exception\LocalizedException(__($errormessage));
        }
    }
    public function validatePaymentData($paymentData)
    {
        $mode = $this->worlpayhelper->getCcIntegrationMode();
        $method = $paymentData['method'];
        $generalErrorMessage = __($this->worlpayhelper->getCreditCardSpecificException('CCAM15'));
        if ($method == self::WORLDPAY_CC_TYPE && empty($paymentData['token_url'])) {
            if (isset($paymentData['additional_data'])) {
                $data = $paymentData['additional_data'];
                if ($mode == 'redirect') {
                    if (!isset($data['cc_type'])) {
                        throw new \Magento\Framework\Exception\LocalizedException($generalErrorMessage, 1);
                    }
                    if (isset($data['cc_number']) && $data['cc_number'] != null) {
                        throw new \Magento\Framework\Exception\LocalizedException(__(
                            $this->worlpayhelper->getCreditCardSpecificException('CCAM16')
                        ), 1);
                    }
                } elseif ($mode == self::DIRECT_MODEL) {
                    if (!isset($data['cc_type'])) {
                        throw new \Magento\Framework\Exception\LocalizedException($generalErrorMessage, 1);
                    }
                }
            } else {
                throw new \Magento\Framework\Exception\LocalizedException(__(
                    $this->worlpayhelper->getCreditCardSpecificException('CCAM20')
                ), 1);
            }
        } elseif ($method == self::WORLDPAY_WALLETS_TYPE
                  && !isset($paymentData['additional_data']['cc_type'])) {
            throw new \Magento\Framework\Exception\LocalizedException($generalErrorMessage, 1);
        }
    }

    public function assignData(\Magento\Framework\DataObject $data)
    {
        parent::assignData($data);
        self::$paymentDetails = $data->getData();
        return $this;
    }

    /**
     * @return string
     */
    private function _generateOrderCode($quote)
    {
        return $quote->getReservedOrderId() . '-' . time();
    }

    /**
     * Save Risk gardian
     */
    private function _createWorldPayPayment(
        \Magento\Payment\Model\InfoInterface $payment,
        $orderCode,
        $storeId,
        $orderId,
        $interactionType = 'ECOM'
    ) {
        $paymentdetails = self::$paymentDetails;
        $integrationType = $this->worlpayhelper->getIntegrationModelByPaymentMethodCode(
            $payment->getMethod(),
            $storeId
        );
        $method = $payment->getMethod();
        
        $wpp = $this->worldpaypayment->create();
        $wpp->setData('order_id', $orderId);
        $wpp->setData(
            'payment_status',
            \Sapient\AccessWorldpay\Model\Payment\State::STATUS_SENT_FOR_AUTHORISATION
        );
        $wpp->setData('worldpay_order_id', $orderCode);
        $wpp->setData('store_id', $storeId);
        $wpp->setData('merchant_id', $this->worlpayhelper->getMerchantCode());
        if ($method == self::WORLDPAY_CC_TYPE && $this->worlpayhelper->is3DSecureEnabled()) {
            $wpp->setData('3d_verified', $this->worlpayhelper->is3DSecureEnabled());
        }
        if ($paymentdetails && !empty($paymentdetails['additional_data']['cc_type'])) {
            if ($paymentdetails['additional_data']['cc_type'] == 'savedcard') {
                $wpp->setData('payment_type', $this->_getSavedCardPaymentType(
                    $paymentdetails['additional_data']['tokenId']
                ));
            } else {
                $wpp->setData('payment_type', $paymentdetails['additional_data']['cc_type']);
            }
        } elseif (( isset($paymentdetails['additional_data']['token'])
                  && !empty($paymentdetails['additional_data']['token']))) {
            $wpp->setData('payment_type', 'TOKEN-SSL');
        } elseif ($paymentdetails['method'] == self::WORLDPAY_WALLETS_TYPE &&
                  $paymentdetails['additional_data']['cc_type'] == 'PAYWITHGOOGLE-SSL') {
            $wpp->setData('payment_type', $paymentdetails['additional_data']['cc_type']);
            $integrationType = 'direct';
        } else {
            $wpp->setData('payment_type', $this->_getpaymentType());
        }
        $wpp->setData('payment_model', $integrationType);
        $wpp->setData('interaction_type', $interactionType);
        $wpp->save();
    }
    
    public function _getSavedCardPaymentType($tokenId)
    {
        $tokenData = $this->worlpayhelper->getSelectedSavedCardTokenData($tokenId);
        return $tokenData[0]['card_brand'].'-SSL';
    }

    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        
        $mageOrder = $payment->getOrder();
        $baseTotal = $mageOrder->getGrandTotal();
        
        $quote = $this->quoteRepository->get($mageOrder->getQuoteId());
        $orderId = '';
        if ($quote->getReservedOrderId()) {
            $orderId = $quote->getReservedOrderId();
        } else {
            $orderId = $mageOrder->getIncrementId();
        }
        $worldPayPayment = $this->worldpaypaymentmodel->loadByPaymentId($orderId);
        
        $paymenttype = $worldPayPayment->getPaymentType();
        if ($this->paymentutils->checkCaptureRequest($payment->getMethod(), $paymenttype)) {
            if ($baseTotal != $amount) {
                $xml = $this->paymentservicerequest->partialCapture(
                    $payment->getOrder(),
                    $worldPayPayment,
                    $amount
                );
                $xml = new \SimpleXmlElement($xml);
                if ($xml && isset($xml->_links)) {
                    $xml->addChild('outcome', 'PARTIAL_CAPTURED');

                    $response = $xml->asXML();
                    $authorisationService = $this->getAuthorisationService($quote->getStoreId());
                    $authorisationService->partialCapturePayment(
                        $mageOrder,
                        $quote,
                        $response,
                        $payment
                    );
                }
                return $this;
            }
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
        if ($payment->getOrder()) {
            $mageOrder = $payment->getOrder();
            $baseTotal = $mageOrder->getGrandTotal();
            $quote = $this->quoteRepository->get($mageOrder->getQuoteId());
            $worldPayPayment = $this->worldpaypaymentmodel->loadByPaymentId($mageOrder->getIncrementId());
            $payment->getCreditmemo()->save();
            if ($baseTotal != $amount) {
                $xml = $this->paymentservicerequest->partialRefund(
                    $payment->getOrder(),
                    $worldPayPayment,
                    $payment->getMethod(),
                    $amount,
                    $payment->getCreditmemo()->getIncrementId()
                );
                $xml = new \SimpleXmlElement($xml);
                if ($xml && isset($xml->_links)) {
                    $xml->addChild('outcome', 'PARTIAL_REFUNDED');
                    $xml->addChild('reference', $payment->getCreditmemo()->getIncrementId());
                    
                    $response = $xml->asXML();
                    $authorisationService = $this->getAuthorisationService($quote->getStoreId());
                    $authorisationService->partialRefundPayment(
                        $mageOrder,
                        $quote,
                        $response,
                        $payment
                    );
                }
                return $this;
            }
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
        throw new \Magento\Framework\Exception\LocalizedException(
            __($this->worlpayhelper->getCreditCardSpecificException('CCAM17'))
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

    /**
     * @return bool
     */
    private function _isRefundAllowed($state)
    {
        $allowed = in_array(
            $state,
            [
                \Sapient\AccessWorldpay\Model\Payment\State::STATUS_CAPTURED,
                \Sapient\AccessWorldpay\Model\Payment\State::STATUS_PARTIAL_CAPTURED,
                \Sapient\AccessWorldpay\Model\Payment\State::STATUS_SETTLED,
                \Sapient\AccessWorldpay\Model\Payment\State::STATUS_SETTLED_BY_MERCHANT,
                \Sapient\AccessWorldpay\Model\Payment\State::STATUS_SENT_FOR_REFUND,
                \Sapient\AccessWorldpay\Model\Payment\State::STATUS_REFUNDED,
                \Sapient\AccessWorldpay\Model\Payment\State::STATUS_PARTIAL_REFUNDED,
                \Sapient\AccessWorldpay\Model\Payment\State::STATUS_REFUNDED_BY_MERCHANT,
                \Sapient\AccessWorldpay\Model\Payment\State::STATUS_REFUND_FAILED
            ]
        );
        return $allowed;
    }

    /**
     * check paymentmethod is available for billing country
     *
     * @param $quote
     * @return bool
     * @throw Exception
     */
    protected function _checkpaymentapplicable($quote)
    {
        $type = strtoupper($this->_getpaymentType());
        $billingaddress = $quote->getBillingAddress();
        $countryId = $billingaddress->getCountryId();
        $paymenttypes = json_decode($this->paymenttypes->getPaymentType($countryId));
        if (!in_array($type, $paymenttypes)) {
             throw new \Magento\Framework\Exception\LocalizedException(
                 'Payment Type not valid for the billing country'
             );
        }
    }

    /**
     * check paymentmethod is available for shipping country
     * No shipping country was mentioned in config it will be applicable for all shipping country
     *
     * @param $quote
     * @return bool
     * @throw Exception
     */
    protected function _checkShippingApplicable($quote)
    {
        $type = strtoupper($this->_getpaymentType());
        if ($type == 'KLARNA-SSL') {
            $shippingaddress = $quote->getShippingAddress();
            $countryId = $shippingaddress->getCountryId();
            $paymenttypes = json_decode($this->paymenttypes->getPaymentType($countryId));
            if (!in_array($type, $paymenttypes)) {
                 throw new \Magento\Framework\Exception\LocalizedException(
                     'Payment Type not valid for the shipping country'
                 );
            }
        }
    }

    /**
     * payment method
     *
     * @return bool
     */
    protected function _getpaymentType()
    {
            return  $this->paymentdetailsdata['additional_data']['cc_type'];
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

    /**
     * Get token Url
     */
    public function getTokenUrl($additionalInformation)
    {
        $savedToken = $this->savedTokenFactory->create();
        if (isset($additionalInformation['token']) && !empty($additionalInformation['token'])) {
            $token = $savedToken->loadByTokenCode($additionalInformation['token']);
            return $token ['token'];
        } elseif (isset($additionalInformation['tokenUrl']) && !empty($additionalInformation['tokenUrl'])) {
            return $additionalInformation['tokenUrl'];
        }
        return '';
    }

    /*
    * Check if graphql request
    */
    public function isGraphQlRequest($additionalInformation)
    {
        if (isset($additionalInformation['is_graphql'])) {
            return true;
        }
        return false;
    }

    /*
    * Get Wallet Type
    */
    public function getWalletCCType($payment)
    {
        $additional_data = $payment->getAdditionalInformation();
        if ($payment->getMethod()=='worldpay_wallets'
            && (isset($additional_data['googlepayToken'])
            && !empty($additional_data['googlepayToken']) )) {
            return 'PAYWITHGOOGLE-SSL';
        } elseif ($payment->getMethod()=='worldpay_wallets'
                  && (isset($additional_data['applepayToken'])
                  && !empty($additional_data['applepayToken']) )) {
            return 'APPLEPAY-SSL';
        }
    }
    
    /*
    * Get GraphQl CC Type
    */
    public function getGraphQlCCType($payment)
    {
        $additional_data = $payment->getAdditionalInformation();
        $cc_type = 'CARD-SSL';
        if ($payment->getMethod()=='worldpay_cc') {
            if (!empty($additional_data['cc_number'])) {
                $cc_type = $this->worlpayhelper->getCardType($additional_data['cc_number']);
            } elseif (!empty($additional_data['token']) || !empty($additional_data['tokenId'])) {
                $tokenId = !empty($additional_data['token']) ? $additional_data['token'] : $additional_data['tokenId'];
                $tokenData = $this->worlpayhelper->getSelectedSavedCardTokenData($tokenId);
                $cc_type = $tokenData[0]['card_brand'].'-SSL';
            } elseif (!empty($additional_data['tokenUrl'])) {
                $getTokenBrandDetails = $this->paymentservicerequest->getDetailedTokenForBrand(
                    $additional_data['tokenUrl'],
                    $this->worlpayhelper->getXmlUsername(),
                    $this->worlpayhelper->getXmlPassword()
                );
                $brandResponse = json_decode($getTokenBrandDetails, true);
                    $cc_type = $brandResponse['paymentInstrument']['brand'].'-SSL';
            }
        }
        
        return $cc_type;
    }
    
    public function checkIfStoredSavedCard($additionalInformation)
    {
        if (!empty($additionalInformation['tokenId'])) {
            return true;
        }
        return false;
    }
}
