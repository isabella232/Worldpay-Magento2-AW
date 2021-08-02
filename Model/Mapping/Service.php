<?php

namespace Sapient\AccessWorldpay\Model\Mapping;

use Magento\Framework\Session\SessionManagerInterface;

class Service
{
    protected $_logger;
    protected $session;
    const THIS_TRANSACTION = 'thisTransaction';
    const LESS_THAN_THIRTY_DAYS = 'lessThanThirtyDays';
    const THIRTY_TO_SIXTY_DAYS = 'thirtyToSixtyDays';
    const MORE_THAN_SIXTY_DAYS = 'moreThanSixtyDays';
    const DURING_TRANSACTION = 'duringTransaction';
    const CREATED_DURING_TRANSACTION = 'createdDuringTransaction';
    const CHANGED_DURING_TRANSACTION = 'changedDuringTransaction';
    const NO_ACCOUNT = 'noAccount';
    const NO_CHANGE = 'noChange';
    
    public function __construct(
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger,
        \Sapient\AccessWorldpay\Helper\Data $worldpayHelper,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Customer\Model\Session $customerSession,
        SessionManagerInterface $session
    ) {
        $this->wplogger = $wplogger;
        $this->worldpayHelper = $worldpayHelper;
        $this->customerSession = $customerSession;
        $this->_urlBuilder = $urlBuilder;
        $this->session = $session;
    }

    public function collectDirectOrderParameters(
        $orderCode,
        $quote,
        $orderStoreId,
        $paymentDetails
    ) {
        $reservedOrderId = $quote->getReservedOrderId();
        return [
            'orderCode'        => $orderCode,
            'merchantCode'     => $this->worldpayHelper->getMerchantCode(),
            'orderDescription' => $this->_getOrderDescription($reservedOrderId),
            'currencyCode'     => $quote->getQuoteCurrencyCode(),
            'amount'           => $quote->getGrandTotal(),
            'paymentDetails'   => (isset($paymentDetails['token_url'])
                                   && !empty($paymentDetails['token_url']))?
                                    $this->_getDirectTokenPaymentDetails($paymentDetails)
                                    :$this->_getPaymentDetails($paymentDetails),
            'cardAddress'      => $this->_getCardAddress($quote),
            'shopperEmail'     => $quote->getCustomerEmail(),
            'acceptHeader'     => php_sapi_name() !== "cli" ?
                                    filter_input(INPUT_SERVER, 'HTTP_ACCEPT') : '',
            'userAgentHeader'  => php_sapi_name() !== "cli" ?
                                    filter_input(
                                        INPUT_SERVER,
                                        'HTTP_USER_AGENT',
                                        FILTER_SANITIZE_STRING,
                                        FILTER_FLAG_STRIP_LOW
                                    ) : '',
            'shippingAddress'  => $this->_getShippingAddress($quote),
            'billingAddress'   => $this->_getBillingAddress($quote),
            'method'           => $paymentDetails['method'],
            'orderStoreId'     => $orderStoreId,
            'shopperId'     => $quote->getCustomerId(),
            'quoteId'     => $quote->getId(),
            'riskData' => $this->getRiskDataForAuthentication($quote)
        ];
    }

    public function collectVaultOrderParameters(
        $orderCode,
        $quote,
        $orderStoreId,
        $paymentDetails
    ) {
        $reservedOrderId = $quote->getReservedOrderId();
        
        return [
            'orderCode'        => $orderCode,
            'merchantCode'     => $this->worldpayHelper->getMerchantCode(),
            'orderDescription' => $this->_getOrderDescription($reservedOrderId),
            'currencyCode'     => $quote->getQuoteCurrencyCode(),
            'amount'           => $quote->getGrandTotal(),
            'paymentDetails'   => $this->_getVaultPaymentDetails($paymentDetails),
            'cardAddress'      => $this->_getCardAddress($quote),
            'shopperEmail'     => $quote->getCustomerEmail(),
            'acceptHeader'     => php_sapi_name() !== "cli" ?
                                    filter_input(INPUT_SERVER, 'HTTP_ACCEPT') : '',
            'userAgentHeader'  => php_sapi_name() !== "cli" ?
                                    filter_input(
                                        INPUT_SERVER,
                                        'HTTP_USER_AGENT',
                                        FILTER_SANITIZE_STRING,
                                        FILTER_FLAG_STRIP_LOW
                                    ) : '',
            'shippingAddress'  => $this->_getShippingAddress($quote),
            'billingAddress'   => $this->_getBillingAddress($quote),
            'method'           => $paymentDetails['method'],
            'orderStoreId'     => $orderStoreId,
            'shopperId'     => $quote->getCustomerId(),
            'quoteId'     => $quote->getId(),
            'riskData' => $this->getRiskDataForAuthentication($quote)
        ];
    }
   
