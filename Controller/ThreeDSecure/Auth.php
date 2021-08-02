<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Controller\ThreeDSecure;

use Magento\Framework\App\Action\Context;
use Exception;

class Auth extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;
    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \Magento\Checkout\Model\Session $checkoutSession
     */
    public function __construct(
        Context $context,
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Sapient\AccessWorldpay\Model\Authorisation\ThreeDSecureService $threeDservice,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Sapient\AccessWorldpay\Model\Authorisation\ThreeDSecureChallenge $threeDchallengeService,
        \Sapient\AccessWorldpay\Helper\Data $helper,
        \Magento\Framework\View\Asset\Repository $assetRepo
    ) {
        $this->wplogger = $wplogger;
        $this->checkoutSession = $checkoutSession;
        $this->_resultPageFactory = $resultPageFactory;
        $this->threeDservice = $threeDservice;
        $this->_assetRepo = $assetRepo;
        $this->threeDchallengeService = $threeDchallengeService;
        $this->helper = $helper;
        parent::__construct($context);
    }

    /**
     * Renders the 3D Secure  page, responsible for forwarding
     * all necessary order data to worldpay.
     */
    public function execute()
    {
       // 3ds2 flow
        if ($this->helper->is3DSecureEnabled() && !empty($this->checkoutSession->getDirectOrderParams())) {
            $threeDparams = $this->checkoutSession->get3Dsparams();
            $directOrderParams = $this->checkoutSession->getDirectOrderParams();
            $threeDSecureChallengeConfig = $this->checkoutSession->get3DS2Config();
            $iframe = false;
            $authenticationurl = isset($threeDparams['_links']['3ds:authenticate']['href'])?
                    $threeDparams['_links']['3ds:authenticate']['href']:'';
         // Chrome 84 releted updates for 3DS
            $phpsessId = $_COOKIE['PHPSESSID'];
            if (phpversion() < '7.3.0') {
                setcookie("PHPSESSID", $phpsessId, time() + 3600, "/; SameSite=None; Secure;");
            } else {
                $domain = parse_url($this->_url->getUrl(), PHP_URL_HOST);
                setcookie("PHPSESSID", $phpsessId, [
                'expires' => time() + 3600,
                'path' => '/',
                'domain' => $domain,
                'secure' => true,
                'httponly' => true,
                'samesite' => 'None',
                ]);
            }
            if (isset($threeDSecureChallengeConfig['challengeWindowType'])
                && $threeDSecureChallengeConfig['challengeWindowType'] == 'iframe') {
                  $iframe = true;
            }
            if ($authenticationurl != null) {
                $this->threeDservice->authenticate3Ddata($authenticationurl, $directOrderParams);
                $this->checkoutSession->unsDdcJwt();
                $this->checkoutSession->uns3Dsparams();
                $this->checkoutSession->uns3DS2Config();
                $threeDsChallengeData = $this->checkoutSession->get3DschallengeData();
          
                if ($threeDsChallengeData['outcome'] ==="challenged") {
                    $this->createChallengeform($threeDsChallengeData, $iframe);
          
              //$orderId = $this->checkoutSession->getAuthOrderId();
                } else {
                    $message=$this->checkoutSession->getInstantPurchaseMessage();
                    $this->threeDchallengeService->continuePost3dSecure2AuthorizationProcess(
                        $directOrderParams,
                        $threeDsChallengeData
                    );
                    if ($this->checkoutSession->getInstantPurchaseOrder()) {
                          $redirectUrl = $this->checkoutSession->getInstantPurchaseRedirectUrl();
                          $this->checkoutSession->unsInstantPurchaseRedirectUrl();
                          $this->checkoutSession->unsInstantPurchaseOrder();
                          //$message=$this->checkoutSession->getInstantPurchaseMessage();
                        if ($message) {
                            $this->checkoutSession->unsInstantPurchaseMessage();
                            $this->messageManager->addSuccessMessage($message);
                        }
                        return $this->resultRedirectFactory->create()->setUrl($redirectUrl);
                    } else {
                        return $this->resultRedirectFactory->create()->
                                setPath($this->checkoutSession->getWpResponseForwardUrl());
                    }
                }
            }
        } else {
            //Non -3ds2 flow
            return $this->resultRedirectFactory->create()->
                    setPath('checkout/onepage/success', ['_current' => true]);
        }
    }
    
    private function createChallengeform($threeDsChallengeData, $iframe)
    {
        $challengeurl = $threeDsChallengeData['challenge']['url'];
        $challengeJwt = $threeDsChallengeData['challenge']['jwt'];
        $challengeReference = $threeDsChallengeData['challenge']['reference'];
        $verificationUrl = $threeDsChallengeData['_links']['3ds:verify']['href'];
        $this->checkoutSession->setVerificationUrl($verificationUrl);
        if ($challengeurl != null) {
            if ($iframe) {
                $imageurl = $this->_assetRepo->getUrl("Sapient_AccessWorldpay::images/cc/worldpay_logo.png");
                $challengeUrl = $this->_url->getUrl("worldpay/hostedpaymentpage/challenge");
                print_r('
                     
                    <div id="challenge_window">                        
                        <div class="image-content" style="text-align: center;">
                            <img src=' . $imageurl . ' alt="WorldPay"/>
                        </div>
                        <div class="iframe-content">
                            <iframe src="' . $challengeUrl . '" name="jwt_frm" id="jwt_frm"
                                style="text-align: center; vertical-align: middle; height: 50%;
                                display: table-cell; margin: 0 25%;
                                width: -webkit-fill-available; z-index:999999;">
                            </iframe>
                        </div>
                    </div>
                    </script>
                
                ');
            } else {
                print_r(' 
                    <form name= "challengeForm" id="challengeForm"
                    method= "POST"
                    action="' . $challengeurl . '" >
                    <!-- Use the above Challenge URL for test, 
                    we will provide a static Challenge URL for production once you go live -->
                        <input type = "hidden" name= "JWT" id= "second_jwt" value= "" />
                        <!-- Encoding of the JWT above with the secret "worldpaysecret". -->
                        <input type="hidden" name="MD" value="merchantSessionId=' . $challengeReference . '" />
                        
                        <!-- 
                        Extra field for you to pass data in to the challenge that will be included in the post 
                        back to the return URL after challenge complete 
                        -->
                    </form>');
                print_r('
                    <script language="Javascript">
  
                     document.getElementById("second_jwt").value = "' . $challengeJwt . '";
    

    
                         window.onload = function()
                            {
                              // Auto submit form on page load
                              document.getElementById("challengeForm").submit();
                            }
                    </script>');
            }
        }
    }
}
