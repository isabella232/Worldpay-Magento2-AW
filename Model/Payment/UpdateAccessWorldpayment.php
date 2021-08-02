<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Payment;

use Sapient\AccessWorldpay\Model\SavedTokenFactory;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\OrderPaymentExtensionInterfaceFactory;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Model\CreditCardTokenFactory;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Vault\Model\Ui\VaultConfigProvider;
use Magento\Vault\Model\PaymentTokenManagement;

/**
 * Updating Risk gardian
 */
class UpdateAccessWorldpayment
{
    /**
     * @var \Sapient\AccessWorldpay\Model\AccessWorldpaymentFactory
     */
    protected $worldpaypayment;
    protected $paymentMethodType;
    
    /**
     * @var \Sapient\AccessWorldpay\Model\OmsDataFactory
     */
    protected $omsDataFactory;
    
    /**
     * @var \Sapient\AccessWorldpay\Model\PartialSettlementsFactory
     */
    protected $partialSettlementsFactory;
    
    /**
     * Constructor
     *
     * @param \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger
     * @param \Sapient\AccessWorldpay\Model\AccessWorldpaymentFactory $worldpaypayment
     * @param \Sapient\AccessWorldpay\Helper\Data $worldpayHelper
     */
    public function __construct(
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger,
        SavedTokenFactory $savedTokenFactory,
        \Sapient\AccessWorldpay\Model\AccessWorldpaymentFactory $worldpaypayment,
        \Sapient\AccessWorldpay\Helper\Data $worldpayHelper,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Backend\Model\Session\Quote $session,
        CreditCardTokenFactory $paymentTokenFactory,
        OrderPaymentExtensionInterfaceFactory $paymentExtensionFactory,
        \Magento\Vault\Api\PaymentTokenRepositoryInterface $paymentTokenRepository,
        EncryptorInterface $encryptor,
        \Sapient\AccessWorldpay\Model\OmsDataFactory $omsDataFactory,
        \Sapient\AccessWorldpay\Model\PartialSettlementsFactory $partialSettlementsFactory,
        \Sapient\AccessWorldpay\Model\Token\WorldpayToken $worldpayToken,
        PaymentTokenManagement $paymentTokenManagement,
        \Magento\Customer\Model\CustomerFactory $customer
    ) {
        $this->wplogger = $wplogger;
        $this->worldpaypayment = $worldpaypayment;
        $this->worldpayHelper = $worldpayHelper;
        $this->_messageManager = $messageManager;
        $this->customerSession = $customerSession;
        $this->quotesession = $session;
        $this->omsDataFactory = $omsDataFactory;
        $this->savedTokenFactory = $savedTokenFactory;
        $this->partialSettlementsFactory = $partialSettlementsFactory;
        $this->paymentTokenFactory = $paymentTokenFactory;
        $this->paymentTokenRepository = $paymentTokenRepository;
        $this->encryptor = $encryptor;
        $this->paymentExtensionFactory = $paymentExtensionFactory;
        $this->_worldpayToken = $worldpayToken;
        $this->paymentTokenManagement = $paymentTokenManagement;
        $this->customer = $customer;
    }

    /**
     * Updating Risk gardian
     *
     * @param \Sapient\AccessWorldpay\Model\Response\DirectResponse $directResponse
     */
    public function updateAccessWorldpayPayment(
        $orderId,
        $orderCode,
        \Sapient\AccessWorldpay\Model\Response\DirectResponse $directResponse,
        \Magento\Payment\Model\InfoInterface $paymentObject
    ) {
                    
        $response = $directResponse->getXml();
        if ($response && isset($response->outcome) && isset($directResponse->getXml()->_links)) {
            $responseLinks = $directResponse->getXml()->_links;
 
            $cancelLink = $settleLink = $partialSettleLink = $eventsLink = '';
            //foreach($responseLinks as $key => $link){
            if (isset($responseLinks->cancel->href)) {
                $cancelLink = $responseLinks->cancel->href;
            }
            if (isset($responseLinks->settle->href)) {
                $settleLink = $responseLinks->settle->href;
            }
            if (isset($responseLinks->partialSettle->href)) {
                $partialSettleLink = $responseLinks->partialSettle->href;
            }
            if (isset($responseLinks->events->href)) {
                $eventsLink = $responseLinks->events->href;
            }
            //}
            $omsData['order_increment_id'] = $orderId;
            $omsData['awp_order_code'] = $orderCode;
            $omsData['awp_payment_status'] = $response->outcome;
            $omsData['awp_cancel_param'] = $cancelLink;
            $omsData['awp_settle_param'] = $settleLink;
            $omsData['awp_partial_settle_param'] = $partialSettleLink;
            $omsData['awp_events_param'] = $eventsLink;
            $oms = $this->omsDataFactory->create();
            $oms->setData($omsData)->save();
            
            //$this->updatePaymentData($response, $orderCode);
            
            return true;
        }
        return false;
    }
    