    public function collectWebSdkOrderParameters(
        $orderCode,
        $quote,
        $orderStoreId,
        $paymentDetails
    ) {
        $reservedOrderId = $quote->getReservedOrderId();
        return [
            'orderCode'        => $orderCode,
            'merchantCode'     => $this->worldpayHelper->getMerchantCode(),
            'orderDescription' => $this->_getOrderDescription($reservedOrderId),
            'currencyCode'     => $quote->getQuoteCurrencyCode(),
            'amount'           => $quote->getGrandTotal(),
            'paymentDetails'   => $this->_getWebSdkPaymentDetails($paymentDetails),
            'cardAddress'      => $this->_getCardAddress($quote),
            'shopperEmail'     => $quote->getCustomerEmail(),
            'acceptHeader'     => php_sapi_name() !== "cli" ?
                                    filter_input(INPUT_SERVER, 'HTTP_ACCEPT') : '',
            'userAgentHeader'  => php_sapi_name() !== "cli" ?
                                    filter_input(
                                        INPUT_SERVER,
                                        'HTTP_USER_AGENT',
                                        FILTER_SANITIZE_STRING,
                                        FILTER_FLAG_STRIP_LOW
                                    ) : '',
            'shippingAddress'  => $this->_getShippingAddress($quote),
            'billingAddress'   => $this->_getBillingAddress($quote),
            'method'           => $paymentDetails['method'],
            'orderStoreId'     => $orderStoreId,
            'shopperId'     => $quote->getCustomerId(),
            'quoteId'     => $quote->getId(),
            'riskData' => $this->getRiskDataForAuthentication($quote)
        ];
    }
   
