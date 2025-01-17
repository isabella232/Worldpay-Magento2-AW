<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Config\Source;

class IntegrationMode implements \Magento\Framework\Option\ArrayInterface
{
    const OPTION_VALUE_DIRECT = 'direct';
    const OPTION_VALUE_WEBSDK = 'web_sdk';
    
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => self::OPTION_VALUE_DIRECT, 'label' => __('Direct')],
            ['value' => self::OPTION_VALUE_WEBSDK, 'label' => __('AccessCheckout (Web SDK)')],
        ];
    }
}
