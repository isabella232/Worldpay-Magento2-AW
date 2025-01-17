<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Block;

use Magento\Framework\Serialize\SerializerInterface;

class Savedcard extends \Magento\Framework\View\Element\Template
{
    /**
     * @var \Sapient\AccessWorldpay\Model\SavedTokenFactory
     */
    protected $_savecard;
    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $_customerSession;
     /**
      * @var SerializerInterface
      */
    private $serializer;
    /**
     * constructor
     *
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Sapient\AccessWorldpay\Model\SavedTokenFactory $savecard
     * @param \Magento\Customer\Model\Session $customerSession
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Sapient\AccessWorldpay\Model\SavedTokenFactory $savecard,
        \Magento\Customer\Model\Session $customerSession,
        \Sapient\AccessWorldpay\Helper\Data $worldpayhelper,
        SerializerInterface $serializer,
        \Magento\Customer\Helper\Session\CurrentCustomerAddress $currentCustomerAddress,
        \Magento\Customer\Model\Address\Config $addressConfig,
        \Magento\Customer\Model\Address\Mapper $addressMapper,
        array $data = []
    ) {
        $this->_savecard = $savecard;
        $this->_customerSession = $customerSession;
        $this->serializer = $serializer;
        $this->worlpayhelper = $worldpayhelper;
        $this->currentCustomerAddress = $currentCustomerAddress;
        $this->_addressConfig = $addressConfig;
        $this->addressMapper = $addressMapper;
           parent::__construct($context, $data);
    }

    /**
     * @return bool|\Sapient\AccessWorldpay\Model\ResourceModel\SavedToken\Collection
     */
    public function getSavedCard()
    {
        if (!($customerId = $this->_customerSession->getCustomerId())) {
            return false;
        }
        return $savedCardCollection = $this->_savecard->create()->getCollection()
                                    ->addFieldToSelect(['card_brand','card_number',
                                        'cardholder_name','card_expiry_month','card_expiry_year',
                                        'transaction_reference', 'token_id'])
                                    ->addFieldToFilter('customer_id', ['eq' => $customerId]);
    }

   /**
    * @param \Sapient\AccessWorldpay\Model\SavedToken $saveCard
    * @return string
    */
    public function getDeleteUrl($saveCard)
    {
        return $this->getUrl('worldpay/savedcard/delete', ['id' => $saveCard->getId()]);
    }

    /**
     * @param \Sapient\AccessWorldpay\Model\SavedToken $saveCard
     * @return string
     */
    public function getEditUrl($saveCard)
    {
        return $this->getUrl('worldpay/savedcard/edit', ['id' => $saveCard->getId()]);
    }
    
    /**
     * Get order id column value
     *
     * @return string
     */
   
    public function getAddNewCardLabel()
    {
            return $this->getUrl('worldpay/savedcard/addnewcard', ['_secure' => true]);
    }
    
    /**
     * Render an address as HTML and return the result
     *
     * @param AddressInterface $address
     * @return string
     */
    protected function getPrimaryBillingAddressHtml()
    {
        /** @var \Magento\Customer\Block\Address\Renderer\RendererInterface $renderer */
        $address = $this->currentCustomerAddress->getDefaultBillingAddress();
        if ($address) {
            $renderer = $this->_addressConfig->getFormatByCode('html')->getRenderer();
            return $renderer->renderArray($this->addressMapper->toFlatArray($address));
        } else {
            return $this->escapeHtml(__('You have not set a default billing address.'));
        }
    }
    
    public function ifBillingAddressPresent()
    {
        $address = $this->currentCustomerAddress->getDefaultBillingAddress();
        if ($address) {
            return true;
        }
        return false;
    }
    public function getMyAccountLabels($labelCode)
    {
        $accdata = $this->serializer->unserialize($this->worlpayhelper->getMyAccountLabels());
        if (is_array($accdata) || is_object($accdata)) {
            foreach ($accdata as $key => $valuepair) {
                if ($key == $labelCode) {
                    return $valuepair['wpay_custom_label']?$valuepair['wpay_custom_label']:
                        $valuepair['wpay_label_desc'];
                }
            }
        }
    }
}