    public function collectWalletOrderParameters(
        $orderCode,
        $quote,
        $orderStoreId,
        $paymentDetails
    ) {
        $reservedOrderId = $quote->getReservedOrderId();
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/worldpay.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        $logger->info('apple response from collectWalletOrderParameters');
        //Apple Pay
        if ($paymentDetails['additional_data']['cc_type'] == 'APPLEPAY-SSL') {
            /*Handle GraphQL Request*/
            if (isset($paymentDetails['additional_data']['is_graphql'])
                && $paymentDetails['additional_data']['is_graphql']==1
                && isset($paymentDetails['additional_data']['applepayToken'])
                && !empty($paymentDetails['additional_data']['applepayToken'])) {
                $paymentMethodData = (array) json_decode(
                    $paymentDetails['additional_data']['applepayToken']
                );
                $version = $paymentMethodData['version'];
                $data = $paymentMethodData['data'];
                $signature = $paymentMethodData['signature'];
                $headerObject = $paymentMethodData['header'];
                $ephemeralPublicKey = $headerObject->ephemeralPublicKey;
                $publicKeyHash = $headerObject->publicKeyHash;
                $transactionId = $headerObject->transactionId;
                return [
                    'orderCode'        => $orderCode,
                    'merchantCode'     => $this->getMerchantDetailsForApplePay(),
                    'orderDescription' => $this->_getOrderDescription($reservedOrderId),
                    'currencyCode'     => $quote->getQuoteCurrencyCode(),
                    'amount'           => $quote->getGrandTotal(),
                    'cardAddress'      => $this->_getCardAddress($quote),
                    'shopperEmail'     => $quote->getCustomerEmail(),
                    'acceptHeader'     => php_sapi_name() !== "cli" ?
                                            filter_input(INPUT_SERVER, 'HTTP_ACCEPT') : '',
                    'userAgentHeader'  => php_sapi_name() !== "cli" ?
                                            filter_input(
                                                INPUT_SERVER,
                                                'HTTP_USER_AGENT',
                                                FILTER_SANITIZE_STRING,
                                                FILTER_FLAG_STRIP_LOW
                                            ) : '',
                    'shippingAddress'  => $this->_getShippingAddress($quote),
                    'billingAddress'   => $this->_getBillingAddress($quote),
                    'method'           => $paymentDetails['method'],
                    'orderStoreId'     => $orderStoreId,
                    'shopperId'     => $quote->getCustomerId(),
                    'quoteId'     => $quote->getId(),
                    'protocolVersion' => $version,
                    'signature' => $signature,
                    'data' => $data,
                    'ephemeralPublicKey' => $ephemeralPublicKey,
                    'publicKeyHash' => $publicKeyHash,
                    'transactionId' => $transactionId
                ];
            }
            if ($paymentDetails['additional_data']['appleResponse']) {
                $appleResponse = (array) json_decode(
                    $paymentDetails['additional_data']['appleResponse']
                );
                $paymentMethodData = (array) $appleResponse['paymentData'];

                $version = $paymentMethodData['version'];

                $data = $paymentMethodData['data'];
                $signature = $paymentMethodData['signature'];

                $headerObject = $paymentMethodData['header'];

                $ephemeralPublicKey = $headerObject->ephemeralPublicKey;
                $publicKeyHash = $headerObject->publicKeyHash;
                $transactionId = $headerObject->transactionId;

                return [
                    'orderCode'        => $orderCode,
                    'merchantCode'     => $this->getMerchantDetailsForApplePay(),
                    'orderDescription' => $this->_getOrderDescription($reservedOrderId),
                    'currencyCode'     => $quote->getQuoteCurrencyCode(),
                    'amount'           => $quote->getGrandTotal(),
                    //'paymentDetails'   => $this->_getWebSdkPaymentDetails($paymentDetails),
                    'cardAddress'      => $this->_getCardAddress($quote),
                    'shopperEmail'     => $quote->getCustomerEmail(),
                    'acceptHeader'     => php_sapi_name() !== "cli" ?
                                            filter_input(INPUT_SERVER, 'HTTP_ACCEPT') : '',
                    'userAgentHeader'  => php_sapi_name() !== "cli" ?
                                            filter_input(
                                                INPUT_SERVER,
                                                'HTTP_USER_AGENT',
                                                FILTER_SANITIZE_STRING,
                                                FILTER_FLAG_STRIP_LOW
                                            ) : '',
                    'shippingAddress'  => $this->_getShippingAddress($quote),
                    'billingAddress'   => $this->_getBillingAddress($quote),
                    'method'           => $paymentDetails['method'],
                    'orderStoreId'     => $orderStoreId,
                    'shopperId'     => $quote->getCustomerId(),
                    'quoteId'     => $quote->getId(),
                    'protocolVersion' => $version,
                    'signature' => $signature,
                    'data' => $data,
                    'ephemeralPublicKey' => $ephemeralPublicKey,
                    'publicKeyHash' => $publicKeyHash,
                    'transactionId' => $transactionId
                ];
            }
        } elseif ($paymentDetails['additional_data']['cc_type'] == 'PAYWITHGOOGLE-SSL') {
            /*Handle GraphQL Request*/
            if (isset($paymentDetails['additional_data']['is_graphql'])
                    && $paymentDetails['additional_data']['is_graphql']==1
                    && isset($paymentDetails['additional_data']['googlepayToken'])
                    && !empty($paymentDetails['additional_data']['googlepayToken'])) {
                $token = (array) json_decode(
                    $paymentDetails['additional_data']['googlepayToken']
                );
                return [
                    'orderCode'        => $orderCode,
                    'merchantCode'     => $this->worldpayHelper->getMerchantCode(),
                    'orderDescription' => $this->_getOrderDescription($reservedOrderId),
                    'currencyCode'     => $quote->getQuoteCurrencyCode(),
                    'amount'           => $quote->getGrandTotal(),
                    'paymentType'      => $paymentDetails['additional_data']['cc_type'],
                    'cardAddress'      => $this->_getCardAddress($quote),
                    'shopperEmail'     => $quote->getCustomerEmail(),
                    'acceptHeader'     => php_sapi_name() !== "cli" ?
                                            filter_input(INPUT_SERVER, 'HTTP_ACCEPT') : '',
                    'userAgentHeader'  => php_sapi_name() !== "cli" ?
                                            filter_input(
                                                INPUT_SERVER,
                                                'HTTP_USER_AGENT',
                                                FILTER_SANITIZE_STRING,
                                                FILTER_FLAG_STRIP_LOW
                                            ) : '',
                    'shippingAddress'  => $this->_getShippingAddress($quote),
                    'billingAddress'   => $this->_getBillingAddress($quote),
                    'method'           => $paymentDetails['method'],
                    'orderStoreId'     => $orderStoreId,
                    'shopperId'        => $quote->getCustomerId(),
                    'quoteId'          => $quote->getId(),
                    'protocolVersion'  => $token['protocolVersion'],
                    'signature'        => $token['signature'],
                    'signedMessage'    => $token['signedMessage'],
                    'paymentDetails'   => $paymentDetails,
                    'shopperIpAddress' => $this->_getClientIPAddress(),
                    'cusDetails' => $this->getCustomerDetailsfor3DS2($quote)
                ];
            }
            if ($paymentDetails['additional_data']['walletResponse']) {
                $walletResponse = (array)json_decode(
                    $paymentDetails['additional_data']['walletResponse']
                );
                $paymentMethodData = (array)$walletResponse['paymentMethodData'];
                $tokenizationData = (array)$paymentMethodData['tokenizationData'];
                $token = (array)json_decode($tokenizationData['token']);
                $sessionId = $this->session->getSessionId();
                $paymentDetails['sessionId'] = $sessionId;
                return [
                    'orderCode'        => $orderCode,
                    'merchantCode'     => $this->worldpayHelper->getMerchantCode(),
                    'orderDescription' => $this->_getOrderDescription($reservedOrderId),
                    'currencyCode'     => $quote->getQuoteCurrencyCode(),
                    'amount'           => $quote->getGrandTotal(),
                    'paymentType'      => $paymentDetails['additional_data']['cc_type'],
                    'cardAddress'      => $this->_getCardAddress($quote),
                    'shopperEmail'     => $quote->getCustomerEmail(),
                    'acceptHeader'     => php_sapi_name() !== "cli" ?
                                            filter_input(INPUT_SERVER, 'HTTP_ACCEPT') : '',
                    'userAgentHeader'  => php_sapi_name() !== "cli" ?
                                            filter_input(
                                                INPUT_SERVER,
                                                'HTTP_USER_AGENT',
                                                FILTER_SANITIZE_STRING,
                                                FILTER_FLAG_STRIP_LOW
                                            ) : '',
                    'shippingAddress'  => $this->_getShippingAddress($quote),
                    'billingAddress'   => $this->_getBillingAddress($quote),
                    'method'           => $paymentDetails['method'],
                    'orderStoreId'     => $orderStoreId,
                    'shopperId'        => $quote->getCustomerId(),
                    'quoteId'          => $quote->getId(),
                    'protocolVersion'  => $token['protocolVersion'],
                    'signature'        => $token['signature'],
                    'signedMessage'    => $token['signedMessage'],
                    'paymentDetails'   => $paymentDetails,
                    'shopperIpAddress' => $this->_getClientIPAddress(),
                    'cusDetails' => $this->getCustomerDetailsfor3DS2($quote)
                ];
            }
        }
    }
    
