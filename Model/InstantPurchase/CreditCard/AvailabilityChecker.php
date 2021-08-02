<?php
namespace Sapient\AccessWorldpay\Model\InstantPurchase\CreditCard;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of AvailabilityChecker
 *
 * @author aatrai
 */
class AvailabilityChecker implements \Magento\InstantPurchase\PaymentMethodIntegration\AvailabilityCheckerInterface
{
    /**
     * @var Config
     */
    private $config;

    private $wplogger;
    /**
     * AvailabilityChecker constructor.
     * @param Config $config
     */
    public function __construct(
        \Sapient\AccessWorldpay\Helper\Data $worldpayHelper,
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger
    ) {
        $this->config = $worldpayHelper;
        $this->wplogger = $wplogger;
    }

    /**
     * @inheritdoc
     */
    public function isAvailable(): bool
    {
        if ($this->config->isWorldPayEnable() &&
            $this->config->isCreditCardEnabled() &&
            $this->config->instantPurchaseEnabled()) {
            return true;
        }
         $this->wplogger->info("Instant Purchase is disabled:");
         return false;
    }
}
