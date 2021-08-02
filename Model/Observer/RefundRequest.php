<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Observer;

use Magento\Framework\Event\ObserverInterface;
use Exception;
use \Magento\Framework\Exception\LocalizedException;

class RefundRequest implements ObserverInterface
{
    
    /**
     * @var \Sapient\AccessWorldpay\Model\OmsDataFactory
     */
    protected $omsDataFactory;
    
    /**
     * @var \Sapient\AccessWorldpay\Model\Request\PaymentServiceRequest
     */
    protected $paymentservicerequest;
    
    /**
     * @var \Sapient\AccessWorldpay\Model\Request\CurlRequest
     */
    protected $_request;
    
    const CURL_POST = true;
    const CURL_RETURNTRANSFER = true;
    const CURL_NOPROGRESS = false;
    const CURL_TIMEOUT = 60;
    const CURL_VERBOSE = true;
    const SUCCESS = 200;
    
    public function __construct(
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger,
        \Magento\Framework\App\ResponseFactory $responseFactory,
        \Magento\Framework\UrlInterface $url,
        \Sapient\AccessWorldpay\Model\OmsDataFactory $omsDataFactory,
        \Sapient\AccessWorldpay\Model\ResourceModel\OmsData\CollectionFactory $omsCollectionFactory,
        \Sapient\AccessWorldpay\Model\Request\PaymentServiceRequest $paymentservicerequest,
        \Sapient\AccessWorldpay\Helper\Data $worldpayHelper,
        \Sapient\AccessWorldpay\Model\Request\CurlRequest $curlrequest,
        \Sapient\AccessWorldpay\Model\Payment\UpdateAccessWorldpaymentFactory $updateWorldPayPayment
    ) {
        $this->wplogger = $wplogger;
        $this->_responseFactory = $responseFactory;
        $this->_url = $url;
        $this->omsDataFactory = $omsDataFactory;
        $this->omsCollectionFactory = $omsCollectionFactory;
        $this->paymentservicerequest = $paymentservicerequest;
        $this->worldpayHelper = $worldpayHelper;
        $this->curlrequest = $curlrequest;
        $this->updateWorldPayPayment = $updateWorldPayPayment;
    }
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $creditmemo = $observer->getEvent()->getCreditmemo();
        $invoice = $creditmemo->getInvoice();
        $order = $creditmemo->getOrder();
        $orderIncrementId = $order->getIncrementId();
        $userName = $this->worldpayHelper->getXmlUsername();
        $password = $this->worldpayHelper->getXmlPassword();
        if ($orderIncrementId) {
            $collectionData = $this->omsCollectionFactory->create()
                ->addFieldToSelect(['awp_refund_param','awp_partial_refund_param'])
                ->addFieldToFilter('order_increment_id', ['eq' => $orderIncrementId]);
            $collectionData = $collectionData->getData();
            if ($collectionData) {
                $refundUrl = $collectionData[0]['awp_refund_param'];
                $partialRefundUrl = $collectionData[0]['awp_partial_refund_param'];
                if (floatval($creditmemo->getGrandTotal()) != floatval($order->getGrandTotal())) {
                    $amount = $this->_amountAsInt($creditmemo->getGrandTotal());
                    $currency = $creditmemo->getOrderCurrencyCode();
                    $data = [];
                    $data['value'] = ['amount' => $amount, 'currency'=>$currency];
                    $data['reference'] = 'Partial-refund-for-'.$order->getIncrementId();
                    $data = json_encode($data);
                    $response = $this->sendRequest($partialRefundUrl, $userName, $password, $data);
                } else {
                    $response = $this->sendRequest($refundUrl, $userName, $password);
                }
//                if($response){
//                    $this->updateOrderData($orderIncrementId, $response);
//                }
            }
        }
        
        return true;
    }
    
    public function sendRequest($refundUrl, $username, $password, $data = null)
    {
        $request = $this->_getRequest();
        $request->setUrl($refundUrl);

        $this->wplogger->info('Initialising request');
        $request->setOption(CURLOPT_POST, self::CURL_POST);
        $request->setOption(CURLOPT_RETURNTRANSFER, self::CURL_RETURNTRANSFER);
        $request->setOption(CURLOPT_NOPROGRESS, self::CURL_NOPROGRESS);
        $request->setOption(CURLOPT_TIMEOUT, self::CURL_TIMEOUT);
        $request->setOption(CURLOPT_VERBOSE, self::CURL_VERBOSE);
        /*SSL verification false*/
        $request->setOption(CURLOPT_SSL_VERIFYHOST, false);
        $request->setOption(CURLOPT_SSL_VERIFYPEER, false);
        $request->setOption(CURLOPT_POSTFIELDS, $data);
        $request->setOption(CURLOPT_USERPWD, $username.':'.$password);
        // Cookie Set to 2nd 3DS request only.
        //$cookie = $this->helper->getAccessWorldpayAuthCookie();
        $request->setOption(CURLOPT_HEADER, true);
        $request->setOption(CURLOPT_HTTPHEADER, ['Content-Type: application/vnd.worldpay.payments-v6+json']);
//        $request->setOption(CURLOPT_HTTPHEADER, array(
//        "Content-Type: application/vnd.worldpay.payments-v6+json",
//        "Authorization: Basic dG5lYnY4NmJjMHlwNG9uMTpsbDAxYTcwZDdhc2xubzYy"
//         ));
        if ($data) {
            $this->wplogger->info('Sending Json as: ' . $data);
        } else {
            $this->wplogger->info('Sending Json as: ' . $refundUrl);
        }

        $request->setOption(CURLINFO_HEADER_OUT, true);

        $result = $request->execute();

        if (!$result) {
            $this->wplogger->info('Request could not be sent.');
            $this->wplogger->info($result);
            $this->wplogger->info(
                '########### END OF REQUEST - FAILURE WHILST TRYING TO SEND REQUEST ###########'
            );
            throw new \Magento\Framework\Exception\LocalizedException(
                'AccessWorldpay api service not available'
            );
        }
        $request->close();
        $this->wplogger->info('Request successfully sent');
        $this->wplogger->info($result);
        
        $bits = explode("\r\n\r\n", $result);
        $body = array_pop($bits);
        
        return $body;
    }
    
    /**
     * @return object
     */
    private function _getRequest()
    {
        if ($this->_request === null) {
            $this->_request = $this->curlrequest;
        }
        
        return $this->_request;
    }
    
    /**
     * @param result array
     *
     * @return Boolean
     */
    public function updateOrderData($orderIncrementId, $responseData)
    {
        if ($responseData) {
            $response = json_decode($responseData);
            try {
                $this->updateWorldPayPayment->create()->updatePaymentSettlement($orderIncrementId, $response);
            } catch (exception $e) {
                $this->wplogger->error($e->getMessage());
            }
        }
    }
    
    /**
     * @param float $amount
     * @return int
     */
    private function _amountAsInt($amount)
    {
        return round($amount, 2, PHP_ROUND_HALF_EVEN) * pow(10, 2);
    }
}