    public function collectPaymentOptionsParameters(
        $countryId,
        $paymenttype
    ) {
         return [
                'merchantCode'  => $this->worldpayHelper->getMerchantCode($paymenttype),
                'countryCode'   => $countryId,
                'paymentType'   => $paymenttype
            ];
    }

    private function _getShippingAddress($quote)
    {
        $shippingaddress = $this->_getAddress($quote->getShippingAddress());
        if (!array_filter($shippingaddress)) {
            $shippingaddress = $this->_getAddress($quote->getBillingAddress());
        }
        return $shippingaddress;
    }

    private function _getBillingAddress($quote)
    {
        return $this->_getAddress($quote->getBillingAddress());
    }

    private function _getOrderLineItems($quote)
    {
         $orderitems = [];
         $orderitems['orderTaxAmount'] = $quote->getShippingAddress()
                 ->getData('tax_amount');
         $orderitems['termsURL'] = $this->_urlBuilder->getUrl();
         $lineitem = [];
           $orderItems = $quote->getItemsCollection();
        foreach ($orderItems as $_item) {
            $lineitem = [];
            if ($_item->getParentItem()) {
                continue;
            } else {
                $rowtotal = $_item->getRowTotal();
                $totalamount = $rowtotal - $_item->getDiscountAmount();
                $totaltax = $_item->getTaxAmount() + $_item->getHiddenTaxAmount() +
                        $_item->getWeeeTaxAppliedRowAmount();
                $discountamount = $_item->getDiscountAmount();

                $lineitem['reference'] = $_item->getProductId();
                $lineitem['name'] = $_item->getName();
                $lineitem['quantity'] = (int)$_item->getQty();
                $lineitem['quantityUnit'] = $this->worldpayHelper->getQuantityUnit(
                    $_item->getProduct()
                );
                $lineitem['unitPrice'] = $rowtotal / $_item->getQty();
                $lineitem['taxRate'] =  (int)$_item->getTaxPercent();
                $lineitem['totalAmount'] = $totalamount;
                $lineitem['totalTaxAmount'] =$totaltax;
                if ($discountamount > 0) {
                     $lineitem['totalDiscountAmount'] = $discountamount;
                }
                $orderitems['lineItem'][] = $lineitem;
            }
        }

          $lineitem = [];
          $address = $quote->getShippingAddress();
        if ($address->getShippingAmount() > 0) {
             $lineitem['reference'] = 'Shipid';
             $lineitem['name'] = 'Shipping amount';
             $lineitem['quantity'] = 1;
             $lineitem['quantityUnit'] = 'shipping';
             $lineitem['unitPrice'] = $address->getShippingAmount();
             $lineitem['totalAmount'] = $address->getShippingAmount() -
                     $address->getShippingDiscountAmount();
             $totaltax = $address->getShippingTaxAmount() +
                     $address->getShippingHiddenTaxAmount();
             $lineitem['totalTaxAmount'] = $totaltax;
             $lineitem['taxRate'] =  (int)(($totaltax * 100) /
                     $address->getShippingAmount());
            if ($address->getShippingDiscountAmount() > 0) {
                  $lineitem['totalDiscountAmount'] = $address->getShippingDiscountAmount();
            }
             $orderitems['lineItem'][] = $lineitem;
        }
          return $orderitems;
    }

