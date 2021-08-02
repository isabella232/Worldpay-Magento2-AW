<?php
namespace Sapient\AccessWorldpay\Model\Request;

/**
 * @copyright 2020 Sapient
 */
use Exception;
use Sapient\AccessWorldpay\Model\SavedToken;

/**
 * Prepare the request and process them
 */
class PaymentServiceRequest extends \Magento\Framework\DataObject
{
    /**
     * @var \Sapient\AccessWorldpay\Model\Request $request
     */
    protected $_request;
    
    public $threeDsValidResponse = ['AUTHENTICATED','BYPASSED','UNAVAILABLE','NOTENROLLED'];

    /**
     * Constructor
     *
     * @param \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger
     * @param \Sapient\AccessWorldpay\Model\Request $request
     * @param \Sapient\AccessWorldpay\Helper\Data $worldpayhelper
     */
    public function __construct(
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger,
        \Sapient\AccessWorldpay\Model\Request $request,
        \Sapient\AccessWorldpay\Helper\Data $worldpayhelper,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Sapient\AccessWorldpay\Model\ResourceModel\OmsData\CollectionFactory $omsCollectionFactory,
        \Sapient\AccessWorldpay\Model\Payment\UpdateAccessWorldpayment $updateAccessWorldpayment
    ) {
        $this->_wplogger = $wplogger;
        $this->_request = $request;
        $this->worldpayhelper = $worldpayhelper;
        $this->scopeConfig = $scopeConfig;
        $this->customerSession = $customerSession;
        $this->quoteFactory = $quoteFactory;
        $this->omsCollectionFactory = $omsCollectionFactory;
        $this->updateAccessWorldpayment = $updateAccessWorldpayment;
    }

    /**
     * Get URL of merchant site based on environment mode
     */
    private function _getUrl()
    {
        if ($this->worldpayhelper->getEnvironmentMode()=='Live Mode') {
            return $this->worldpayhelper->getLiveUrl();
        }
        return $this->worldpayhelper->getTestUrl();
    }
   
    /**
     * Send direct order XML to AccessWorldpay server
     *
     * @param array $directOrderParams
     * @return mixed
     */
    public function order($directOrderParams)
    {
        if (!isset($directOrderParams['threeDSecureConfig'])) {
            $directOrderParams['threeDSecureConfig'] = '';
        }
        $this->_wplogger->info('########## Submitting direct order request. OrderCode: '
                               . $directOrderParams['orderCode'] . ' ##########');
        $requestConfiguration = [
            'threeDSecureConfig' => $directOrderParams['threeDSecureConfig']
        ];

        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        $verifiedToken = $this->worldpayhelper->getTokenization();
        $directOrderParams['verifiedToken'] = '';
        
        if (isset($directOrderParams['paymentDetails']['tokenId'])
            && isset($directOrderParams['paymentDetails']['cvc'])) {
            return $this->_sendDirectSavedCardRequest(
                $directOrderParams,
                $requestConfiguration
            );
        } elseif ($verifiedToken
                && $directOrderParams['paymentDetails']['paymentType'] != 'TOKEN-SSL') {
            //check verified token is enabled
            //build json request for verified token
            $verifiedTokenRequest = $this->_createVerifiedTokenReq($directOrderParams);

            //send verified token request to Access Worldpay
            $verifiedTokenResponse = $this->_getVerifiedToken(
                $verifiedTokenRequest,
                $this->worldpayhelper->getXmlUsername(),
                $this->worldpayhelper->getXmlPassword()
            );
            $responseToArray = json_decode($verifiedTokenResponse, true);

            if (isset($responseToArray['outcome']) && $responseToArray['outcome'] == 'verified') {

                $directOrderParams['verifiedToken'] = $responseToArray['_links']['tokens:token']['href'];
                /*Conflict Resolution*/
                if ($responseToArray['response_code']==409
                    && !empty($responseToArray['_links']['tokens:conflicts']['href'])) {
                    $conflictResponse = $this->resolveConflict(
                        $this->worldpayhelper->getXmlUsername(),
                        $this->worldpayhelper->getXmlPassword(),
                        $responseToArray['_links']['tokens:conflicts']['href']
                    );
                }
                $directOrderParams['paymentDetails']['paymentType'] = 'TOKEN-SSL';
                $directOrderParams['paymentDetails']['token_url'] = $directOrderParams['verifiedToken'];
                $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/worldpay.log');
                $logger = new \Zend\Log\Logger();
                $logger->addWriter($writer);
               
                $logger->info('check customer is logged in paymentDetails token_url ...................');
            
                $customerId = $this->customerSession->getCustomer()->getId();
               
                //check save card for graphql order
                $quote = $this->quoteFactory->create()->load($directOrderParams['quoteId']);
                
                $customerId = $quote->getCustomer()->getId();
               
                //check save card is checked by user
                if ($customerId) {
                 
                    $logger->info('customer is logged in  ...............................');
                //check save card request for normal order
                    $saveMyCard = $directOrderParams['paymentDetails']['saveMyCard'];
                
                //check save card for graphql order
               
                    $addtionalData = $quote->getPayment()->getOrigData();
                
                    $saveMyCardGraphQl = '';
                    if (isset($addtionalData['additional_information']['save_card'])) {
                        $saveMyCardGraphQl = $addtionalData['additional_information']['save_card'];
                    }
                
                    if ($saveMyCard == 1 || $saveMyCardGraphQl == 1) {
                        $logger->info('sending detailed tokem req  ..........................');
                        //get detailed token
                        $getTokenDetails = $this->_getDetailedVerifiedToken(
                            $directOrderParams['verifiedToken'],
                            $this->worldpayhelper->getXmlUsername(),
                            $this->worldpayhelper->getXmlPassword()
                        );
                   
                        $tokenDetailResponseToArray = json_decode($getTokenDetails, true);
                        //make a call to getBrand Details,content-type is different
                        $getTokenBrandDetails = $this->getDetailedTokenForBrand(
                            $tokenDetailResponseToArray['_links']['tokens:token']['href'],
                            $this->worldpayhelper->getXmlUsername(),
                            $this->worldpayhelper->getXmlPassword()
                        );
                        $brandResponse = json_decode($getTokenBrandDetails, true);
                        $tokenDetailResponseToArray['card_brand'] = $brandResponse['paymentInstrument']['brand'];
                        $tokenDetailResponseToArray['customer_id'] = $customerId;
                        // Set disclaimer flag in customer token session
                        $tokenDetailResponseToArray['disclaimer'] =
                                $directOrderParams['paymentDetails']['disclaimer'];
                        if (isset($conflictResponse)) {
                            $tokenDetailResponseToArray['conflictResponse'] = $conflictResponse;
                        }
                    //save detailed token in session for later use
                        $this->customerSession->setIsSavedCardRequested(true);
                        $this->customerSession->setDetailedToken($tokenDetailResponseToArray);
                    }
                }
                //required to delete for guest user.
                $this->customerSession->setVerifiedDetailedToken($directOrderParams['verifiedToken']);
            } else {
                $message = $this->worldpayhelper->getCreditCardSpecificException('CCAM18');
                if (isset($responseToArray['message'])) {
                    $message = $responseToArray['message'];
                }
                throw new \Magento\Framework\Exception\LocalizedException(__($message));
            }
        }

        $this->xmldirectorder = new \Sapient\AccessWorldpay\Model\JsonBuilder\DirectOrder($requestConfiguration);
        
        $orderSimpleXml = $this->xmldirectorder->build(
            $directOrderParams['merchantCode'],
            $directOrderParams['orderCode'],
            $directOrderParams['orderDescription'],
            $directOrderParams['currencyCode'],
            $directOrderParams['amount'],
            $directOrderParams['paymentDetails'],
            $directOrderParams['cardAddress'],
            $directOrderParams['shopperEmail'],
            $directOrderParams['acceptHeader'],
            $directOrderParams['userAgentHeader'],
            $directOrderParams['shippingAddress'],
            $directOrderParams['billingAddress'],
            $directOrderParams['shopperId'],
            $directOrderParams['quoteId'],
            $directOrderParams['threeDSecureConfig']
        );
        $this->_wplogger->info("Sending direct order request as ....");
        $this->_wplogger->info(print_r($orderSimpleXml, true));
        return $this->_sendRequest(
            $directOrderParams['orderCode'],
            $this->worldpayhelper->getXmlUsername(),
            $this->worldpayhelper->getXmlPassword(),
            $this->_getUrl(),
            $orderSimpleXml
        );
    }

