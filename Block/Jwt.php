<?php
namespace Sapient\AccessWorldpay\Block;

use Sapient\AccessWorldpay\Helper\Data;
use Sapient\AccessWorldpay\Model\Request\PaymentServiceRequest;

class Jwt extends \Magento\Framework\View\Element\Template
{
    protected $helper;
    
    /**
     * Jwt constructor.
     * @param Create $helper
     * @param array $data
     */

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        Data $helper,
        PaymentServiceRequest $paymentservice
    ) {
        $this->_helper = $helper;
        $this->paymentservice = $paymentservice;
        $this->checkoutSession = $checkoutSession;
        $this->quoteRepository = $quoteRepository;
        parent::__construct($context);
    }

    public function getDdcUrl()
    {
        
        //$quote = $this->quoteRepository->get($this->checkoutSession->getQuoteId());
        $mode = $this->_helper->getEnvironmentMode();
        $ddcurl =  $this->checkoutSession->getDdcUrl();
        return $ddcurl;
    }
    
    public function getJWT()
    {
        $jwt = $this->checkoutSession->getDdcJwt();
       
        return $jwt;
    }
    
    public function getCookie()
    {
        return $cookie = $this->_helper->getWorldpayAuthCookie();
    }
}
