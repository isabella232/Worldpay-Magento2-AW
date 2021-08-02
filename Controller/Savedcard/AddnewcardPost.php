<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Controller\Savedcard;

use Magento\Framework\App\Action\Context;
use Magento\Customer\Model\Session;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Store\Model\StoreManagerInterface;
use Exception;

/**
 * Controller for adding saved card
 *
 */
class AddnewcardPost extends \Magento\Customer\Controller\AbstractAccount
{
    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;
    protected $checkoutSession;
    const THIS_TRANSACTION = 'thisTransaction';
    const LESS_THAN_THIRTY_DAYS = 'lessThanThirtyDays';
    const THIRTY_TO_SIXTY_DAYS = 'thirtyToSixtyDays';
    const MORE_THAN_SIXTY_DAYS = 'moreThanSixtyDays';
    const DURING_TRANSACTION = 'duringTransaction';
    const CREATED_DURING_TRANSACTION = 'createdDuringTransaction';
    const CHANGED_DURING_TRANSACTION = 'changedDuringTransaction';
    const NO_ACCOUNT = 'noAccount';
    const NO_CHANGE = 'noChange';
    
    /**
     * @var Magento\Framework\Data\Form\FormKey\Validator
     */
    protected $formKeyValidator;
    
    public function __construct(
        Context $context,
        Session $customerSession,
        Validator $formKeyValidator,
        StoreManagerInterface $storeManager,
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger,
        \Magento\Customer\Api\AddressRepositoryInterface $addressRepository,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Sapient\AccessWorldpay\Helper\Data $worldpayHelper,
        \Sapient\AccessWorldpay\Model\Request\PaymentServiceRequest $paymentservicerequest,
        \Magento\Framework\Session\SessionManagerInterface $session,
        \Sapient\AccessWorldpay\Model\Response\DirectResponse $directResponse,
        \Sapient\AccessWorldpay\Model\Payment\UpdateAccessWorldpayment $updateAccessWorldpayment,
        \Sapient\AccessWorldpay\Model\Payment\Service $paymentservice,
        \Magento\Integration\Model\Oauth\TokenFactory $tokenModelFactory,
        \Magento\SalesSequence\Model\Manager $sequenceManager,
        \Sapient\AccessWorldpay\Helper\Registry $registryhelper,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Sapient\AccessWorldpay\Model\Token\WorldpayToken $worldpayToken,
        \Magento\Framework\Message\ManagerInterface $messageManager
    ) {
        parent::__construct($context);
        $this->_storeManager = $storeManager;
        $this->formKeyValidator = $formKeyValidator;
        $this->customerSession = $customerSession;
        $this->wplogger = $wplogger;
        $this->addressRepository = $addressRepository;
        $this->scopeConfig = $scopeConfig;
        $this->worldpayHelper = $worldpayHelper;
        $this->_paymentservicerequest = $paymentservicerequest;
        $this->session = $session;
        $this->directResponse = $directResponse;
        $this->updateAccessWorldpayment = $updateAccessWorldpayment;
        $this->paymentservice = $paymentservice;
        $this->_tokenModelFactory = $tokenModelFactory;
        $this->sequenceManager = $sequenceManager;
        $this->registryhelper = $registryhelper;
        $this->checkoutSession = $checkoutSession;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->_worldpayToken = $worldpayToken;
        $this->_messageManager = $messageManager;
    }
    