    public function saveVerifiedToken($tokenDetailResponseToArray, $payment)
    {
        
        $savedTokenFactory = $this->savedTokenFactory->create();
        // checking tokenization exist or not
        
            $tokenDataExist = '';
            
            $tokenDataExist = $savedTokenFactory->getCollection()
               ->addFieldToFilter('customer_id', $tokenDetailResponseToArray['customer_id'])
                ->addFieldToFilter('token_id', $tokenDetailResponseToArray['tokenId'])
                ->getFirstItem()->getData();
            
        if ($tokenDataExist) {
            $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/worldpay.log');
            $logger = new \Zend\Log\Logger();
            $logger->addWriter($writer);
            $logger->info('token is already exists ..........................................');
            if (isset($tokenDetailResponseToArray['tokenId'])) {
                //Manage Exceed Update Limit
                if (isset($tokenDetailResponseToArray['conflictResponse'])
                    && (isset($tokenDetailResponseToArray['conflictResponse']['nameConflict'])
                        && isset($tokenDetailResponseToArray['conflictResponse']['dateConflict'])
                        && $tokenDetailResponseToArray['conflictResponse']['nameConflict']==429)
                    || isset($tokenDetailResponseToArray['conflictResponse'])
                    && (isset($tokenDetailResponseToArray['conflictResponse']['nameConflict'])
                        && !isset($tokenDetailResponseToArray['conflictResponse']['dateConflict'])
                        && $tokenDetailResponseToArray['conflictResponse']['nameConflict']==429)
                    || isset($tokenDetailResponseToArray['conflictResponse'])
                    && (!isset($tokenDetailResponseToArray['conflictResponse']['nameConflict'])
                        && isset($tokenDetailResponseToArray['conflictResponse']['dateConflict'])
                        && $tokenDetailResponseToArray['conflictResponse']['dateConflict']==429)) {
                    $this->_messageManager->addError(
                        __($this->worldpayHelper->getCreditCardSpecificException('CCAM21'))
                    );
                    $logger->info($this->worldpayHelper->getCreditCardSpecificException('CCAM21'));
                    return;
                }
                $customerData = $this->customer->create()->load($tokenDetailResponseToArray['customer_id']);
                //update Token
                $logger->info('Token Already Exists ..........................................');
                $this->_worldpayToken->updateTokenByCustomer(
                    $this->_loadTokenModel($tokenDetailResponseToArray),
                    $customerData
                );
                //update vault token
                $this->_applyVaultTokenUpdate($tokenDetailResponseToArray);
                $this->_messageManager->addNotice(__($this->worldpayHelper->getMyAccountSpecificexception('MCAM11')));
                return;
            }
                
        }
                $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/worldpay.log');
                $logger = new \Zend\Log\Logger();
                $logger->addWriter($writer);
                $logger->info('saving the token ..........................................');
           
            $savedTokenFactory->setTokenId($tokenDetailResponseToArray['tokenId']);
            $savedTokenFactory->setDescription($tokenDetailResponseToArray['description']);
            $savedTokenFactory->setToken($tokenDetailResponseToArray['tokenPaymentInstrument']['href']);
            $savedTokenFactory->setTransactionReference(
                $tokenDetailResponseToArray['schemeTransactionReference']
            );
            $savedTokenFactory->setTokenExpiryDate($tokenDetailResponseToArray['tokenExpiryDateTime']);
            $savedTokenFactory->setCardNumber($tokenDetailResponseToArray['paymentInstrument']['cardNumber']);
            $savedTokenFactory->setCardholderName(
                $tokenDetailResponseToArray['paymentInstrument']['cardHolderName']
            );
            $savedTokenFactory->setCardExpiryMonth(
                $tokenDetailResponseToArray['paymentInstrument']['cardExpiryDate']['month']
            );
            $savedTokenFactory->setCardExpiryYear(
                $tokenDetailResponseToArray['paymentInstrument']['cardExpiryDate']['year']
            );
            
            $savedTokenFactory->setMethod('worldpay_cc');
            $savedTokenFactory->setCustomerId($tokenDetailResponseToArray['customer_id']);
            $savedTokenFactory->setDisclaimerFlag($tokenDetailResponseToArray['disclaimer']);
            $savedTokenFactory->setCardBrand($tokenDetailResponseToArray['card_brand']);
            
            $savedTokenFactory->save();
            $logger->info('Saving is done ................................................');
            $logger->info('Saving to Vault:START ................................................');
            $this->setVaultPaymentToken($tokenDetailResponseToArray, $payment);
            $logger->info('Saving to Vault:END ................................................');
    }
    /**
     * Updating Refund data
     *
     * @param $orderIncrementId
     */
    public function updatePaymentSettlement($directResponse)
    {
        $response = new \SimpleXmlElement($directResponse);
        
        if ($response && isset($response->outcome) && isset($response->_links)) {
            $orderCode = $response->orderCode;
            $responseLinks = $response->_links;
            $refundLink = $partialRefundLink = '';
            if (isset($responseLinks->refund->href)) {
                $refundLink = $responseLinks->refund->href;
            }
            if (isset($responseLinks->partialRefund->href)) {
                $partialRefundLink = $responseLinks->partialRefund->href;
            }
            $omsData['awp_refund_param'] = $refundLink;
            $omsData['awp_partial_refund_param'] = $partialRefundLink;
            $result = $this->omsDataFactory->create()->loadByAccessWorldpayOrderCode($orderCode);
            $result->setAwpRefundParam($refundLink);
            $result->setAwpPartialRefundParam($partialRefundLink);
            $result->save();
            return true;
        }
        return false;
    }
    
