<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Controller\Hostedpaymentpage;

use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\App\Action\Context;

/**
 * Redirect to payment hosted page
 */
class Pay extends \Magento\Framework\App\Action\Action
{

    /**
     * @var Magento\Framework\View\Result\PageFactory
     */
    protected $pageFactory;

    /**
     * @var \Sapient\AccessWorldpay\Model\Checkout\Hpp\State
     */
    protected $_status;

    /**
     * Constructor
     *
     * @param Context $context
     * @param PageFactory $pageFactory
     * @param \Sapient\AccessWorldpay\Model\Checkout\Hpp\State
     * @param \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger
     */
    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        \Sapient\AccessWorldpay\Model\Checkout\Hpp\State $hppstate,
        \Sapient\AccessWorldpay\Helper\Data $worldpayhelper,
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger
    ) {
        $this->pageFactory = $pageFactory;
        $this->wplogger = $wplogger;
        $this->hppstate = $hppstate;
        $this->worldpayhelper = $worldpayhelper;
        return parent::__construct($context);
    }
 
    public function execute()
    {

        if (!$this->_getStatus()->isInitialised() || !$this->worldpayhelper->isIframeIntegration()) {
            return $this->resultRedirectFactory->create()->setPath('checkout/cart', ['_current' => true]);
        }
        return $this->pageFactory->create();
    }

    protected function _getStatus()
    {
        if ($this->_status === null) {
            $this->_status = $this->hppstate;
        }

        return $this->_status;
    }
}
