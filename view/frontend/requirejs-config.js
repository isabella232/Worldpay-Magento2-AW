/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
var sdkJs;
var environmentMode;
var cookieArr = document.cookie.split(";");
 
    // Loop through the array elements
    for(var i = 0; i < cookieArr.length; i++) {
        var cookiePair = cookieArr[i].split("=");
        
        /* Removing whitespace at the beginning of the cookie name
        and compare it with the given string */
        if(cookiePair[1].trim() == 'Test Mode') {
            environmentMode = 'Test Mode';
            break;
        }
    }
    if (environmentMode == 'Test Mode') {
    var sdkJs = 'https://try.access.worldpay.com/access-checkout/v1/checkout.js';
    } else {
    var sdkJs = 'https://access.worldpay.com/access-checkout/v1/checkout.js';
    }
         
var config = {
    map: {
        '*': {
          
            "Magento_Checkout/template/payment.html": "Sapient_AccessWorldpay/template/payment.html",
            "Magento_Checkout/template/payment-methods/list.html": "Sapient_AccessWorldpay/template/payment-methods/list.html",
            newcard : "Sapient_AccessWorldpay/js/newcard",
            "validation": "mage/validation/validation",
            websdk: sdkJs,
            googlePay: 'https://pay.google.com/gp/p/js/pay.js'
            //websdk: "Sapient_AccessWorldpay/js/abc"
            //websdk: window.websdk
       
        }
    },
    mixins: {
        "Magento_Checkout/js/view/billing-address": {
            "Sapient_AccessWorldpay/js/view/billing-address": true
        }
    },
   
  
};
