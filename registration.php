<?php
/**
 * EDPay Magento 2 Payment Module Registration
 */

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'Edpay_Payment',
    __DIR__
);
