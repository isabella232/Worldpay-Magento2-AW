<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Sapient\AccessWorldpay\Block;

use Magento\Framework\App\DefaultPathInterface;
use Magento\Framework\View\Element\Template\Context;
use Sapient\AccessWorldpay\Model\AccessWorldpayConfigProvider;
use Sapient\AccessWorldpay\Helper\Data;

/**
 * Description of SavedCardLink
 *
 * @author aatrai
 */
class SavedCardLink extends \Magento\Framework\View\Element\Html\Link\Current
{
    protected $_scopeConfig = null;
    public function __construct(
        Context $context,
        AccessWorldpayConfigProvider $config,
        Data $helper,
        DefaultPathInterface $defaultPath,
        array $data = []
    ) {
        parent::__construct($context, $defaultPath);
        $this->config = $config;
        $this->helper = $helper;
    }
    
    public function _toHtml()
    {
        
        if ($this->helper->isWorldPayEnable() && $this->checkSaveCardTabToBeEnabled()) {
             return parent::_toHtml();
        } else {
            return '';
        }
    }
    
    public function checkSaveCardTabToBeEnabled()
    {
        
        if ($this->helper->getSaveCard() ||
            !empty($this->config->getSaveCardList())) {
            return true;
        }
    }
}