    public function getDetailedTokenForBrand($verifiedToken, $username, $password)
    {
        return $this->_request->getDetailedTokenForBrand(
            $verifiedToken,
            $username,
            $password
        );
    }
    
    public function sendWebsdkTokenOrder($directOrderParams)
    {
        $this->_wplogger->info(
            '########## Submitting websdk token only request. OrderCode: '
            . $directOrderParams['orderCode'] . ' ##########'
        );
        $this->xmldirectorder = new \Sapient\AccessWorldpay\Model\JsonBuilder\WebSdkOrder();
                $orderSimpleXml = $this->xmldirectorder->build(
                    $directOrderParams['merchantCode'],
                    $directOrderParams['orderCode'],
                    $directOrderParams['orderDescription'],
                    $directOrderParams['currencyCode'],
                    $directOrderParams['amount'],
                    $directOrderParams['paymentDetails'],
                    $directOrderParams['cardAddress'],
                    $directOrderParams['shopperEmail'],
                    $directOrderParams['acceptHeader'],
                    $directOrderParams['userAgentHeader'],
                    $directOrderParams['shippingAddress'],
                    $directOrderParams['billingAddress'],
                    $directOrderParams['shopperId'],
                    $directOrderParams['quoteId'],
                    $directOrderParams['threeDSecureConfig']
                );
            $this->_wplogger->info(print_r($orderSimpleXml, true));
            $tokenOnlyResponse= $this->_request->savedCardSendRequest(
                $directOrderParams['orderCode'],
                $this->worldpayhelper->getXmlUsername(),
                $this->worldpayhelper->getXmlPassword(),
                $this->_getUrl(),
                $orderSimpleXml
            );
        if (isset($tokenOnlyResponse['outcome'])
            && $tokenOnlyResponse['outcome'] === 'authorized') {
            $xml = $this->_request->_array2xml(
                $tokenOnlyResponse,
                false,
                $directOrderParams['orderCode']
            );
            //add check for Graphql
            if (!isset($directOrderParams['is_graphql'])) {
                $this->customerSession->setUsedSavedCard(true);
            }
            return $xml;
        } else {
            return $this->_handleFailureCases($tokenOnlyResponse);
        }
    }

    public function websdkorder($directOrderParams)
    {
        $customerId = $this->customerSession->getCustomer()->getId();
        if (!isset($directOrderParams['threeDSecureConfig'])) {
            $directOrderParams['threeDSecureConfig'] = '';
        }
        $this->_wplogger->info('########## Submitting websdk order request. OrderCode: '
                               . $directOrderParams['orderCode'] . ' ##########');
        $requestConfiguration = [
            'threeDSecureConfig' => $directOrderParams['threeDSecureConfig']
        ];
       //checkGraphQl, !empty(token_url),!used_savedcard
        if (isset($directOrderParams['paymentDetails']['is_graphql'])
                && !empty($directOrderParams['paymentDetails']['token_url'] && !$this->worldpayhelper->is3DSecureEnabled())
                ) {
            $directOrderParams['paymentDetails']['verifiedToken']= $directOrderParams['paymentDetails']['token_url'];
            return $this->sendWebsdkTokenOrder($directOrderParams);
        } elseif (isset($directOrderParams['paymentDetails']['tokenId'])) {
            if (isset($directOrderParams['paymentDetails']['cvcHref'])
                && !empty($directOrderParams['paymentDetails']['cvcHref'])) {
                return $this->_sendWebSdkSavedCardRequest($directOrderParams, $customerId);
            } else {
                $directOrderParams['paymentDetails']['verifiedToken']=
                        $directOrderParams['paymentDetails']['tokenHref'];
                $this->customerSession->setUsedSavedCard(true);
                return $this->sendWebsdkTokenOrder($directOrderParams);
            }
        } else {
            if ($this->worldpayhelper->is3DSecureEnabled()
                && (isset($directOrderParams['verifiedToken'])
                || $directOrderParams['paymentDetails']['paymentType'] === 'TOKEN-SSL')) {
                  $directOrderParams['paymentDetails']['verifiedToken'] =
                                        isset($directOrderParams['verifiedToken'])?
                                              $directOrderParams['verifiedToken']:
                                              $directOrderParams['paymentDetails']['token_url'];
                  $this->xmldirectorder = new \Sapient\AccessWorldpay\Model\JsonBuilder\WebSdkOrder();

                $orderSimpleXml = $this->xmldirectorder->build(
                    $directOrderParams['merchantCode'],
                    $directOrderParams['orderCode'],
                    $directOrderParams['orderDescription'],
                    $directOrderParams['currencyCode'],
                    $directOrderParams['amount'],
                    $directOrderParams['paymentDetails'],
                    $directOrderParams['cardAddress'],
                    $directOrderParams['shopperEmail'],
                    $directOrderParams['acceptHeader'],
                    $directOrderParams['userAgentHeader'],
                    $directOrderParams['shippingAddress'],
                    $directOrderParams['billingAddress'],
                    $directOrderParams['shopperId'],
                    $directOrderParams['quoteId'],
                    $directOrderParams['threeDSecureConfig']
                );
                $this->_wplogger->info(print_r($orderSimpleXml, true));
                return $this->_sendRequest(
                    $directOrderParams['orderCode'],
                    $this->worldpayhelper->getXmlUsername(),
                    $this->worldpayhelper->getXmlPassword(),
                    $this->_getUrl(),
                    $orderSimpleXml
                );
            }
            $verifiedTokenRequest = $this->_createWebSdkVerifiedTokenReq($directOrderParams);
            $verifiedTokenResponse = $this->_getVerifiedToken(
                $verifiedTokenRequest,
                $this->worldpayhelper->getXmlUsername(),
                $this->worldpayhelper->getXmlPassword()
            );
            $responseToArray = json_decode($verifiedTokenResponse, true);

            if (isset($responseToArray['outcome'])
                && $responseToArray['outcome'] == 'verified') {
                $directOrderParams['paymentDetails']['verifiedToken'] =
                        $responseToArray['_links']['tokens:token']['href'];
                $saveMyCard = $directOrderParams['paymentDetails']['saveMyCard'];
                /*Conflict Resolution*/
                if ($responseToArray['response_code']==409
                    && !empty($responseToArray['_links']['tokens:conflicts']['href'])) {
                    $conflictResponse = $this->resolveConflict(
                        $this->worldpayhelper->getXmlUsername(),
                        $this->worldpayhelper->getXmlPassword(),
                        $responseToArray['_links']['tokens:conflicts']['href']
                    );
                }
                if ($saveMyCard == 1) {
                    //get detailed token
                    $getTokenDetails = $this->_getDetailedVerifiedToken(
                        $directOrderParams['paymentDetails']['verifiedToken'],
                        $this->worldpayhelper->getXmlUsername(),
                        $this->worldpayhelper->getXmlPassword()
                    );
                     $tokenDetailResponseToArray = json_decode($getTokenDetails, true);
                    
                    //make a call to getBrand Details,content-type is different
                    $getTokenBrandDetails = $this->getDetailedTokenForBrand(
                        $tokenDetailResponseToArray['_links']['tokens:token']['href'],
                        $this->worldpayhelper->getXmlUsername(),
                        $this->worldpayhelper->getXmlPassword()
                    );
                    $brandResponse = json_decode($getTokenBrandDetails, true);
                    $tokenDetailResponseToArray['card_brand'] = $brandResponse['paymentInstrument']['brand'];
                    
                        $tokenDetailResponseToArray['customer_id'] = $customerId;
                        $tokenDetailResponseToArray['disclaimer'] = $directOrderParams['paymentDetails']['disclaimer'];
                        //Set Resolve Conflict Response Code In Customer Session
                    if (isset($conflictResponse)) {
                        $tokenDetailResponseToArray['conflictResponse'] = $conflictResponse;
                    }
                        //save detailed token in session for later use
                        $this->customerSession->setIsSavedCardRequested(true);
                        $this->customerSession->setDetailedToken($tokenDetailResponseToArray);
                }
                //required to delete for guest user.
                $this->customerSession->setVerifiedDetailedToken(
                    $directOrderParams['paymentDetails']['verifiedToken']
                );
                $this->xmldirectorder = new \Sapient\AccessWorldpay\Model\JsonBuilder\WebSdkOrder();

                $orderSimpleXml = $this->xmldirectorder->build(
                    $directOrderParams['merchantCode'],
                    $directOrderParams['orderCode'],
                    $directOrderParams['orderDescription'],
                    $directOrderParams['currencyCode'],
                    $directOrderParams['amount'],
                    $directOrderParams['paymentDetails'],
                    $directOrderParams['cardAddress'],
                    $directOrderParams['shopperEmail'],
                    $directOrderParams['acceptHeader'],
                    $directOrderParams['userAgentHeader'],
                    $directOrderParams['shippingAddress'],
                    $directOrderParams['billingAddress'],
                    $directOrderParams['shopperId'],
                    $directOrderParams['quoteId'],
                    $directOrderParams['threeDSecureConfig']
                );
                $this->_wplogger->info(print_r($orderSimpleXml, true));
                return $this->_sendRequest(
                    $directOrderParams['orderCode'],
                    $this->worldpayhelper->getXmlUsername(),
                    $this->worldpayhelper->getXmlPassword(),
                    $this->_getUrl(),
                    $orderSimpleXml
                );
            } else {
                $message = $this->worldpayhelper->getCreditCardSpecificException('CCAM18');
                if (isset($responseToArray['message'])) {
                    $message = $responseToArray['message'];
                }
                throw new \Magento\Framework\Exception\LocalizedException(__($message));
            }
        }
    }