    public function execute()
    {
        if (!$this->customerSession->isLoggedIn()) {
            $this->_redirect('customer/account/login');
            return;
        }

        if ($this->getRequest()->isPost()) {
            try {
                $customer = $this->customerSession->getCustomer();
                $store = $this->_storeManager->getStore();
                $paymentType = "worldpay_cc";
                $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
                $billingaddress = $this->addressRepository->getById($customer->getData('default_billing'));
                $merchantCode = $this->scopeConfig->
                        getValue('worldpay/general_config/merchant_code', $storeScope);
                $currencyCode = $store->getCurrentCurrencyCode();

                $billingadd = $this->getAddress($billingaddress, $customer);
                $fullRequest = json_decode($this->getRequest()->getContent());
                $payment = [
                    'cardNumber' => $fullRequest->payment->cardNumber,
                    'paymentType' => $fullRequest->payment->paymentType,
                    'cardHolderName' => $fullRequest->payment->cardHolderName,
                    'expiryMonth' => $fullRequest->payment->expiryMonth,
                    'expiryYear' => $fullRequest->payment->expiryYear
                ];
                if (isset($fullRequest->payment->cvc) && !$fullRequest->payment->cvc == ''
                    && !empty($fullRequest->payment->cvc)) {
                    $payment['cvc'] = $fullRequest->payment->cvc;
                }
                $payment['sessionId'] = $this->session->getSessionId();
                $payment['token_type'] = $this->worldpayHelper->getTokenization();
                $orderParams = [];
                $orderParams['merchantCode'] = $merchantCode;
                $orderParams['currencyCode'] = $currencyCode;
                $orderParams['paymentDetails'] = $payment;
                $orderParams['cardAddress'] = $billingadd;
                $orderParams['billingAddress'] = $billingadd;
                $orderParams['method'] = $paymentType;
                
                $verifiedTokenRequest = $this->createVerifiedTokenRequest($orderParams);
                //send verified token request to Access Worldpay
                $verifiedTokenResponse = $this->_paymentservicerequest->_getVerifiedToken(
                    $verifiedTokenRequest,
                    $this->worldpayHelper->getXmlUsername(),
                    $this->worldpayHelper->getXmlPassword()
                );
                $verifiedTokenResponseToArray = json_decode($verifiedTokenResponse, true);
                
                if (isset($verifiedTokenResponseToArray['outcome'])
                        && $verifiedTokenResponseToArray['outcome'] == 'verified') {
                    $verifiedTokenhref = $verifiedTokenResponseToArray['_links']['tokens:token']['href'];
                    /*Conflict Resolution*/
                    if ($verifiedTokenResponseToArray['response_code']==409
                        && !empty($verifiedTokenResponseToArray['_links']['tokens:conflicts']['href'])) {
                        $conflictResponse = $this->_paymentservicerequest->resolveConflict(
                            $this->worldpayHelper->getXmlUsername(),
                            $this->worldpayHelper->getXmlPassword(),
                            $verifiedTokenResponseToArray['_links']['tokens:conflicts']['href']
                        );
                    }
                    //get detailed token
                    $getTokenDetails = $this->_paymentservicerequest->_getDetailedVerifiedToken(
                        $verifiedTokenhref,
                        $this->worldpayHelper->getXmlUsername(),
                        $this->worldpayHelper->getXmlPassword()
                    );
                    $tokenDetailResponseToArray = json_decode($getTokenDetails, true);
                    //make a call to getBrand Details,content-type is different
                    $getTokenBrandDetails = $this->_paymentservicerequest->getDetailedTokenForBrand(
                        $tokenDetailResponseToArray['_links']['tokens:token']['href'],
                        $this->worldpayHelper->getXmlUsername(),
                        $this->worldpayHelper->getXmlPassword()
                    );
                    $brandResponse = json_decode($getTokenBrandDetails, true);
                    $tokenDetailResponseToArray['card_brand'] =
                            $brandResponse['paymentInstrument']['brand'];
                    $tokenDetailResponseToArray['customer_id'] = $customer->getId();
                    // Set disclaimer flag in customer token session
                    $tokenDetailResponseToArray['disclaimer'] =
                            isset($fullRequest->payment->disclaimerFlag)?
                            $fullRequest->payment->disclaimerFlag:0;
                    //save detailed token in session for later use
                    $this->customerSession->setIsSavedCardRequested(true);
                    $this->customerSession->setDetailedToken($tokenDetailResponseToArray);
                    $isSaved = $this->updateAccessWorldpayment->saveVerifiedTokenForMyAccount(
                        $tokenDetailResponseToArray
                    );
                    
                    $this->checkIfTokenSaved($isSaved);
                } else {
                    $this->messageManager->getMessages(true);
                    $this->messageManager->addError($this->worldpayHelper->
                            getMyAccountSpecificexception('MCAM14'));
                    return $this->resultJsonFactory->create()->setData(['success' => false]);
                }
            } catch (Exception $e) {
                $this->wplogger->error($e->getMessage());
                $this->messageManager->getMessages(true);
                if ($e->getMessage() === 'Unique constraint violation found') {
                    $this->messageManager->addError(__(
                        $this->worldpayHelper->getMyAccountSpecificexception('MCAM13')
                    ));
                } else {
                    $this->messageManager->addException($e, __('Error: ') . $e->getMessage());
                }
                return $this->resultJsonFactory->create()->setData(['success' => false]);
            }
        }
    }