    private function _getAddress($address)
    {
        return [
            'firstName'   => $address->getFirstname(),
            'lastName'    => $address->getLastname(),
            'street'      => $address->getData('street'),
            'postalCode'  => $address->getPostcode(),
            'city'        => $address->getCity(),
            'state'       => $address->getRegion(),
            'countryCode' => $address->getCountryId(),
        ];
    }

    private function _getCardAddress($quote)
    {
        return $this->_getAddress($quote->getBillingAddress());
    }

    private function _getPaymentDetails($paymentDetails)
    {
        $method = $paymentDetails['method'];
        if ($paymentDetails['additional_data']['cc_type'] == "PAYWITHGOOGLE-SSL") {
            return $paymentDetails['additional_data']['cc_type'];
        }
           $details = [
               'paymentType' => $paymentDetails['additional_data']['cc_type'],
               'directSessionHref' =>isset($paymentDetails['additional_data']
                   ['directSessionHref'])
               ?$paymentDetails['additional_data']['directSessionHref']:'',
               'cardHolderName' => $paymentDetails['additional_data']['cc_name'],
               'saveMyCard' => isset($paymentDetails['additional_data']['save_my_card'])?
               $paymentDetails['additional_data']['save_my_card']:'',
               'disclaimer' => isset($paymentDetails['additional_data']['disclaimerFlag'])?
               $paymentDetails['additional_data']['disclaimerFlag']:0,
           ];

           if (isset($paymentDetails['additional_data']['cc_cid'])
                   && $paymentDetails['additional_data']['cc_cid'] !== '') {
               $details['cvc'] = $paymentDetails['additional_data']['cc_cid'];
           }
            
           
           if ((isset($paymentDetails['additional_data']['tokenId'])
              && !empty($paymentDetails['additional_data']['tokenId']))) {
               $details['tokenId'] = $paymentDetails['additional_data']['tokenId'];
               $details['tokenHref'] = $this->_getSavedCardTokenHref(
                   $paymentDetails['additional_data']['tokenId']
               );
               if ((isset($paymentDetails['additional_data']['saved_cc_cid'])
                  && $paymentDetails['additional_data']['saved_cc_cid'] !== '')) {
                   $details['cvc'] = $paymentDetails['additional_data']['saved_cc_cid'];
               } else {
                   //cvc disabled
                   $details['paymentType'] = 'TOKEN-SSL';
               }
           }
            
           if (isset($paymentDetails['additional_data']['collectionReference'])) {
               $details['collectionReference'] = $paymentDetails['additional_data']
                       ['collectionReference'];
           }
           if ($this->worldpayHelper->is3DSecureEnabled()) {
               if ($this->worldpayHelper->getChallengeWindowSize() == 'iframe') {
                   $details['url'] = $this->_urlBuilder->getUrl(
                       'worldpay/threedsecure/challengeredirectresponse',
                       ['_secure' => true]
                   );
               } else {
                   $details['url'] = $this->_urlBuilder->getUrl(
                       'worldpay/threedsecure/challengeauthresponse',
                       ['_secure' => true]
                   );
               }
           }

           $details['sessionId'] = $this->session->getSessionId();
           $details['shopperIpAddress'] = $this->_getClientIPAddress();
           $details['dynamicInteractionType'] = $this->worldpayHelper->
                   getDynamicIntegrationType($method);
           //Entity Ref
           $details['entityRef'] = $this->worldpayHelper->getMerchantEntityReference();

           return $details;
    }

    public function _getSavedCardTokenHref($tokenId)
    {
        $tokenData = $this->worldpayHelper->getSelectedSavedCardTokenData($tokenId);
        return $tokenData[0]['token'];
    }

    private function _getRedirectPaymentType($paymentDetails)
    {
        if ('CARTEBLEUE-SSL' == $paymentDetails['additional_data']['cc_type']) {
            return 'ECMC-SSL';
        }
        return $paymentDetails['additional_data']['cc_type'];
    }

    private function _getOrderDescription($reservedOrderId)
    {
        return $this->worldpayHelper->getOrderDescription();
    }

    private function _getClientIPAddress()
    {
        $REMOTE_ADDR = filter_input(INPUT_SERVER, 'REMOTE_ADDR', FILTER_VALIDATE_IP);
        $remoteAddresses = explode(',', $REMOTE_ADDR);
        return trim($remoteAddresses[0]);
    }
    
