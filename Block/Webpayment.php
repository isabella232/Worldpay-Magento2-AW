<?php

namespace Sapient\AccessWorldpay\Block;

use Magento\Framework\View\Element\Template;
use Sapient\AccessWorldpay\Helper\Data;
use Magento\Framework\Serialize\SerializerInterface;

class Webpayment extends Template
{
    /**
     * @var SerializerInterface
     */
    private $serializer;
    /*
     * @param \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger
     */
    public function __construct(
        Template\Context $context,
        Data $helper,
        SerializerInterface $serializer,
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger,
        array $data = []
    ) {

        $this->_helper = $helper;
        $this->serializer = $serializer;
        $this->wplogger = $wplogger;
        parent::__construct(
            $context,
            $data
        );
    }
    
    public function is3DSecureEnabled()
    {
        return $this->_helper->is3DSecureEnabled();
    }

    public function getWebSdkJsPath()
    {
        return $this->_helper->getWebSdkJsPath();
    }
    
    public function getEnvironmentMode()
    {
        return $this->_helper->getEnvironmentMode();
    }
    
    public function getCreditCardException()
    {
        $generaldata=$this->serializer->unserialize($this->_helper->getCreditCardException());
        $result=[];
        $data=[];
        if (is_array($generaldata) || is_object($generaldata)) {
            foreach ($generaldata as $key => $value) {

                $result['exception_code']=$key;
                $result['exception_messages'] = $value['exception_messages'];
                $result['exception_module_messages'] = $value['exception_module_messages'];
                array_push($data, $result);
            
            }
        }
        //$output=implode(',', $data);
        return json_encode($data);
    }
    
    public function myAccountExceptions()
    {
        $generaldata=$this->serializer->unserialize($this->_helper->getMyAccountException());
        $result=[];
        $data=[];
        if (is_array($generaldata) || is_object($generaldata)) {
            foreach ($generaldata as $key => $value) {
                $result['exception_code']=$key;
                $result['exception_messages'] = $value['exception_messages'];
                $result['exception_module_messages'] = $value['exception_module_messages'];
                array_push($data, $result);
            
            }
        }
        return json_encode($data);
    }
    
    public function getMyAccountSpecificException($exceptioncode)
    {
        $data=json_decode($this->myAccountExceptions(), true);
        if (is_array($data) || is_object($data)) {
            foreach ($data as $key => $valuepair) {
                if ($valuepair['exception_code'] == $exceptioncode) {
                    return $valuepair['exception_module_messages']?
                            $valuepair['exception_module_messages']:$valuepair['exception_messages'];
                }
            }
        }
    }
    public function getCreditCardSpecificException($exceptioncode)
    {
        return $this->_helper->getCreditCardSpecificexception($exceptioncode);
    }
}
