<?php


namespace Sapient\AccessWorldpay\Controller\ThreeDSecure;

class ChallengeAuthResponse extends \Magento\Framework\App\Action\Action
{
    protected $helper;
    
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Sapient\AccessWorldpay\Model\Authorisation\ThreeDSecureChallenge $threedcredirectresponse,
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger,
        \Magento\Framework\Session\SessionManagerInterface $session,
        \Sapient\AccessWorldpay\Helper\Data $worldpayHelper
    ) {
        $this->wplogger = $wplogger;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->urlBuilder = $context->getUrl();
        $this->checkoutSession = $checkoutSession;
        $this->orderFactory = $orderFactory;
        $this->orderSender = $orderSender;
        $this->threedscredirectresponse = $threedcredirectresponse;
        $this->session = $session;
        $this->worldpayHelper = $worldpayHelper;
        //$this->helper = $helper;
        parent::__construct($context);
    }
    
     /**
      * Accepts callback from worldpay's 3DS2 Secure page. If payment has been
      * authorised, update order and redirect to the checkout success page.
      */
    public function execute()
    {
        if (isset($_COOKIE['PHPSESSID'])) {
            $phpsessId = $_COOKIE['PHPSESSID'];
            if (phpversion() >= '7.3.0') {
                $domain = parse_url($this->_url->getUrl(), PHP_URL_HOST);
                setcookie("PHPSESSID", $phpsessId, [
                'expires' => time() + 3600,
                'path' => '/',
                'domain' => $domain,
                'secure' => true,
                'httponly' => true,
                'samesite' => 'None',
                ]);
            } else {
                setcookie("PHPSESSID", $phpsessId, time() + 3600, "/; SameSite=None; Secure;");
            }
        }
        $directOrderParams = $this->checkoutSession->getDirectOrderParams();
        $threeDSecureParams = $this->checkoutSession->get3DschallengeData();
        $this->checkoutSession->unsDirectOrderParams();
        $this->checkoutSession->uns3DschallengeData();
        try {
             
            $this->threedscredirectresponse->continuePost3dSecure2AuthorizationProcess(
                $directOrderParams,
                $threeDSecureParams
            );
        } catch (\Exception $e) {
            $this->checkoutSession->setInstantPurchaseMessage('');
            $this->wplogger->error($e->getMessage());
            $this->wplogger->error('3DS2 Failed');
            $this->_messageManager->addError(__(
                $this->worldpayHelper->getCreditCardSpecificException('CCAM10')
            ));
            $this->messageManager->addError(__('Unfortunately the order could not be processed. "
                                            . "Please contact us or try again later.'));
            if ($this->checkoutSession->getInstantPurchaseOrder()) {
                $redirectUrl = $this->checkoutSession->getInstantPurchaseRedirectUrl();
                $this->checkoutSession->unsInstantPurchaseRedirectUrl();
                $this->checkoutSession->unsInstantPurchaseOrder();
                return $this->resultRedirectFactory->create()->setUrl($redirectUrl);
            } else {
                $this->getResponse()->setRedirect($this->urlBuilders->
                        getUrl('checkout/cart', ['_secure' => true]));
            }
        }
        if ($this->checkoutSession->getInstantPurchaseOrder()) {
            $redirectUrl = $this->checkoutSession->getInstantPurchaseRedirectUrl();
            $this->checkoutSession->unsInstantPurchaseRedirectUrl();
            $this->checkoutSession->unsInstantPurchaseOrder();
            $message=$this->checkoutSession->getInstantPurchaseMessage();
            if ($message) {
                $this->wplogger->info($message);
                $this->checkoutSession->unsInstantPurchaseMessage();
                $this->messageManager->addSuccessMessage($message);
            }
            return $this->resultRedirectFactory->create()->setUrl($redirectUrl);
        } else {
            $redirectUrl = $this->checkoutSession->getWpResponseForwardUrl();
            $this->checkoutSession->unsWpResponseForwardUrl();
            $this->getResponse()->setRedirect($redirectUrl);
        }
    }
}