    protected function _createWebSdkVerifiedTokenReq($directOrderParams)
    {
        $instruction['paymentInstrument'] = ["type" => "card/checkout",
                        "cardHolderName" => strtolower(
                            $directOrderParams['paymentDetails']['cardHolderName']
                        ),
                                //lowered the case to remove conflict arise due to case changes
                        "sessionHref" => $directOrderParams['paymentDetails']['sessionHref'],
            ];

        $instruction['paymentInstrument']['billingAddress'] =
                ["address1" => $directOrderParams['billingAddress']['firstName'],
                "address2" => $directOrderParams['billingAddress']['lastName'],
                "address3" => $directOrderParams['billingAddress']['street'],
                "postalCode" => $directOrderParams['billingAddress']['postalCode'],
                 "city" => $directOrderParams['billingAddress']['city']];
        if (isset($directOrderParams['billingAddress']['state'])
            && $directOrderParams['billingAddress']['state'] !== '') {
            $instruction['paymentInstrument']['billingAddress']['state'] =
                    $directOrderParams['billingAddress']['state'];
        }
            $instruction['paymentInstrument']['billingAddress']['countryCode'] =
                    $directOrderParams['billingAddress']['countryCode'];
            $instruction['merchant'] = ["entity" => $this->worldpayhelper->getMerchantEntityReference()];
            $instruction['verificationCurrency'] = ($directOrderParams['currencyCode']);

        if ($this->customerSession->isLoggedIn()) {
            $shoperId = $this->customerSession->getCustomer()->getId()
                    .'_'.date("m").date("Y");
            $instruction['namespace'] = $shoperId;
        } else {
            $instruction['namespace'] = strtotime("now");
        }
            return json_encode($instruction);
    }

    protected function _createVerifiedTokenReq($directOrderParams)
    {
        $instruction = [];
      //graphql order
        if ($directOrderParams['paymentDetails']['cardHolderName'] == '') {
            $quote = $this->quoteFactory->create()->load($directOrderParams['quoteId']);

            $addtionalData = $quote->getPayment()->getOrigData();
            $ccData = $addtionalData['additional_information'];
            $instruction['paymentInstrument'] =
                ["type" => "card/plain",
                "cardHolderName" => strtolower($ccData['cc_name']),
                //Using lowercase for cardholder name to minimize the conflict
                "cardNumber" => $ccData['cc_number'],
                "cardExpiryDate" => ["month" => (int) $ccData['cc_exp_month'],
                    "year" => (int) $ccData['cc_exp_year']],
                "cvc" => (int) $ccData['cvc']];

            $instruction['paymentInstrument']['billingAddress'] =
                ["address1" => $directOrderParams['billingAddress']['firstName'],
                "address2" => $directOrderParams['billingAddress']['lastName'],
                "address3" => $directOrderParams['billingAddress']['street'],
                "postalCode" => $directOrderParams['billingAddress']['postalCode'],
                "city" => $directOrderParams['billingAddress']['city']];
            if (isset($directOrderParams['billingAddress']['state'])
                && $directOrderParams['billingAddress']['state'] !== '') {
                $instruction['paymentInstrument']['billingAddress']['state'] =
                        $directOrderParams['billingAddress']['state'];
            }
            $instruction['paymentInstrument']['billingAddress']['countryCode'] =
                    $directOrderParams['billingAddress']['countryCode'];
            $instruction['merchant'] = ["entity" => $this->worldpayhelper->getMerchantEntityReference()];
            $instruction['verificationCurrency'] = ($directOrderParams['currencyCode']);
            /*Fixed namespace issue for graphQl*/
            if ($quote->getCustomer()->getId()) {
                $shoperId = $quote->getCustomer()->getId().'_'.date("m").date("Y");
                $instruction['namespace'] = $shoperId;
            } else {
                $instruction['namespace'] = strtotime("now");
            }

            return json_encode($instruction);
            
        } else {
            if (isset($directOrderParams['paymentDetails']['directSessionHref'])
                && $directOrderParams['paymentDetails']['directSessionHref'] !== '') {
                $instruction['paymentInstrument'] = ["type" => "card/checkout",
                        "cardHolderName" => strtolower(
                            $directOrderParams['paymentDetails']['cardHolderName']
                        ),
                        //Using lowercase for cardholder name to minimize the conflict
                        "sessionHref" =>$directOrderParams['paymentDetails']['directSessionHref'],
                        ];
            }
            if (isset($directOrderParams['paymentDetails']['sessionHref'])
                && $directOrderParams['paymentDetails']['sessionHref'] !== '') {
                $instruction['paymentInstrument'] = ["type" => "card/checkout",
                        "cardHolderName" => strtolower(
                            $directOrderParams['paymentDetails']['cardHolderName']
                        ),
                       //Using lowercase for cardholder name to minimize the conflict
                        "sessionHref" =>$directOrderParams['paymentDetails']['sessionHref'],
                        ];
            }

            $instruction['paymentInstrument']['billingAddress'] =
                ["address1" => $directOrderParams['billingAddress']['firstName'],
                "address2" => $directOrderParams['billingAddress']['lastName'],
                "address3" => $directOrderParams['billingAddress']['street'],
                "postalCode" => $directOrderParams['billingAddress']['postalCode'],
                "city" => $directOrderParams['billingAddress']['city']];
            if (isset($directOrderParams['billingAddress']['state'])
                && $directOrderParams['billingAddress']['state'] !== '') {
                $instruction['paymentInstrument']['billingAddress']['state'] =
                        $directOrderParams['billingAddress']['state'];
            }
            $instruction['paymentInstrument']['billingAddress']['countryCode'] =
                    $directOrderParams['billingAddress']['countryCode'];
            $instruction['merchant'] = ["entity" => $this->worldpayhelper->getMerchantEntityReference()];
            $instruction['verificationCurrency'] = ($directOrderParams['currencyCode']);

            if ($this->customerSession->isLoggedIn()) {
                $shoperId = $this->customerSession->getCustomer()->getId().'_'.date("m").date("Y");
                $instruction['namespace'] = $shoperId;
            } else {
                $instruction['namespace'] = strtotime("now");
            }
            
            return json_encode($instruction);
        }
    }

    /**
     * Send Apple Pay order XML to Worldpay server
     *
     * @param array $walletOrderParams
     * @return mixed
     */
    public function applePayOrder($applePayOrderParams)
    {
        try {
            $this->_wplogger->info(
                '########## Submitting Apple Pay order request. OrderCode: '
                . $applePayOrderParams['orderCode'] . ' ##########'
            );
            $customerId = $this->customerSession->getCustomer()->getId();
            $this->xmldirectorder = new \Sapient\AccessWorldpay\Model\JsonBuilder\ApplePayOrder();
            
            //Entity Ref value
            //$applePayOrderParams['merchantCode']['entityRef'] = $this->worldpayhelper->getMerchantEntityReference();
            $appleSimpleXml = $this->xmldirectorder->build(
                $applePayOrderParams['merchantCode'],
                $applePayOrderParams['orderCode'],
                $applePayOrderParams['orderDescription'],
                $applePayOrderParams['currencyCode'],
                $applePayOrderParams['amount'],
                $applePayOrderParams['shopperEmail'],
                $applePayOrderParams['protocolVersion'],
                $applePayOrderParams['signature'],
                $applePayOrderParams['data'],
                $applePayOrderParams['ephemeralPublicKey'],
                $applePayOrderParams['publicKeyHash'],
                $applePayOrderParams['transactionId']
            );
            $this->_wplogger->info(print_r($appleSimpleXml, true));
            return $this->_sendApplePayRequest(
                $applePayOrderParams['orderCode'],
                $this->worldpayhelper->getXmlUsername(),
                $this->worldpayhelper->getXmlPassword(),
                $this->_getUrl(),
                $appleSimpleXml
            );
        } catch (Exception $ex) {
            throw new \Magento\Framework\Exception\LocalizedException(__($ex));
        }
    }
    