    /**
     * Updating Refund data
     *
     * @param $orderIncrementId
     */
    public function updatePartialPaymentSettlement(
        $orderId,
        $directResponse,
        InfoInterface $paymentObject
    ) {
        $response = new \SimpleXmlElement($directResponse);
        $orderCode = $response->orderCode;
        
        if ($response && isset($response->outcome) && isset($response->_links)) {
            $responseLinks = $response->_links;
            $refundLink = $partialRefundLink = '';
            
            $refundLink = $responseLinks->refund->href;
            $partialRefundLink = $responseLinks->partialRefund->href;
            $partialSettleLink = $responseLinks->partialSettle->href;
            $cancelLink = $responseLinks->cancel->href;
            $eventsLink = $responseLinks->events->href;
            
            $omsData['order_increment_id'] = $orderId;
            $omsData['order_invoice_id'] = $paymentObject->getLastTransId();
            $omsData['order_item_id'] = $paymentObject->getId();
            $omsData['awp_order_code'] = $orderCode;
            $omsData['awp_lineitem_cancel_param'] = $cancelLink;
            $omsData['awp_lineitem_refund_param'] = $refundLink;
            $omsData['awp_lineitem_partial_refund_param'] = $partialRefundLink;
            $omsData['awp_lineitem_partial_settle_param'] = $partialSettleLink;
            $omsData['awp_lineitem_events_param'] = $eventsLink;
            $result = $this->partialSettlementsFactory->create();
//            $result->setAwpRefundParam($refundLink);
//            $result->setAwpPartialRefundParam($partialRefundLink);
            $result->setData($omsData)->save();
            return true;
        }
        return false;
    }
    
    public function setVaultPaymentToken($tokenDetailResponseToArray, $paymentObject)
    {
        $paymentToken = $this->getVaultPaymentToken($tokenDetailResponseToArray);
        if (null !== $paymentToken) {
            $extensionAttributes = $this->getExtensionAttributes($paymentObject);
            $this->getAdditionalInformation($paymentObject);
            $extensionAttributes->setVaultPaymentToken($paymentToken);
            if ($this->worldpayHelper->is3DSecureEnabled()) {
                $this->paymentTokenRepository->save($paymentToken);
            }
        }
    }
    
    private function getExtensionAttributes(InfoInterface $payment)
    {
        $extensionAttributes = $payment->getExtensionAttributes();
        if (null === $extensionAttributes) {
            $extensionAttributes = $this->paymentExtensionFactory->create();
            $payment->setExtensionAttributes($extensionAttributes);
        }
        return $extensionAttributes;
    }
    
