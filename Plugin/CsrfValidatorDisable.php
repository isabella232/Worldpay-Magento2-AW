<?php

namespace Sapient\AccessWorldpay\Plugin;

class CsrfValidatorDisable
{
    public function aroundValidate(
        $subject,
        \Closure $proceed,
        $request,
        $action
    ) {
        if ($request->getModuleName() == 'worldpay') {
            return; // Disable CSRF check
        }
        $proceed($request, $action); // Proceed Magento 2 core functionalities
    }
}