    /**
     * process the request
     *
     * @param SimpleXmlElement $xml
     * @param string $username
     * @param string $password
     * @return SimpleXmlElement $response
     */
    public function _getVerifiedToken($verifiedTokenRequest, $username, $password)
    {
        $response = $this->_request->getVerifiedToken($verifiedTokenRequest, $username, $password);
        return $response;
    }
    
    public function _getDetailedVerifiedToken($verifiedToken, $username, $password)
    {
        $response = $this->_request->getDetailedVerifiedToken($verifiedToken, $username, $password);
        return $response;
    }

    /**
     * process the request
     *
     * @param SimpleXmlElement $xml
     * @param string $username
     * @param string $password
     * @return SimpleXmlElement $response
     */
    protected function _sendApplePayRequest($orderCode, $username, $password, $url, $xml)
    {
        $response = $this->_request->sendApplePayRequest($orderCode, $username, $password, $url, $xml);
        return $response;
    }
    
    protected function _sendRequest($orderCode, $username, $password, $url, $xml)
    {
        $response = $this->_request->sendRequest($orderCode, $username, $password, $url, $xml);
        return $response;
    }

    /**
     * check error
     *
     * @param SimpleXmlElement $response
     * @throw Exception
     */
    protected function _checkForError($response)
    {
        $paymentService = new \SimpleXmlElement($response);
        $lastEvent = $paymentService->xpath('//lastEvent');
        if ($lastEvent && $lastEvent[0] =='REFUSED') {
            return;
        }
        $error = $paymentService->xpath('//error');

        if ($error) {
            $this->_wplogger->error('An error occurred while sending the request');
            $this->_wplogger->error('Error (code ' . $error[0]['code'] . '): ' . $error[0]);
            throw new \Magento\Framework\Exception\LocalizedException($error[0]);
        }
    }

    public function paymentOptionsByCountry($paymentOptionsParams)
    {
         $this->_wplogger->info('########## Submitting payment otions request ##########');
         $this->xmlpaymentoptions = new \Sapient\AccessWorldpay\Model\JsonBuilder\PaymentOptions();
        $paymentOptionsXml = $this->xmlpaymentoptions->build(
            $paymentOptionsParams['merchantCode'],
            $paymentOptionsParams['countryCode']
        );

        return $this->_sendRequest(
            dom_import_simplexml($paymentOptionsXml)->ownerDocument,
            $this->worldpayhelper->getXmlUsername($paymentOptionsParams['paymentType']),
            $this->worldpayhelper->getXmlPassword($paymentOptionsParams['paymentType'])
        );
    }
    
    /**
     * Send capture XML to Worldpay server
     *
     * @param \Magento\Sales\Model\Order $order
     * @param \Magento\Framework\DataObject $wp
     * @param string $paymentMethodCode
     * @return mixed
     */
    public function capture(\Magento\Sales\Model\Order $order, $wp, $paymentMethodCode)
    {
        if ($this->worldpayhelper->isWorldPayEnable()) { // Capture request only when service is enabled
            $collectionData = $this->omsCollectionFactory->create()
                    ->addFieldToSelect(['awp_settle_param','awp_partial_settle_param'])
                    ->addFieldToFilter('awp_order_code', ['eq' => $wp->getWorldpayOrderId()]);
                $collectionData = $collectionData->getData();
            if ($collectionData) {
                $captureUrl = $collectionData[0]['awp_settle_param'];
                $partialCaptureUrl = $collectionData[0]['awp_partial_settle_param'];
            }
            $requestType = 'capture';
            //print_r($collectionData);
            //exit;
            $orderCode = $wp->getWorldpayOrderId();
            $this->_wplogger->info(
                '########## Submitting capture request. Order: '
                . $orderCode . ' Amount:' . $order->getGrandTotal() . ' ##########'
            );
            $this->xmlcapture = new \Sapient\AccessWorldpay\Model\JsonBuilder\Capture();
            
            $captureSimpleXml = $this->xmlcapture->build(
                $this->worldpayhelper->getMerchantCode($wp->getPaymentType()),
                $orderCode,
                $order->getOrderCurrencyCode(),
                $order->getGrandTotal(),
                $requestType
            );
            //print_r($captureSimpleXml); exit;
            
            return $this->_sendRequest(
                $orderCode,
                $this->worldpayhelper->getXmlUsername(),
                $this->worldpayhelper->getXmlPassword(),
                $captureUrl,
                null
            );
        }
        throw new \Magento\Framework\Exception\LocalizedException(
            __('Access Worldpay Service Not Available')
        );
    }
    
    /**
     * Send Partial capture XML to Worldpay server
     *
     * @param \Magento\Sales\Model\Order $order
     * @param \Magento\Framework\DataObject $wp
     * @param string $paymentMethodCode
     * @return mixed
     */
    public function partialCapture(\Magento\Sales\Model\Order $order, $wp, $grandTotal)
    {
        if ($this->worldpayhelper->isWorldPayEnable()) { // Capture request only when service is enabled
            $collectionData = $this->omsCollectionFactory->create()
                    ->addFieldToSelect(['awp_settle_param','awp_partial_settle_param'])
                    ->addFieldToFilter('awp_order_code', ['eq' => $wp->getWorldpayOrderId()]);
            $collectionData = $collectionData->getData();
            if ($collectionData) {
                $captureUrl = $collectionData[0]['awp_settle_param'];
                $partialCaptureUrl = $collectionData[0]['awp_partial_settle_param'];
            }
            $requestType = 'partial_capture';
            $orderCode = $wp->getWorldpayOrderId();
            $this->_wplogger->info(
                '########## Submitting Partial capture request. Order: '
                . $orderCode . ' Amount:' . $grandTotal . ' ##########'
            );
            $this->xmlcapture = new \Sapient\AccessWorldpay\Model\JsonBuilder\Capture();
            
            $captureSimpleXml = $this->xmlcapture->build(
                $this->worldpayhelper->getMerchantCode($wp->getPaymentType()),
                $orderCode,
                $order->getOrderCurrencyCode(),
                $grandTotal,
                $requestType
            );
            $this->_wplogger->info(print_r($captureSimpleXml, true));
            return $this->_sendRequest(
                $orderCode,
                $this->worldpayhelper->getXmlUsername(),
                $this->worldpayhelper->getXmlPassword(),
                $partialCaptureUrl,
                $captureSimpleXml
            );
        }
        throw new \Magento\Framework\Exception\LocalizedException(
            __('Access Worldpay Service Not Available')
        );
    }
    
    /**
     * Send refund Json to Worldpay server
     *
     * @param \Magento\Sales\Model\Order $order
     * @param \Magento\Framework\DataObject $wp
     * @param string $paymentMethodCode
     * @param float $amount
     * @param  $reference
     * @return mixed
     */
    public function refund(\Magento\Sales\Model\Order $order, $wp, $paymentMethodCode, $amount, $reference)
    {
        $collectionData = $this->omsCollectionFactory->create()
                ->addFieldToSelect(['awp_refund_param','awp_partial_refund_param'])
                ->addFieldToFilter('awp_order_code', ['eq' => $wp->getWorldpayOrderId()]);
            $collectionData = $collectionData->getData();
        if ($collectionData) {
            $refundUrl = $collectionData[0]['awp_refund_param'];
        }
        $requestType = 'refund';
        $orderCode = $wp->getWorldpayOrderId();
        $this->_wplogger->info('########## Submitting refund request. OrderCode: ' . $orderCode . ' ##########');
        $this->xmlrefund = new \Sapient\AccessWorldpay\Model\JsonBuilder\Refund();
        $refundSimpleXml = $this->xmlrefund->build(
            $this->worldpayhelper->getMerchantCode($wp->getPaymentType()),
            $orderCode,
            $order->getOrderCurrencyCode(),
            $amount,
            $requestType,
            $reference
        );

        return $this->_sendRequest(
            $orderCode,
            $this->worldpayhelper->getXmlUsername(),
            $this->worldpayhelper->getXmlPassword(),
            $refundUrl,
            null
        );
    }
    