    private function _getVaultPaymentDetails($paymentDetails)
    {
        $details = [
            'brand' => $paymentDetails['card_brand'],
            'paymentType' => 'TOKEN-SSL',
            'customerId' => $paymentDetails['customer_id'],
            'tokenCode' => $paymentDetails['token'],
            'token_url' => $paymentDetails['token_url']
        ];
        if (isset($paymentDetails['collectionReference'])) {
                $details['collectionReference'] = $paymentDetails['collectionReference'];
        }
        if ($this->worldpayHelper->is3DSecureEnabled()) {
            if ($this->worldpayHelper->getChallengeWindowSize() == 'iframe') {
                $details['url'] = $this->_urlBuilder->getUrl(
                    'worldpay/threedsecure/challengeredirectresponse',
                    ['_secure' => true]
                );
            } else {
                $details['url'] = $this->_urlBuilder->getUrl(
                    'worldpay/threedsecure/challengeauthresponse',
                    ['_secure' => true]
                );
            }
        }
        $details['sessionId'] = $this->session->getSessionId();
        $details['shopperIpAddress'] = $this->_getClientIPAddress();
        $details['dynamicInteractionType'] = $this->worldpayHelper->
                                                getDynamicIntegrationType($paymentDetails['method']);
        //entity Ref
        $details['entityRef'] = $this->worldpayHelper->getMerchantEntityReference();
        // Check for Merchant Token
        //$details['token_type'] = $this->worldpayHelper->getMerchantTokenization();
        
        return $details;
    }

    public function getRiskDataForAuthentication($quote)
    {
        $address = $this->_getCardAddress($quote);
        $shippingAddress = $this->_getShippingAddress($quote);
        $now = new \DateTime();
        $createdAt = !empty($this->customerSession->getCustomer()->getCreatedAt())
                ? $this->customerSession->getCustomer()->getCreatedAt()
                : $now->format('Y-m-d H:i:s');
        $modifiedAt = !empty($this->customerSession->getCustomer()->getUpdatedAt())
                ? $this->customerSession->getCustomer()->getUpdatedAt()
                : $now->format('Y-m-d H:i:s');
             
        $details =[
         "type" => $quote->getCustomerId()?"registeredUser":"guestUser",
         "email" => $quote->getCustomerEmail(),
         "createdAt" => substr($createdAt, 0, 10),
         "modifiedAt" =>substr($modifiedAt, 0, 10),
         "firstName" => substr(preg_replace('/\s+/', '', $address['firstName']), 0, 22),
         "lastName" => substr(preg_replace('/\s+/', '', $address['lastName']), 0, 22),
         "nameMatchesAccountName" => $address['firstName'] == $shippingAddress['firstName']?
                                    "true":"false"
        ]  ;
      
        return $details;
    }
    /*
    * Get Token Payment Details
    */

    private function _getDirectTokenPaymentDetails($paymentDetails)
    {
        $details = [
            'paymentType' => 'TOKEN-SSL',
            'customerId' => isset($paymentDetails['additional_data']['customer_id'])?$paymentDetails['additional_data']['customer_id']:'',
            'tokenCode' => isset($paymentDetails['additional_data']['token'])?$paymentDetails['additional_data']['token']:'',
            'token_url' => isset($paymentDetails['token_url'])?$paymentDetails['token_url']:''
        ];
        $details['sessionId'] = $this->session->getSessionId();
        $details['shopperIpAddress'] = $this->_getClientIPAddress();
        $details['dynamicInteractionType'] = $this->worldpayHelper->
                                           getDynamicIntegrationType($paymentDetails['method']);
        //Entity reference
        $details['entityRef'] = $this->worldpayHelper->getMerchantEntityReference();
        // Check for Merchant Token
        //$details['token_type'] = $this->worldpayHelper->getMerchantTokenization();
        
        return $details;
    }
    
