<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Sapient\AccessWorldpay\Observer;

use Magento\Framework\Event\Observer;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;

/**
 * Adds the payment info to the payment object
 *
 * @deprecated 100.3.3 Starting from Magento 2.3.4 Authorize.net
 * payment method core integration is deprecated in favor of
 * official payment integration available on the marketplace
 */
class DataAssignObserver extends AbstractDataAssignObserver
{
    /**
     * @var array
     */
    private $additionalInformationList = [
        'cc_name',
        'cc_number',
        'cc_exp_month',
        'cc_exp_year',
        'cart_id',
        'cvc',
        'save_card',
        'cvcHref',
        'sessionHref',
        'tokenId',
        'tokenUrl',
        'customer_id',
        'is_graphql',
        'googlepayToken',
        'applepayToken'
    ];

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer)
    {
      
        $data = $this->readDataArgument($observer);
        $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);
        if (!is_array($additionalData)) {
            return;
        }

        $paymentInfo = $this->readPaymentModelArgument($observer);

        foreach ($this->additionalInformationList as $additionalInformationKey) {
            if (isset($additionalData[$additionalInformationKey])) {
                $paymentInfo->setAdditionalInformation(
                    $additionalInformationKey,
                    $additionalData[$additionalInformationKey]
                );
            }
        }
    }
}
