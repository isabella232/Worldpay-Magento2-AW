<?php

namespace Sapient\AccessWorldpay\Controller\ThreeDSecure;

class ChallengeRedirectResponse extends \Magento\Framework\App\Action\Action
{
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Sapient\AccessWorldpay\Model\Authorisation\ThreeDSecureChallenge $threedcredirectresponse,
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory
    ) {
        $this->wplogger = $wplogger;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->urlBuilder = $context->getUrl();
        $this->checkoutSession = $checkoutSession;
        $this->orderFactory = $orderFactory;
        $this->orderSender = $orderSender;
        $this->threedscredirectresponse = $threedcredirectresponse;
        $this->_resultPageFactory = $resultPageFactory;
        parent::__construct($context);
    }

    /**
     * Accepts callback from worldpay's 3DS2 challenge iframe page.
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
        $resultPage = $this->_resultPageFactory->create();
                $block = $resultPage->getLayout()
                ->createBlock(\Sapient\AccessWorldpay\Block\Checkout\Hpp\ChallengeIframe::class)
                ->setTemplate('Sapient_AccessWorldpay::checkout/hpp/challengeiframe.phtml')
                ->toHtml();
                return $this->getResponse()->setBody($block);
    }
}
