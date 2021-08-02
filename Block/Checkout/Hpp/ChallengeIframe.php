<?php

namespace Sapient\AccessWorldpay\Block\Checkout\Hpp;

class ChallengeIframe extends \Magento\Framework\View\Element\Template
{
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Sapient\AccessWorldpay\Model\Checkout\Hpp\Json\Config\Factory $configfactory,
        array $data = []
    ) {
        $this->configfactory = $configfactory;
          parent::__construct($context, $data);
    }
    
    /**
     *
     * @return string
     */
    public function getRedirectUrl()
    {
        return $this->getUrl('worldpay/threedsecure/challengeauthresponse', ['_secure' => true]);
    }
}