    /**
     * Frame Shipping Address
     * @return array
     */
    private function getAddress($addressDetails, $customer)
    {
        $address = [
            'firstName' => $addressDetails->getFirstName(),
            'lastName' => $addressDetails->getLastName(),
            'street' => $addressDetails->getStreet()[0],
            'postalCode' => $addressDetails->getPostcode(),
            'city' => $addressDetails->getCity(),
            'state' => $customer->getDefaultBillingAddress()->getRegion(),
            'countryCode' => $addressDetails->getCountryId()
        ];
        return $address;
    }

    /**
     * Retrieve store Id
     *
     * @return int
     */
    public function getStoreId()
    {
        return $this->_storeManager->getStore()->getId();
    }

    protected function createVerifiedTokenRequest($orderParams)
    {
        $instruction = [];
        $instruction['paymentInstrument'] = ["type" => "card/plain",
            "cardHolderName" => strtolower($orderParams['paymentDetails']['cardHolderName']),
            //Using lowercase for cardholder name to minimize the conflict
            "cardNumber" => $orderParams['paymentDetails']['cardNumber'],
            "cardExpiryDate" => ["month" => (int) $orderParams['paymentDetails']['expiryMonth'],
                "year" => (int) $orderParams['paymentDetails']['expiryYear']]];
        
        if (isset($orderParams['paymentDetails']['cvc'])
            && !$orderParams['paymentDetails']['cvc'] == '') {
            $instruction['paymentInstrument']['cvc'] = $orderParams['paymentDetails']['cvc'];
        }

        $instruction['paymentInstrument']['billingAddress'] =
            ["address1" => $orderParams['billingAddress']['firstName'],
            "address2" => $orderParams['billingAddress']['lastName'],
            "address3" => $orderParams['billingAddress']['street'],
            "postalCode" => $orderParams['billingAddress']['postalCode'],
            "city" => $orderParams['billingAddress']['city'],
            "state" => $orderParams['billingAddress']['state'],
            "countryCode" => $orderParams['billingAddress']['countryCode']];
        $instruction['merchant'] = ["entity" => $this->worldpayHelper->getMerchantEntityReference()];
        $instruction['verificationCurrency'] = ($orderParams['currencyCode']);
        if ($this->customerSession->isLoggedIn()) {
            $shoperId = $this->customerSession->getCustomer()->getId().'_'.date("m").date("Y");
            $instruction['namespace'] = $shoperId;
        } else {
            $instruction['namespace'] = strtotime("now");
        }
        return json_encode($instruction);
    }
    
    public function checkIfTokenSaved($isSaved)
    {
        if ($isSaved) {
            $this->messageManager->addSuccess('The card has been added');
            return $this->resultJsonFactory->create()->setData(['success' => true]);
        } else {
            $this->messageManager->getMessages(true);
            if (isset($tokenDetailResponseToArray['tokenId'])) {
                //Manage Exceed Update Limit
                if (isset($conflictResponse) && (isset($conflictResponse['nameConflict'])
                    && isset($conflictResponse['dateConflict'])
                    && $conflictResponse['nameConflict']==429)) {
                    $this->_messageManager->addError(__(
                        $this->worldpayHelper->getCreditCardSpecificException('CCAM22')
                    ));
                    return $this->resultJsonFactory->create()->setData(['success' => true]);
                } elseif (isset($conflictResponse) && (isset($conflictResponse['nameConflict'])
                          && !isset($conflictResponse['dateConflict'])
                          && $conflictResponse['nameConflict']==429)) {
                    $this->_messageManager->addError(__(
                        $this->worldpayHelper->getCreditCardSpecificException('CCAM22')
                    ));
                    return $this->resultJsonFactory->create()->setData(['success' => true]);
                } elseif (isset($conflictResponse) && (!isset($conflictResponse['nameConflict'])
                          && isset($conflictResponse['dateConflict'])
                          && $conflictResponse['dateConflict']==429)) {
                    $this->_messageManager->addError(__(
                        $this->worldpayHelper->getCreditCardSpecificException('CCAM22')
                    ));
                    return $this->resultJsonFactory->create()->setData(['success' => true]);
                }
                //update Token
                $this->wplogger->info('Token already exists ..........................................');
                $this->_worldpayToken->updateTokenByCustomer(
                    $this->updateAccessWorldpayment->_loadTokenModel(
                        $tokenDetailResponseToArray
                    ),
                    $this->customerSession->getCustomer()
                );
                //update vault token
                $this->updateAccessWorldpayment->_applyVaultTokenUpdate($tokenDetailResponseToArray);
                $this->_messageManager->addNotice(__(
                    $this->worldpayHelper->getMyAccountSpecificexception('MCAM11')
                ));
                return $this->resultJsonFactory->create()->setData(['success' => true]);
            }
        }
    }
}
