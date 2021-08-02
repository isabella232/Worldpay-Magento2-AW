<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Payment;

/**
 * Describe what can be read from WP's xml response
 */
interface State
{
    const STATUS_SENT_FOR_AUTHORISATION = 'SENT_FOR_AUTHORIZATION';
    const STATUS_AUTHORISED = 'AUTHORIZED';
    const STATUS_CANCELLED = 'CANCELLED';
    const STATUS_PENDING_PAYMENT = 'PENDING_PAYMENT';
    const STATUS_REFUSED = 'REFUSED';
    const STATUS_ERROR = 'ERROR';
    const STATUS_SETTLED = 'SETTLED';
    const STATUS_SETTLED_BY_MERCHANT = 'SETTLED_BY_MERCHANT';
    const STATUS_CHARGED_BACK = 'CHARGED_BACK';
    const STATUS_CHARGEBACK_REVERSED = 'CHARGEBACK_REVERSED';
    const STATUS_INFORMATION_SUPPLIED = 'INFORMATION_SUPPLIED';
    const STATUS_INFORMATION_REQUESTED = 'INFORMATION_REQUESTED';
    const STATUS_CAPTURED = 'CAPTURED';
    const STATUS_PARTIAL_CAPTURED = 'PARTIAL_CAPTURED';
    const STATUS_SENT_FOR_REFUND = 'SENT_FOR_REFUND';
    const STATUS_REFUNDED = 'REFUNDED';
    const STATUS_PARTIAL_REFUNDED = 'PARTIAL_REFUNDED';
    const STATUS_REFUND_WEBFORM_ISSUED = 'REFUND_WEBFORM_ISSUED';
    const STATUS_REFUND_EXPIRED = 'REFUND_EXPIRED';
    const STATUS_REFUND_FAILED = 'REFUND_FAILED';
    const STATUS_REFUNDED_BY_MERCHANT = 'REFUNDED_BY_MERCHANT';
    const STATUS_SENT_FOR_SETTLEMENT = 'SENT_FOR_SETTLEMENT';
   
    public function getPaymentStatus();
    public function getOrderCode();
    public function getJournalReference($state);
    public function getLinks();
//    public function getAmount();
//    public function getMerchantCode();
//    public function getRiskScore();
//    public function getPaymentMethod();
//    public function getCardNumber();
//    public function getAvsResultCode();
//    public function getCvcResultCode();
//    public function getAdvancedRiskProvider();
//    public function getAdvancedRiskProviderId();
//    public function getAdvancedRiskProviderThreshold();
//    public function getAdvancedRiskProviderScore();
//    public function getAdvancedRiskProviderFinalScore();
//    public function getPaymentRefusalCode();
//    public function getPaymentRefusalDescription();
//    public function getJournalReference($state);
//    public function isAsyncNotification();
//    public function isDirectReply();
//    public function getAAVAddressResultCode();
//    public function getAAVPostcodeResultCode();
//    public function getAAVCardholderNameResultCode();
//    public function getAAVTelephoneResultCode();
//    public function getAAVEmailResultCode();
//    public function getCurrency();
}