    /**
     * Send partial refund Json to Worldpay server
     *
     * @param \Magento\Sales\Model\Order $order
     * @param \Magento\Framework\DataObject $wp
     * @param string $paymentMethodCode
     * @param float $amount
     * @param  $reference
     * @return mixed
     */
    public function partialRefund(\Magento\Sales\Model\Order $order, $wp, $paymentMethodCode, $amount, $reference)
    {
        $collectionData = $this->omsCollectionFactory->create()
                ->addFieldToSelect(['awp_refund_param','awp_partial_refund_param'])
                ->addFieldToFilter('awp_order_code', ['eq' => $wp->getWorldpayOrderId()]);
            $collectionData = $collectionData->getData();
        if ($collectionData) {
            $partialRefundUrl = $collectionData[0]['awp_partial_refund_param'];
        }
        $requestType = 'partial_refund';
        $orderCode = $wp->getWorldpayOrderId();
        $this->_wplogger->info('########## Submitting Partial refund request. OrderCode: '
                               . $orderCode . ' ##########');
        $this->xmlrefund = new \Sapient\AccessWorldpay\Model\JsonBuilder\Refund();
        $refundSimpleXml = $this->xmlrefund->build(
            $this->worldpayhelper->getMerchantCode($wp->getPaymentType()),
            $orderCode,
            $order->getOrderCurrencyCode(),
            $amount,
            $reference,
            $requestType
        );

        return $this->_sendRequest(
            $orderCode,
            $this->worldpayhelper->getXmlUsername(),
            $this->worldpayhelper->getXmlPassword(),
            $partialRefundUrl,
            $refundSimpleXml
        );
    }

    public function _createDeviceDataCollection($directOrderParams)
    {
        $url = str_replace(
            '/payments/authorizations',
            '/verifications/customers/3ds/deviceDataInitialization',
            $this->_getUrl()
        );
        //$url = 'https://try.access.worldpay.com/verifications/customers/3ds/deviceDataInitialization';
        if ($this->worldpayhelper->is3DSecureEnabled()) {
            $this->_wplogger->info(
                '########## Submitting get DDC order request. OrderCode: '
                . $directOrderParams['orderCode'] . ' ##########'
            );
            $this->xmldirectorder = new \Sapient\AccessWorldpay\Model\JsonBuilder\DeviceDataCollection();
            $orderSimpleXml= $this->xmldirectorder->build(
                $directOrderParams['orderCode'],
                $directOrderParams['paymentDetails']
            );
            $response = $this->_request->sendDdcRequest(
                $directOrderParams['orderCode'],
                $this->worldpayhelper->getXmlUsername(),
                $this->worldpayhelper->getXmlPassword(),
                $url,
                $orderSimpleXml
            );
            return $response;
        }
    }
    
    public function authenticate3Ddata($authenticationurl, $directOrderParams)
    {
        $this->_wplogger->info(
            '########## Submitting get 3Ds authentication request. OrderCode: '
            . $directOrderParams['orderCode'] . ' ##########'
        );
        $this->xmldirectorder = new \Sapient\AccessWorldpay\Model\JsonBuilder\ThreeDsAuthentication();
       
        $orderSimpleXml= $this->xmldirectorder->build(
            $directOrderParams['orderCode'],
            $directOrderParams['paymentDetails'],
            $directOrderParams['billingAddress'],
            $directOrderParams['currencyCode'],
            $directOrderParams['amount'],
            $directOrderParams['acceptHeader'],
            $directOrderParams['userAgentHeader'],
            $directOrderParams['riskData']
        );
        $this->_wplogger->info($orderSimpleXml);
        $response = $this->_request->sendDdcRequest(
            $directOrderParams['orderCode'],
            $this->worldpayhelper->getXmlUsername(),
            $this->worldpayhelper->getXmlPassword(),
            $authenticationurl,
            $orderSimpleXml
        );
       
        return $response;
    }
    
    public function _createVerifiedTokenFor3Ds($directOrderParams)
    {
           
        $this->_wplogger->info(
            '########## Submitting create VerifiedToken For 3Ds. OrderCode: '
            . $directOrderParams['orderCode'] . ' ##########'
        );
        //build json request for verified token
        $verifiedTokenRequest = $this->_createVerifiedTokenReq($directOrderParams);

            //send verified token request to Access Worldpay
        $verifiedTokenResponse = $this->_getVerifiedToken(
            $verifiedTokenRequest,
            $this->worldpayhelper->getXmlUsername(),
            $this->worldpayhelper->getXmlPassword()
        );

        $responseToArray = json_decode($verifiedTokenResponse, true);
        if (isset($responseToArray['outcome']) && $responseToArray['outcome'] == 'verified') {

                $directOrderParams['verifiedToken'] = $responseToArray['_links']['tokens:token']['href'];
                /*Conflict Resolution*/
            if ($responseToArray['response_code']==409
                && !empty($responseToArray['_links']['tokens:conflicts']['href'])) {
                $conflictResponse = $this->resolveConflict(
                    $this->worldpayhelper->getXmlUsername(),
                    $this->worldpayhelper->getXmlPassword(),
                    $responseToArray['_links']['tokens:conflicts']['href']
                );
            }
                $directOrderParams['paymentDetails']['paymentType'] = 'TOKEN-SSL';
                $directOrderParams['paymentDetails']['token_url'] = $directOrderParams['verifiedToken'];
                $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/worldpay.log');
                $logger = new \Zend\Log\Logger();
                $logger->addWriter($writer);
               
                $logger->info('verified outcome came  ...............................');
            
                $customerId = $this->customerSession->getCustomer()->getId();
                //check save card for graphql order
                $quote = $this->quoteFactory->create()->load($directOrderParams['quoteId']);
                $customerId = $quote->getCustomer()->getId();
               
                //check save card is checked by user
            if ($customerId && isset($directOrderParams['paymentDetails']['saveMyCard'])
                        && $directOrderParams['paymentDetails']['saveMyCard'] == 1 ) {
                 $logger->info('customer is logged in  ...............................');
                $logger->info('sending detailed tokem req  ...............................');
                //get detailed token
                $getTokenDetails = $this->_getDetailedVerifiedToken(
                    $directOrderParams['verifiedToken'],
                    $this->worldpayhelper->getXmlUsername(),
                    $this->worldpayhelper->getXmlPassword()
                );
                    
                $tokenDetailResponseToArray = json_decode($getTokenDetails, true);
                //make a call to getBrand Details,content-type is different
                $getTokenBrandDetails = $this->getDetailedTokenForBrand(
                    $tokenDetailResponseToArray['_links']['tokens:token']['href'],
                    $this->worldpayhelper->getXmlUsername(),
                    $this->worldpayhelper->getXmlPassword()
                );
                $brandResponse = json_decode($getTokenBrandDetails, true);
                $tokenDetailResponseToArray['card_brand'] = $brandResponse['paymentInstrument']['brand'];
                $tokenDetailResponseToArray['customer_id'] = $customerId;
                // Set disclaimer flag in customer token session
                $tokenDetailResponseToArray['disclaimer'] = $directOrderParams['paymentDetails']['disclaimer'];
                if (isset($conflictResponse)) {
                    $tokenDetailResponseToArray['conflictResponse'] = $conflictResponse;
                }
                //save detailed token in session for later use
                $this->customerSession->setIsSavedCardRequested(true);
                $this->customerSession->setDetailedToken($tokenDetailResponseToArray);
            }
                //required to delete for guest user.
                $this->customerSession->setVerifiedDetailedToken($directOrderParams['verifiedToken']);
                return $directOrderParams;
        } else {
            $message = $this->worldpayhelper->getCreditCardSpecificException('CCAM18');
            if (isset($responseToArray['message'])) {
                if ($responseToArray['message'] === "Session could not be found") {
                    $message =  $responseToArray['message']. " Please refresh and try again." ;
                } else {
                    $message = $responseToArray['message'];
                }
            }
            throw new \Magento\Framework\Exception\LocalizedException(__($message));
        }
    }
    