    private function _getWebSdkPaymentDetails($paymentDetails)
    {
        $details = [
            'paymentType' => 'TOKEN-SSL',
            'cardHolderName' => $paymentDetails['additional_data']['cc_name'],
            'sessionHref' => $paymentDetails['additional_data']['sessionHref'],
            'cvcHref' => $paymentDetails['additional_data']['cvcHref'],
            'saveMyCard' => isset($paymentDetails['additional_data']['save_my_card'])?
            $paymentDetails['additional_data']['save_my_card']:'',
            'disclaimer' => isset($paymentDetails['additional_data']['disclaimerFlag'])?
            $paymentDetails['additional_data']['disclaimerFlag']:0,
        ];
        if (isset($paymentDetails['additional_data']['tokenId'])
            && $paymentDetails['additional_data']['tokenId'] !== '') {
            $details['tokenId'] = $paymentDetails['additional_data']['tokenId'];
            $details['tokenHref'] = $this->_getSavedCardTokenHref(
                $paymentDetails['additional_data']['tokenId']
            );
        }
        if (isset($paymentDetails['additional_data']['collectionReference'])) {
                $details['collectionReference'] = $paymentDetails['additional_data']
                                                  ['collectionReference'];
        }
        if ($this->worldpayHelper->is3DSecureEnabled()) {
            if ($this->worldpayHelper->getChallengeWindowSize() == 'iframe') {
                $details['url'] = $this->_urlBuilder->getUrl(
                    'worldpay/threedsecure/challengeredirectresponse',
                    ['_secure' => true]
                );
            } else {
                $details['url'] = $this->_urlBuilder->getUrl(
                    'worldpay/threedsecure/challengeauthresponse',
                    ['_secure' => true]
                );
            }
        }
        if (isset($paymentDetails['additional_data']['is_graphql'])) {
            $details['is_graphql'] =1;
            $details['token_url'] = !empty($paymentDetails['additional_data']['tokenUrl'])?$paymentDetails['additional_data']['tokenUrl']:'';
        }
        $details['sessionId'] = $this->session->getSessionId();
        $details['shopperIpAddress'] = $this->_getClientIPAddress();
        $details['dynamicInteractionType'] = $this->worldpayHelper->
                getDynamicIntegrationType($paymentDetails['method']);
        //entity ref
        $details['entityRef'] = $this->worldpayHelper->getMerchantEntityReference();
//        $details['sessionId'] = session_id();
//        $details['shopperIpAddress'] = $this->_getClientIPAddress();
//        $details['dynamicInteractionType'] = $this->worldpayHelper->
//                                                getDynamicIntegrationType($paymentDetails['method']);
        // Check for Merchant Token
        //$details['token_type'] = $this->worldpayHelper->getMerchantTokenization();
        
        return $details;
    }

    public function getCustomerDetailsfor3DS2($quote)
    {
        $cusDetails = [];
        $now = new \DateTime();
        $cusDetails['created_at'] = !empty($this->customerSession->getCustomer()->getCreatedAt())
                ? $this->customerSession->getCustomer()->getCreatedAt() : $now->format('Y-m-d H:i:s');
        $cusDetails['updated_at'] = !empty($this->customerSession->getCustomer()->getUpdatedAt())
                ? $this->customerSession->getCustomer()->getUpdatedAt() : $now->format('Y-m-d H:i:s');
        $orderDetails = $this->worldpayHelper->getOrderDetailsByEmailId($quote->getCustomerEmail());
        $orderDetails['created_at'] = !empty($orderDetails['created_at'])
                ? $orderDetails['created_at'] : $now->format('Y-m-d H:i:s');
        $orderDetails['updated_at'] = !empty($orderDetails['updated_at'])
                ? $orderDetails['updated_at'] : $now->format('Y-m-d H:i:s');
        $orderDetails['previous_purchase'] = !empty($orderDetails['updated_at'])
                ? 'true' : 'false';
        
        $orderCount = $this->worldpayHelper->getOrdersCountByEmailId(
            $quote->getCustomerEmail()
        );
        if ($quote->getCustomerId()) {
            $savedCardCount = $this->worldpayHelper->getSavedCardsCount(
                $quote->getCustomerId()
            );
        } else {
            $savedCardCount = 0;
        }
        
        $cusDetails['shopperAccountAgeIndicator'] = $this->
                getshopperAccountAgeIndicator(
                    $cusDetails['created_at'],
                    $now->format('Y-m-d H:i:s')
                );
        $cusDetails['shopperAccountChangeIndicator'] = $this->
                getShopperAccountChangeIndicator(
                    $cusDetails['updated_at'],
                    $now->format('Y-m-d H:i:s')
                );
        $cusDetails['shopperAccountPasswordChangeIndicator'] = $this->
                getShopperAccountPasswordChangeIndicator(
                    $cusDetails['updated_at'],
                    $now->format('Y-m-d H:i:s')
                );
        $cusDetails['shopperAccountShippingAddressUsageIndicator'] = $this->
           getShopperAccountShippingAddressUsageIndicator(
               $orderDetails['created_at'],
               $now->format('Y-m-d H:i:s')
           );
        $cusDetails['shopperAccountPaymentAccountIndicator'] = $this->
           getShopperAccountPaymentAccountIndicator(
               $orderDetails['created_at'],
               $now->format('Y-m-d H:i:s')
           );
        
        $cusDetails['order_details'] = $orderDetails;
        $cusDetails['order_count'] = $orderCount;
        $cusDetails['card_count'] = $savedCardCount;
        $cusDetails['shipping_method'] = $quote->getShippingAddress()->getShippingMethod();
        
        //Fraudsight
        $cusDetails['shopperName'] = $quote->getBillingAddress()->getFirstname();
        $cusDetails['shopperId'] = $quote->getCustomerId();
        $cusDetails['birthDate']= $this ->getCustomerDOB($quote->getCustomer());
        
        return $cusDetails;
    }