    protected function getVaultPaymentToken($tokenDetailResponseToArray)
    {
        // Check token existing in gateway response
        $token = $tokenDetailResponseToArray['tokenId'];
        if (empty($token)) {
            return null;
        }

        /** @var PaymentTokenInterface $paymentToken */
        $paymentToken = $this->paymentTokenFactory->create();
        $paymentToken->setGatewayToken($token);
        $paymentToken->setExpiresAt($tokenDetailResponseToArray['tokenExpiryDateTime']);
        $paymentToken->setIsVisible(true);
        $paymentToken->setTokenDetails($this->convertDetailsToJSON([
            'type' => $tokenDetailResponseToArray['card_brand'].'-SSL',
            'maskedCC' => $this->getLastFourNumbers($tokenDetailResponseToArray['paymentInstrument']['cardNumber']),
            'expirationDate'=> $this->getExpirationMonthAndYear($tokenDetailResponseToArray)
        ]));
        
        //3ds related
        if ($this->worldpayHelper->is3DSecureEnabled()) {
            $paymentToken->setPaymentMethodCode('worldpay_cc');
            $paymentToken->setCustomerId($tokenDetailResponseToArray['customer_id']);
            $paymentToken->setPublicHash($this->generatePublicHash($paymentToken));
        }
        return $paymentToken;
    }
    
    private function getAdditionalInformation(InfoInterface $payment)
    {
        $additionalInformation = $payment->getAdditionalInformation();
        if (null === $additionalInformation) {
            $additionalInformation = $this->paymentExtensionFactory->create();
        }
        $additionalInformation[VaultConfigProvider::IS_ACTIVE_CODE] = true;
        $payment->setAdditionalInformation($additionalInformation);
    }
    
    public function getLastFourNumbers($number)
    {
        return substr($number, -4);
    }
    
    public function getExpirationMonthAndYear($tokenDetailResponseToArray)
    {
        $month = $tokenDetailResponseToArray['paymentInstrument']['cardExpiryDate']['month'];
        $year = $tokenDetailResponseToArray['paymentInstrument']['cardExpiryDate']['year'];
        return $month.'/'.$year;
    }
    
    public function convertDetailsToJSON($details)
    {
        $json = \Zend_Json::encode($details);
        return $json ? $json : '{}';
    }
    
    public function saveVerifiedTokenForMyAccount($tokenDetailResponseToArray)
    {

        $savedTokenFactory = $this->savedTokenFactory->create();
        // checking tokenization exist or not

        $tokenDataExist = '';

        $tokenDataExist = $savedTokenFactory->getCollection()
                        ->addFieldToFilter('customer_id', $tokenDetailResponseToArray['customer_id'])
                        ->addFieldToFilter('token_id', $tokenDetailResponseToArray['tokenId'])
                        ->getFirstItem()->getData();

        if ($tokenDataExist) {
            $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/worldpay.log');
            $logger = new \Zend\Log\Logger();
            $logger->addWriter($writer);
            $logger->info('token is already exists ..........................................');
            $this->_messageManager->addNotice(__($this->worldpayHelper->getMyAccountSpecificexception('MCAM13')));
            return false;
        }
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/worldpay.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        $logger->info('saving the token ..........................................');

        $savedTokenFactory->setTokenId($tokenDetailResponseToArray['tokenId']);
        $savedTokenFactory->setDescription($tokenDetailResponseToArray['description']);
        $savedTokenFactory->setToken($tokenDetailResponseToArray['tokenPaymentInstrument']['href']);
        $savedTokenFactory->setTransactionReference(
            $tokenDetailResponseToArray['schemeTransactionReference']
        );
        $savedTokenFactory->setTokenExpiryDate($tokenDetailResponseToArray['tokenExpiryDateTime']);
        $savedTokenFactory->setCardNumber($tokenDetailResponseToArray['paymentInstrument']['cardNumber']);
        $savedTokenFactory->setCardholderName(
            $tokenDetailResponseToArray['paymentInstrument']['cardHolderName']
        );
        $savedTokenFactory->setCardExpiryMonth(
            $tokenDetailResponseToArray['paymentInstrument']['cardExpiryDate']['month']
        );
        $savedTokenFactory->setCardExpiryYear(
            $tokenDetailResponseToArray['paymentInstrument']['cardExpiryDate']['year']
        );
        $savedTokenFactory->setMethod('worldpay_cc');
        $savedTokenFactory->setCustomerId($tokenDetailResponseToArray['customer_id']);
        /*Disclaimer*/
        $savedTokenFactory->setDisclaimerFlag($tokenDetailResponseToArray['disclaimer']);
        $savedTokenFactory->setCardBrand($tokenDetailResponseToArray['card_brand']);
        $savedTokenFactory->save();
        $logger->info('Saving is done ................................................');
        $logger->info('Saving to Vault:START ................................................');
        $this->setVaultPaymentTokenMyAccount($tokenDetailResponseToArray);
        $logger->info('Saving to Vault:END ................................................');
        return true;
    }