    public function order3Ds2Secure($directOrderParams, $threeDSecureParams)
    {
        $verificationResponse = $threeDSecureParams;
        if ($threeDSecureParams['outcome'] ==='challenged') {
            $this->_wplogger->info(
                '########## Submitting get 3Ds verification request. OrderCode: '
                . $directOrderParams['orderCode'] . ' ##########'
            );
            $this->xmldirectorder = new \Sapient\AccessWorldpay\Model\JsonBuilder\ThreeDsVerifiaction();
            
            
            $verificationRequest = $this->xmldirectorder->build(
                $directOrderParams,
                $threeDSecureParams['challenge']['reference']
            );
            $verificationUrl = $threeDSecureParams['_links']['3ds:verify']['href'];
         
            $this->_wplogger->info($verificationRequest);
      
            $verificationResponse = $this->_request->sendDdcRequest(
                $directOrderParams['orderCode'],
                $this->worldpayhelper->getXmlUsername(),
                $this->worldpayhelper->getXmlPassword(),
                $verificationUrl,
                $verificationRequest
            );
        }
        $this->_wplogger->info($verificationResponse['outcome']);
        if (in_array(
            strtoupper($verificationResponse['outcome']),
            $this->threeDsValidResponse
        )) {
            $directOrderParams['threeDSecureConfig'] = $verificationResponse;
            if ($this->worldpayhelper->getCcIntegrationMode() == 'direct') {
                $response = $this->order($directOrderParams);
            } else {
                $response = $this->websdkorder($directOrderParams);
            }
            return $response;
        } else {
            $this->handle3DsFailureCases($verificationResponse);
        }
    }
    
