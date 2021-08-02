<?php

namespace Sapient\AccessWorldpay\Block\Adminhtml\Form\Field;


class AdminLabels extends \Sapient\AccessWorldpay\Block\Form\Field\FieldArray\CustomLabelsArray
{
  
    /**
     * Prepare to render.
     *
     * @return void
     */
    protected function _prepareToRender()
    {
        $this->addColumn(
            'wpay_label_code',
            ['label' => __('Label Code'),
            'style' => 'width:100px',
            'class' => 'required-entry']
        );
        $this->addColumn(
            'wpay_label_desc',
            ['label' => __('Default Label'),
            'style' => 'width:200px',
            'class' => 'required-entry']
        );
        $this->addColumn('wpay_custom_label', ['label' => __('Custom label'),
            'style' => 'width:200px']);

        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add');
    }  
}
