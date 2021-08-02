<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Config\Source;

class AuthMethods extends \Magento\Framework\App\Config\Value
{
    /**
     * @return array
     */
    public function toOptionArray()
    {

        return [
            ['value' => 'PAN_ONLY', 'label' => __('Pan Only')],
            ['value' => 'CRYPTOGRAM_3DS', 'label' => __('Cryptogram 3ds')]
        ];
    }
}