    public function handle3DsFailureCases($verificationResponse)
    {
        if (!isset($verificationResponse)) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __($this->worldpayhelper->getCreditCardSpecificException('CCAM6'))
            );
        } else {
            throw new \Magento\Framework\Exception\LocalizedException(
                __($this->worldpayhelper->getCreditCardSpecificException('CCAM19'))
            );
        }
    }

    public function createSessionHrefForDirect($orderParams)
    {
        $url = str_replace(
            '/payments/authorizations',
            '/verifiedTokens/sessions',
            $this->_getUrl()
        );
        $this->_wplogger->info(
            '########## Submitting get Session Href request for direct integration. ##########'
        );
        $params = json_encode($orderParams);
        $sesshrefresponse = $this->_request->getSessionHrefForDirect(
            $url,
            $params,
            $this->worldpayhelper->getXmlUsername(),
            $this->worldpayhelper->getXmlPassword()
        );
        $sessionHref = json_decode($sesshrefresponse, true);
        return $sessionHref['_links']['verifiedTokens:session'];
    }
    
     /**
      * Send token update XML to Worldpay server
      *
      * @param SavedToken $tokenModel
      * @param \Magento\Customer\Model\Customer $customer
      * @param int $storeId
      * @return mixed
      */
    public function tokenUpdate(
        SavedToken $tokenModel,
        \Magento\Customer\Model\Customer $customer,
        $storeId
    ) {
        $this->_wplogger->info('########## Submitting token update. TokenId: ' . $tokenModel->getId() . ' ##########');
        $requestParameters = [
            'tokenModel'   => $tokenModel,
            'customer'     => $customer,
            'merchantCode' => $this->worldpayhelper->getMerchantCode($tokenModel->getMethod()),
        ];
        /** @var SimpleXMLElement $simpleXml */
        $this->tokenUpdateXml = new \Sapient\AccessWorldpay\Model\XmlBuilder\TokenUpdate($requestParameters);
        $tokenUpdateSimpleXml = $this->tokenUpdateXml->build();

        return $this->_sendRequest(
            dom_import_simplexml($tokenUpdateSimpleXml)->ownerDocument,
            $this->worldpayhelper->getXmlUsername($tokenModel->getMethod()),
            $this->worldpayhelper->getXmlPassword($tokenModel->getMethod())
        );
    }
    
     /**
      * Send token delete XML to Worldpay server
      *
      * @param SavedToken $tokenModel
      * @param \Magento\Customer\Model\Customer $customer
      * @param int $storeId
      * @return mixed
      */
    public function tokenDelete(
        SavedToken $tokenModel,
        \Magento\Customer\Model\Customer $customer,
        $storeId
    ) {
        $this->_wplogger->info('########## Submitting token Delete. TokenId: ' . $tokenModel->getId() . ' ##########');

        $requestParameters = [
            'tokenModel'   => $tokenModel,
            'customer'     => $customer,
            'merchantCode' => $this->worldpayhelper->getMerchantCode($tokenModel->getMethod()),
        ];

        /** @var SimpleXMLElement $simpleXml */
        $this->tokenDeleteXml = new Sapient\AccessWorldpay\Model\XmlBuilder\TokenDelete($requestParameters);
        $tokenDeleteSimpleXml = $this->tokenDeleteXml->build();

        return $this->_sendRequest(
            dom_import_simplexml($tokenDeleteSimpleXml)->ownerDocument,
            $this->worldpayhelper->getXmlUsername($tokenModel->getMethod()),
            $this->worldpayhelper->getXmlPassword($tokenModel->getMethod())
        );
    }
    
     /**
      * Send token inquiry XML to Worldpay server
      *
      * @param SavedToken $tokenModel
      * @param \Magento\Customer\Model\Customer $customer
      * @param int $storeId
      * @return mixed
      */
    public function tokenInquiry(
        SavedToken $tokenModel
    ) {
        $this->_wplogger->info('########## Submitting token inquiry. TokenId: ' . $tokenModel->getId() . ' ##########');
        $username = $this->worldpayhelper->getXmlUsername();
        $password = $this->worldpayhelper->getXmlPassword();
        $tokenUrl = $tokenModel->getToken();
        $response = $this->_request->getTokenInquiry($tokenUrl, $username, $password);
        $tokenDetailResponseToArray = json_decode($response, true);
        return $tokenDetailResponseToArray;
    }
     /**
      * Send token inquiry XML to Worldpay server
      *
      * @param SavedToken $tokenModel
      * @param \Magento\Customer\Model\Customer $customer
      * @param int $storeId
      * @return mixed
      */
    public function getTokenDelete($tokenModelUrl)
    {
        $this->_wplogger->info('########## Deleting token . ##########');
        $username = $this->worldpayhelper->getXmlUsername();
        $password = $this->worldpayhelper->getXmlPassword();
        $response = $this->_request->getTokenDelete($tokenModelUrl, $username, $password);
        return $response;
    }
    
    public function putTokenExpiry(SavedToken $tokenModel, $cardHolderNameUrl)
    {
        $this->_wplogger->info('########## Submitting token Expiry request.  ##########');
         $requestConfiguration = [
            'tokenModel' => $tokenModel
         ];
         $this->xmlcapture = new \Sapient\AccessWorldpay\Model\JsonBuilder\TokenExpiryUpdate($requestConfiguration);
         $simpleXml = $this->xmlcapture->build();
         return $this->_request->putRequest(
             $this->worldpayhelper->getXmlUsername(),
             $this->worldpayhelper->getXmlPassword(),
             $cardHolderNameUrl,
             $simpleXml
         );
    }
    public function putTokenName(SavedToken $tokenModel, $cardHolderNameUrl)
    {
        $this->_wplogger->info('########## Submitting token CardHolderName request.  ##########');
        $requestConfiguration = [
           'tokenModel' => $tokenModel
        ];
        $this->xmlcapture = new \Sapient\AccessWorldpay\Model\JsonBuilder\TokenNameUpdate($requestConfiguration);
        
        $simpleXml = $this->xmlcapture->build();
        return $this->_request->putRequest(
            $this->worldpayhelper->getXmlUsername(),
            $this->worldpayhelper->getXmlPassword(),
            $cardHolderNameUrl,
            $simpleXml
        );
    }

    /**
     * Resolve Request Token Conflict
     */
    public function resolveConflict($username, $password, $conflictUrl)
    {
        return $this->_request->resolveConflict($username, $password, $conflictUrl);
    }
    
    public function _sendDirectSavedCardRequest($directOrderParams, $requestConfiguration)
    {
        $tokenData = $this->worldpayhelper->getSelectedSavedCardTokenData(
            $directOrderParams['paymentDetails']['tokenId']
        );
        if (!empty($tokenData[0]['cardonfile_auth_link'])) {
            $this->_wplogger->info(
                '########## Submitting direct order card on file authorization request. OrderCode: '
                . $directOrderParams['orderCode'] . ' ##########'
            );
            $directOrderParams['paymentDetails']['cardOnFileAuthorization'] = $tokenData[0]['cardonfile_auth_link'];
            $directOrderParams['paymentDetails']['paymentType'] = 'TOKEN-SSL';
            $cardOnFileAuthArrayResponse = $this->_getDirectCardOnFileAuthorization(
                $directOrderParams,
                $requestConfiguration
            );
            if (isset($cardOnFileAuthArrayResponse['outcome'])
                && $cardOnFileAuthArrayResponse['outcome'] === 'authorized') {
                $xml = $this->_request->_array2xml(
                    $cardOnFileAuthArrayResponse,
                    false,
                    $directOrderParams['orderCode']
                );
                $this->customerSession->setUsedSavedCard(true);
                return $xml;
            } else {
                return $this->_handleFailureCases($cardOnFileAuthArrayResponse);
            }
        } else {
            return $this->_getFirstDirectCardOnFileVerification($directOrderParams, $requestConfiguration);
        }
    }
    
    public function _getFirstDirectCardOnFileVerification($directOrderParams, $requestConfiguration)
    {
        $this->_wplogger->info(''
                . '########## Submitting direct order card on file verification request. OrderCode: '
                . $directOrderParams['orderCode'] . ' ##########');
        $cardOnFileVerificationResponse = $this->_getDirectCardOnFileVerification(
            $directOrderParams,
            $requestConfiguration
        );
        $cardOnFileArrayResponse = json_decode($cardOnFileVerificationResponse, true);
        if (isset($cardOnFileArrayResponse['outcome']) && $cardOnFileArrayResponse['outcome'] == 'verified') {
            $directOrderParams['paymentDetails']['cardOnFileAuthorization'] =
                    $cardOnFileArrayResponse['_links']['payments:cardOnFileAuthorize']['href'];
            $directOrderParams['paymentDetails']['paymentType'] = 'TOKEN-SSL';
            $this->customerSession->setUsedSavedCard(true);
            return $this->_getFirstDirectAuthorization($directOrderParams, $requestConfiguration);
        } else {
            return $this->_handleFailureCases($cardOnFileArrayResponse);
        }
    }
    
    public function _getFirstDirectAuthorization($directOrderParams, $requestConfiguration)
    {
        $this->_wplogger->info(
            '########## Submitting direct order card on file authorization request. OrderCode: '
            . $directOrderParams['orderCode'] . ' ##########'
        );
        $cardOnFileAuthArrayResponse = $this->_getDirectCardOnFileAuthorization(
            $directOrderParams,
            $requestConfiguration
        );
        if (isset($cardOnFileAuthArrayResponse['outcome']) && $cardOnFileAuthArrayResponse['outcome'] == 'authorized') {
            $cardOnFileAuthLink = $cardOnFileAuthArrayResponse['_links']['payments:cardOnFileAuthorize']['href'];
            $this->_wplogger->info('##    Saving card on file auth link to accessworldpay verifiedtoken.............');
            $this->updateAccessWorldpayment->_setCardOnFileAuthorizeLink(
                $directOrderParams['paymentDetails']['tokenId'],
                $cardOnFileAuthLink
            );
            $this->_wplogger->info('##    Saving done ...........................');
            $xml = $this->_request->_array2xml($cardOnFileAuthArrayResponse, false, $directOrderParams['orderCode']);
            return $xml;
        } else {
            return $this->_handleFailureCases($cardOnFileAuthArrayResponse);
        }
    }
    
    public function _getDirectCardOnFileVerification($directOrderParams, $requestConfiguration)
    {
        $this->xmldirectorder = new \Sapient\AccessWorldpay\Model\JsonBuilder\DirectOrder($requestConfiguration);
        $customerId = $this->customerSession->getCustomer()->getId();
        $ordercode = $customerId.'-'.time();
        //$url = 'https://try.access.worldpay.com/verifications/accounts/dynamic/cardOnFile';
        $url = str_replace('/payments/authorizations', '/verifications/accounts/dynamic/cardOnFile', $this->_getUrl());
        $amount = 0;
        $orderSimpleXml = $this->xmldirectorder->build(
            $directOrderParams['merchantCode'],
            $ordercode,
            $directOrderParams['orderDescription'],
            $directOrderParams['currencyCode'],
            $amount,
            $directOrderParams['paymentDetails'],
            $directOrderParams['cardAddress'],
            $directOrderParams['shopperEmail'],
            $directOrderParams['acceptHeader'],
            $directOrderParams['userAgentHeader'],
            $directOrderParams['shippingAddress'],
            $directOrderParams['billingAddress'],
            $directOrderParams['shopperId'],
            $directOrderParams['quoteId'],
            $directOrderParams['threeDSecureConfig']
        );
        $this->_wplogger->info(print_r($orderSimpleXml, true));
        return $this->_request->sendSavedCardCardOnFileVerificationRequest(
            $ordercode,
            $this->worldpayhelper->getXmlUsername(),
            $this->worldpayhelper->getXmlPassword(),
            $url,
            $orderSimpleXml
        );
    }
    
    public function _getDirectCardOnFileAuthorization($directOrderParams, $requestConfiguration)
    {
        $this->xmldirectorder = new \Sapient\AccessWorldpay\Model\JsonBuilder\DirectOrder($requestConfiguration);
        
        $orderSimpleXml = $this->xmldirectorder->build(
            $directOrderParams['merchantCode'],
            $directOrderParams['orderCode'],
            $directOrderParams['orderDescription'],
            $directOrderParams['currencyCode'],
            $directOrderParams['amount'],
            $directOrderParams['paymentDetails'],
            $directOrderParams['cardAddress'],
            $directOrderParams['shopperEmail'],
            $directOrderParams['acceptHeader'],
            $directOrderParams['userAgentHeader'],
            $directOrderParams['shippingAddress'],
            $directOrderParams['billingAddress'],
            $directOrderParams['shopperId'],
            $directOrderParams['quoteId'],
            $directOrderParams['threeDSecureConfig']
        );
        $this->_wplogger->info(print_r($orderSimpleXml, true));
        return $this->_request->savedCardSendRequest(
            $directOrderParams['orderCode'],
            $this->worldpayhelper->getXmlUsername(),
            $this->worldpayhelper->getXmlPassword(),
            $directOrderParams['paymentDetails']['cardOnFileAuthorization'],
            $orderSimpleXml
        );
    }
    
    public function _getWebSdkCardOnFileVerification($directOrderParams)
    {
        if (!isset($directOrderParams['threeDSecureConfig'])) {
            $directOrderParams['threeDSecureConfig'] = '';
        }
        $this->_wplogger->info(
            '########## getWebSdkCardOnFileVerification. OrderCode: '
            . $directOrderParams['orderCode'] . ' ##########'
        );
        $requestConfiguration = [
            'threeDSecureConfig' => $directOrderParams['threeDSecureConfig']
        ];
        $this->xmldirectorder = new \Sapient\AccessWorldpay\Model\JsonBuilder\WebSdkOrder();
        $customerId = $this->customerSession->getCustomer()->getId();
        $ordercode = $customerId.'-'.time();
        //$url = 'https://try.access.worldpay.com/verifications/accounts/dynamic/cardOnFile';
        $url = str_replace('/payments/authorizations', '/verifications/accounts/dynamic/cardOnFile', $this->_getUrl());
        $amount = 0;
        $orderSimpleXml = $this->xmldirectorder->build(
            $directOrderParams['merchantCode'],
            $ordercode,
            $directOrderParams['orderDescription'],
            $directOrderParams['currencyCode'],
            $amount,
            $directOrderParams['paymentDetails'],
            $directOrderParams['cardAddress'],
            $directOrderParams['shopperEmail'],
            $directOrderParams['acceptHeader'],
            $directOrderParams['userAgentHeader'],
            $directOrderParams['shippingAddress'],
            $directOrderParams['billingAddress'],
            $directOrderParams['shopperId'],
            $directOrderParams['quoteId'],
            $directOrderParams['threeDSecureConfig']
        );
        $this->_wplogger->info(print_r($orderSimpleXml, true));
        return $this->_request->sendSavedCardCardOnFileVerificationRequest(
            $ordercode,
            $this->worldpayhelper->getXmlUsername(),
            $this->worldpayhelper->getXmlPassword(),
            $url,
            $orderSimpleXml
        );
    }
    
    public function _getWebSdkCardOnFileAuthorization($directOrderParams)
    {
        if (!isset($directOrderParams['threeDSecureConfig'])) {
            $directOrderParams['threeDSecureConfig'] = '';
        }
        $this->xmldirectorder = new \Sapient\AccessWorldpay\Model\JsonBuilder\WebSdkOrder();

        $orderSimpleXml = $this->xmldirectorder->build(
            $directOrderParams['merchantCode'],
            $directOrderParams['orderCode'],
            $directOrderParams['orderDescription'],
            $directOrderParams['currencyCode'],
            $directOrderParams['amount'],
            $directOrderParams['paymentDetails'],
            $directOrderParams['cardAddress'],
            $directOrderParams['shopperEmail'],
            $directOrderParams['acceptHeader'],
            $directOrderParams['userAgentHeader'],
            $directOrderParams['shippingAddress'],
            $directOrderParams['billingAddress'],
            $directOrderParams['shopperId'],
            $directOrderParams['quoteId'],
            $directOrderParams['threeDSecureConfig']
        );
        $this->_wplogger->info(print_r($orderSimpleXml, true));
        return $this->_request->savedCardSendRequest(
            $directOrderParams['orderCode'],
            $this->worldpayhelper->getXmlUsername(),
            $this->worldpayhelper->getXmlPassword(),
            $directOrderParams['paymentDetails']['cardOnFileAuthorization'],
            $orderSimpleXml
        );
    }
    
    public function _handleFailureCases($errorResponse)
    {
        $message = $this->worldpayhelper->getCreditCardSpecificException('CCAM18');
        if (isset($errorResponse['errorName']) && isset($errorResponse['message'])) {
            if ($errorResponse['errorName'] === 'maximumUpdatesExceeded') {
                $message = $this->worldpayhelper->getCreditCardSpecificException('CCAM14') ;
            } elseif (preg_match('#Unable to locate token#', $errorResponse['message']) ||
                    preg_match('#Requested token does not exist#', $errorResponse['message'])) {
                $message = $this->worldpayhelper->getCreditCardSpecificException('CCAM9');
            } else {
                $message = $errorResponse['message'];
            }
        }
        $this->_wplogger->error($message);
        throw new \Magento\Framework\Exception\LocalizedException(__($message));
    }
    
    public function _sendWebSdkSavedCardRequest($directOrderParams)
    {
        $tokenData = $this->worldpayhelper->getSelectedSavedCardTokenData(
            $directOrderParams['paymentDetails']['tokenId']
        );
        if (!empty($tokenData[0]['cardonfile_auth_link'])) {
            $this->_wplogger->info(
                '########## Submitting websdk order card on file authorization request. OrderCode: '
                . $directOrderParams['orderCode'] . ' ##########'
            );
            $directOrderParams['paymentDetails']['cardOnFileAuthorization'] = $tokenData[0]['cardonfile_auth_link'];
            $cardOnFileAuthArrayResponse = $this->_getWebSdkCardOnFileAuthorization(
                $directOrderParams
            );
            if (isset($cardOnFileAuthArrayResponse['outcome'])
                && $cardOnFileAuthArrayResponse['outcome'] === 'authorized') {
                $xml = $this->_request->_array2xml(
                    $cardOnFileAuthArrayResponse,
                    false,
                    $directOrderParams['orderCode']
                );
                $this->customerSession->setUsedSavedCard(true);
                return $xml;
            } else {
                return $this->_handleFailureCases($cardOnFileAuthArrayResponse);
            }
        } else {
            return $this->_getFirstWebSdkCardOnFileVerification($directOrderParams);
        }
    }
    
    public function _getFirstWebSdkCardOnFileVerification($directOrderParams)
    {
        $this->_wplogger->info(
            '########## Submitting websdk card on file verification request. OrderCode: '
            . $directOrderParams['orderCode'] . ' ##########'
        );
        $cardOnFileVerificationResponse = $this->_getWebSdkCardOnFileVerification($directOrderParams);
        $cardOnFileArrayResponse = json_decode($cardOnFileVerificationResponse, true);
        if (isset($cardOnFileArrayResponse['outcome']) && $cardOnFileArrayResponse['outcome'] == 'verified') {
            $directOrderParams['paymentDetails']['cardOnFileAuthorization'] =
                    $cardOnFileArrayResponse['_links']['payments:cardOnFileAuthorize']['href'];
            $this->customerSession->setUsedSavedCard(true);
            return $this->_getFirstWebSdkAuthorization($directOrderParams);
        } else {
            return $this->_handleFailureCases($cardOnFileArrayResponse);
        }
    }
    
    public function _getFirstWebSdkAuthorization($directOrderParams)
    {
        $this->_wplogger->info(
            '########## Submitting websdk card on file authorization request. OrderCode: '
            . $directOrderParams['orderCode'] . ' ##########'
        );
        $cardOnFileAuthArrayResponse = $this->_getWebSdkCardOnFileAuthorization($directOrderParams);
        if (isset($cardOnFileAuthArrayResponse['outcome']) && $cardOnFileAuthArrayResponse['outcome'] == 'authorized') {
            $cardOnFileAuthLink = $cardOnFileAuthArrayResponse['_links']['payments:cardOnFileAuthorize']['href'];
            $this->_wplogger->info('##    Saving card on file auth link to accessworldpay verifiedtoken.............');
            $this->updateAccessWorldpayment->_setCardOnFileAuthorizeLink(
                $directOrderParams['paymentDetails']['tokenId'],
                $cardOnFileAuthLink
            );
            $this->_wplogger->info('##    Saving done ...........................');
            $xml = $this->_request->_array2xml($cardOnFileAuthArrayResponse, false, $directOrderParams['orderCode']);
            return $xml;
        } else {
            return $this->_handleFailureCases($cardOnFileAuthArrayResponse);
        }
    }

    /**
     * Send wallet order XML to Worldpay server
     *
     * @param array $walletOrderParams
     * @return mixed
     */
    public function walletsOrder($walletOrderParams)
    {
        $loggerMsg = '########## Submitting wallet order request. OrderCode: ';
        $this->_wplogger->info($loggerMsg . $walletOrderParams['orderCode'] . ' ##########');
        $walletOrderParams['paymentDetails']['entityRef']= $this->worldpayhelper->getMerchantEntityReference();
        $this->jsonredirectorder = new \Sapient\AccessWorldpay\Model\JsonBuilder\WalletOrder();
            $walletSimpleJson = $this->jsonredirectorder->build(
                $walletOrderParams['merchantCode'],
                $walletOrderParams['orderCode'],
                $walletOrderParams['orderDescription'],
                $walletOrderParams['currencyCode'],
                $walletOrderParams['amount'],
                $walletOrderParams['paymentType'],
                $walletOrderParams['shopperEmail'],
                $walletOrderParams['acceptHeader'],
                $walletOrderParams['userAgentHeader'],
                $walletOrderParams['protocolVersion'],
                $walletOrderParams['signature'],
                $walletOrderParams['signedMessage'],
                $walletOrderParams['shippingAddress'],
                $walletOrderParams['billingAddress'],
                $walletOrderParams['cusDetails'],
                $walletOrderParams['shopperIpAddress'],
                $walletOrderParams['paymentDetails']
            );
        $this->_wplogger->info('Sending Request To Googlepay');
        $this->_wplogger->info(print_r($walletSimpleJson, true));
        return $this->_sendGoogleRequest(
            $walletOrderParams['orderCode'],
            $this->worldpayhelper->getXmlUsername(),
            $this->worldpayhelper->getXmlPassword(),
            $this->_getUrl(),
            $walletSimpleJson
        );
    }

    /**
     * process the request
     *
     * @param SimpleXmlElement $xml
     * @param string $username
     * @param string $password
     * @return SimpleXmlElement $response
     */
    protected function _sendGoogleRequest($orderCode, $username, $password, $url, $xml)
    {
        $response = $this->_request->sendGooglePayRequest(
            $orderCode,
            $username,
            $password,
            $url,
            $xml
        );
        
        return $response;
    }
    
    public function eventInquiry($orderid)
    {
        if ($this->worldpayhelper->isWorldPayEnable()) {
            $collectionData = $this->omsCollectionFactory->create()
                    ->addFieldToSelect(['awp_order_code','awp_events_param'])
                    ->addFieldToFilter('order_increment_id', ['eq' => $orderid ]);
                $collectionData = $collectionData->getData();
            if ($collectionData) {
                $eventUrl = $collectionData[0]['awp_events_param'];
            } else {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('No available event link found to synchronize the status')
                );
            }
            
            $orderCode = $collectionData[0]['awp_order_code'];
            
            $this->_wplogger->info(
                '########## Submitting events request. Order: '
                . $orderCode . ' ##########'
            );
            
             $xml = $this->_request->sendEventRequest(
                 $orderCode,
                 $this->worldpayhelper->getXmlUsername(),
                 $this->worldpayhelper->getXmlPassword(),
                 $eventUrl,
                 null
             );
            
             return $xml;
        }
        throw new \Magento\Framework\Exception\LocalizedException(
            __('Access Worldpay Service Not Available')
        );
    }
}