    protected function setVaultPaymentTokenMyAccount($tokenDetailResponseToArray)
    {
        // Check token existing in gateway response
        $token = $tokenDetailResponseToArray['tokenId'];
        if (empty($token)) {
            return null;
        }

        /** @var PaymentTokenInterface $paymentToken */
        $paymentToken = $this->paymentTokenFactory->create();
        $paymentToken->setGatewayToken($token);
        $paymentToken->setExpiresAt($tokenDetailResponseToArray['tokenExpiryDateTime']);
        $paymentToken->setIsVisible(true);
        $paymentToken->setTokenDetails($this->convertDetailsToJSON([
                    'type' => $tokenDetailResponseToArray['card_brand'].'-SSL',
                    'maskedCC' => $this->getLastFourNumbers(
                        $tokenDetailResponseToArray['paymentInstrument']['cardNumber']
                    ),
                    'expirationDate' => $this->getExpirationMonthAndYear($tokenDetailResponseToArray)
        ]));
        $paymentToken->setIsActive(true);
        $paymentToken->setPaymentMethodCode('worldpay_cc');
        $paymentToken->setCustomerId($this->customerSession->getCustomerId());
        $paymentToken->setPublicHash($this->generatePublicHash($paymentToken));
        $this->paymentTokenRepository->save($paymentToken);
    }

    protected function generatePublicHash(PaymentTokenInterface $paymentToken)
    {
        $hashKey = $paymentToken->getGatewayToken();
        if ($paymentToken->getCustomerId()) {
            $hashKey = $paymentToken->getCustomerId();
        }
        $hashKey .= $paymentToken->getPaymentMethodCode()
                . $paymentToken->getType()
                . $paymentToken->getTokenDetails();

        return $this->encryptor->getHash($hashKey);
    }

    /**
     * load Token
     * @return Sapient/AccessWorldPay/Model/Token
     */
    public function _loadTokenModel($tokenUpdateData)
    {
        $this->wplogger->info('Load Token Model');
        $tokenId = $tokenUpdateData['tokenId'];
        $token = $this->savedTokenFactory->create()->loadByTokenCode($tokenId);
        if (!empty($tokenUpdateData)) {
            $token->setToken($tokenUpdateData['tokenPaymentInstrument']['href']);
            $token->setTokenId(trim($tokenUpdateData['tokenId']));
            $token->setCardholderName(trim($tokenUpdateData['paymentInstrument']['cardHolderName']));
            $token->setCardExpiryMonth($tokenUpdateData['paymentInstrument']['cardExpiryDate']['month']);
            $token->setCardExpiryYear($tokenUpdateData['paymentInstrument']['cardExpiryDate']['year']);
            $token->setDisclaimerFlag($tokenUpdateData['disclaimer']);
            $token->setTokenExpiryDate($tokenUpdateData['tokenExpiryDateTime']);
        }
        return $token;
    }

    public function _applyVaultTokenUpdate($tokenDetail)
    {
        $existingVaultPaymentToken = $this->paymentTokenManagement->getByGatewayToken(
            $this->_loadTokenModel($tokenDetail)->getTokenId(),
            'worldpay_cc',
            $tokenDetail['customer_id']
        );
            $this->_saveVaultToken($existingVaultPaymentToken, $tokenDetail);
    }
    
    public function _saveVaultToken(PaymentTokenInterface $vaultToken, $tokenDetail)
    {
        $vaultToken->setTokenDetails($this->convertDetailsToJSON([
            'type' => $this->worldpayHelper->getCardType($tokenDetail['paymentInstrument']['cardNumber']),
            'maskedCC' => $this->getLastFourNumbers($this->_loadTokenModel($tokenDetail)->getCardNumber()),
            'expirationDate'=> $this->getExpirationMonthAndYear($tokenDetail)
        ]));
        try {
            $this->paymentTokenRepository->save($vaultToken);
        } catch (Exception $e) {
            $this->wplogger->error($e->getMessage());
            $this->messageManager->addException($e, __('Error: ').$e->getMessage());
        }
    }
    
    public function _setCardOnFileAuthorizeLink($tokenId, $cardOnFileAuthLink)
    {
        $token = $this->savedTokenFactory->create()->loadByTokenCode($tokenId);
        if (isset($cardOnFileAuthLink)) {
            $token->setCardonfileAuthLink($cardOnFileAuthLink);
            $token->save();
        }
    }
}
