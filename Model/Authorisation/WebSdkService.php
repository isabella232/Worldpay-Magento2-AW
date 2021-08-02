<?php

namespace Sapient\AccessWorldpay\Model\Authorisation;

use Exception;
use Magento\Framework\Exception\LocalizedException;

class WebSdkService extends \Magento\Framework\DataObject
{
    protected $checkoutSession;
    protected $updateWorldPayPayment;
    
     // get 3ds2 params from the configuration and set to checkout session
    public function get3DS2ConfigValues()
    {
        $data = [];
        $data['challengeWindowType'] = $this->worldpayHelper->getChallengeWindowSize();
        return $data;
    }
    
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
        \Sapient\AccessWorldpay\Helper\Data $worldpayHelper,
        \Magento\Customer\Model\Session $customerSession
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
        $this->customerSession = $customerSession;
    }

    public function authorizePayment(
        $mageOrder,
        $quote,
        $orderCode,
        $orderStoreId,
        $paymentDetails,
        $payment
    ) {
        if ($this->worldpayHelper->is3DSecureEnabled()
            && !isset($paymentDetails['additional_data']['is_graphql'])) {
            $directOrderParams = $this->mappingservice->collectWebSdkOrderParameters(
                $orderCode,
                $quote,
                $orderStoreId,
                $paymentDetails
            );
            
            $this->wplogger->info('WebSdkService Authorizepayment initiated');
            if (!(isset($directOrderParams['paymentDetails']['tokenId']))) {
                $directOrderParams = $this->paymentservicerequest->
                        _createVerifiedTokenFor3Ds($directOrderParams);
            }
            $this->checkoutSession->setDirectOrderParams($directOrderParams);
            $threeDSecureConfig = $this->get3DS2ConfigValues();
            $this->checkoutSession->set3DS2Config($threeDSecureConfig);
        } else {
            $this->wplogger->info('WebSdkService Authorizepayment initiated');
            $directOrderParams = $this->mappingservice->collectWebSdkOrderParameters(
                $orderCode,
                $quote,
                $orderStoreId,
                $paymentDetails
            );
            $response = $this->paymentservicerequest->websdkorder($directOrderParams);
            $directResponse = $this->directResponse->setResponse($response);
            $orderId = $quote->getReservedOrderId();
        // Normal order goes here.
            $this->updateWorldPayPayment->create()->updateAccessWorldpayPayment(
                $orderId,
                $orderCode,
                $directResponse,
                $payment
            );
            $this->_applyPaymentUpdate($directResponse, $payment);
            $customerId = $quote->getCustomer()->getId();
            if ($customerId) {
                $this->saveToken($customerId, $payment, $paymentDetails);
            } elseif (!empty($this->customerSession->getVerifiedDetailedToken())
                    || (isset($paymentDetails['additional_data']['is_graphql'])
                        && !empty($paymentDetails['token_url']))) {
                //delete verified token for guest user
                $graphqlToken = isset($paymentDetails['additional_data']['is_graphql'])?$paymentDetails['token_url']:'';
                $verifiedToken = $this->customerSession->getVerifiedDetailedToken();
                $this->customerSession->unsVerifiedDetailedToken();
                $this->wplogger->info(" Inititating Delete Token for Guest User....");
                $token = $verifiedToken?$verifiedToken:$graphqlToken;
                   $this->paymentservicerequest->getTokenDelete($token);
            }
        
        }
    }

    private function _applyPaymentUpdate(
        \Sapient\AccessWorldpay\Model\Response\DirectResponse $directResponse,
        $payment
    ) {
        $paymentUpdate = $this->paymentservice->createPaymentUpdateFromWorldPayXml(
            $directResponse->getXml()
        );
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
    
    public function saveToken($customerId, $payment, $paymentDetails)
    {
        if (isset($paymentDetails['additional_data']['is_graphql'])) {
            if ($paymentDetails['additional_data']['save_card'] !=='1' && !empty($paymentDetails['token_url'])) {
            if (!isset($paymentDetails['additional_data']['use_savedcard'])
                    && $this->worldpayHelper->checkIfTokenExists($paymentDetails['token_url'])) {
                $this->wplogger->info(" User already has this card saved....");
            }else {
               $this->wplogger->info(
                " Inititating Delete Token for Registered customer with customerID="
                .$customerId." ...."
            );
               $this->paymentservicerequest->getTokenDelete($paymentDetails['token_url']); 
            }
            }else if ($paymentDetails['additional_data']['save_card'] =='1' && !empty($paymentDetails['token_url'])) {
                $this->saveTokenForGraphQl($paymentDetails['token_url'],$customerId,$payment);
            }
            
        }elseif ($this->customerSession->getIsSavedCardRequested()
            && empty($paymentDetails['additional_data']['tokenId'])) {
            $tokenDetailResponseToArray = $this->customerSession->getDetailedToken();
            $this->updateWorldPayPayment->create()
                    ->saveVerifiedToken($tokenDetailResponseToArray, $payment);
            //unset the session variables
            $this->customerSession->unsIsSavedCardRequested();
            $this->customerSession->unsDetailedToken();
        } elseif (!empty($this->customerSession->getVerifiedDetailedToken())
                    && $this->worldpayHelper->checkIfTokenExists(
                        $this->customerSession->getVerifiedDetailedToken()
                    )) {
            $this->wplogger->info(" User already has this card saved....");
            $this->customerSession->unsVerifiedDetailedToken();
        } elseif (empty($this->customerSession->getUsedSavedCard())) {
            //delete verified token for registered user when save_card=0
            $verifiedToken = $this->customerSession->getVerifiedDetailedToken();
            $this->customerSession->unsVerifiedDetailedToken();
            $this->wplogger->info(
                " Inititating Delete Token for Registered customer with customerID="
                .$customerId." .............."
            );
               $this->paymentservicerequest->getTokenDelete($verifiedToken);
        } else {
            $this->customerSession->unsUsedSavedCard();
        }
    }
    
    public function saveTokenForGraphQl ($token_url,$customerId,$payment)
    {
        $getTokenDetails = $this->paymentservicerequest->_getDetailedVerifiedToken(
                $token_url, $this->worldpayHelper->getXmlUsername(), $this->worldpayHelper->getXmlPassword()
        );

        $tokenDetailResponseToArray = json_decode($getTokenDetails, true);
        //make a call to getBrand Details,content-type is different
        $getTokenBrandDetails = $this->paymentservicerequest->getDetailedTokenForBrand(
                $token_url, $this->worldpayHelper->getXmlUsername(), $this->worldpayHelper->getXmlPassword()
        );
        $brandResponse = json_decode($getTokenBrandDetails, true);
        $tokenDetailResponseToArray['card_brand'] = $brandResponse['paymentInstrument']['brand'];
        $tokenDetailResponseToArray['customer_id'] = $customerId;
        $tokenDetailResponseToArray['disclaimer'] = 0;
        $this->updateWorldPayPayment->create()->
                saveVerifiedToken($tokenDetailResponseToArray, $payment);
    }
}