    public function getCustomerDOB($customer)
    {
        $now = new \DateTime();
        $dob = $customer->getDob();
        if (isset($dob)) {
            $dob = date('Y-m-d', strtotime($dob));
            return $dob;
        }
    }

    public function getShopperAccountAgeIndicator(
        $fromDate,
        $toDate,
        $differenceFormat = '%a'
    ) {
        $datetime1 = date_create($fromDate);
        $datetime2 = date_create($toDate);
        $interval = date_diff($datetime1, $datetime2);
        $days = $interval->format($differenceFormat);
        if ($days > 0 && $days < 30) {
            return self::LESS_THAN_THIRTY_DAYS;
        } elseif ($days > 30 && $days < 60) {
            return self::THIRTY_TO_SIXTY_DAYS;
        } elseif ($days > 60) {
            return self::MORE_THAN_SIXTY_DAYS;
        } else {
            $indicator = !empty($this->customerSession->getCustomer()->getId())
                    ? self::CREATED_DURING_TRANSACTION : self::NO_ACCOUNT;
            return $indicator;
        }
    }
    
    public function getShopperAccountChangeIndicator(
        $fromDate,
        $toDate,
        $differenceFormat = '%a'
    ) {
        $datetime1 = date_create($fromDate);
        $datetime2 = date_create($toDate);
        $interval = date_diff($datetime1, $datetime2);
        $days = $interval->format($differenceFormat);
        if ($days > 0 && $days < 30) {
            return self::LESS_THAN_THIRTY_DAYS;
        } elseif ($days > 30 && $days < 60) {
            return self::THIRTY_TO_SIXTY_DAYS;
        } elseif ($days > 60) {
            return self::MORE_THAN_SIXTY_DAYS;
        } else {
            return self::CHANGED_DURING_TRANSACTION;
        }
    }
    
    public function getShopperAccountPasswordChangeIndicator(
        $fromDate,
        $toDate,
        $differenceFormat = '%a'
    ) {
        $datetime1 = date_create($fromDate);
        $datetime2 = date_create($toDate);
        $interval = date_diff($datetime1, $datetime2);
        $days = $interval->format($differenceFormat);
        if ($days > 0 && $days < 30) {
            return self::LESS_THAN_THIRTY_DAYS;
        } elseif ($days > 30 && $days < 60) {
            return self::THIRTY_TO_SIXTY_DAYS;
        } elseif ($days > 60) {
            return self::MORE_THAN_SIXTY_DAYS;
        } else {
            $indicator = !empty($this->customerSession->getCustomer()->getId())
                            ? self::CHANGED_DURING_TRANSACTION : self::NO_CHANGE;
            return $indicator;
        }
    }
    
    public function getShopperAccountShippingAddressUsageIndicator(
        $fromDate,
        $toDate,
        $differenceFormat = '%a'
    ) {
        $datetime1 = date_create($fromDate);
        $datetime2 = date_create($toDate);
        $interval = date_diff($datetime1, $datetime2);
        $days = $interval->format($differenceFormat);
        if ($days > 0 && $days < 30) {
            return self::LESS_THAN_THIRTY_DAYS;
        } elseif ($days > 30 && $days < 60) {
            return self::THIRTY_TO_SIXTY_DAYS;
        } elseif ($days > 60) {
            return self::MORE_THAN_SIXTY_DAYS;
        } else {
            return self::THIS_TRANSACTION;
        }
    }
    
    public function getMerchantDetailsForApplePay()
    {
        $merchantcode = $this->worldpayHelper->getMerchantCode();
        $entityRef = $this->worldpayHelper->getMerchantEntityReference();
        
        return ["merchantCode" => $merchantcode,
                "entityRef" => $entityRef];
    }
    
    public function getShopperAccountPaymentAccountIndicator(
        $fromDate,
        $toDate,
        $differenceFormat = '%a'
    ) {
        $datetime1 = date_create($fromDate);
        $datetime2 = date_create($toDate);
        $interval = date_diff($datetime1, $datetime2);
        $days = $interval->format($differenceFormat);
        if ($days > 0 && $days < 30) {
            return self::LESS_THAN_THIRTY_DAYS;
        } elseif ($days > 30 && $days < 60) {
            return self::THIRTY_TO_SIXTY_DAYS;
        } elseif ($days > 60) {
            return self::MORE_THAN_SIXTY_DAYS;
        } else {
            $indicator = !empty($this->customerSession->getCustomer()->getId())
                                ? self::DURING_TRANSACTION : self::NO_ACCOUNT;
            return $indicator;
        }
    }
}
