<?php

/**
 * PostFinance Checkout Shopware 5
 *
 * This Shopware 5 extension enables to process payments with PostFinance Checkout (https://postfinance.ch/en/business/products/e-commerce/postfinance-checkout-all-in-one.html/).
 *
 * @package PostFinanceCheckout_Payment
 * @author wallee AG (http://www.wallee.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */

namespace PostFinanceCheckoutPayment\Subscriber;

use Enlight\Event\SubscriberInterface;
use PostFinanceCheckoutPayment\Components\PaymentMethodConfiguration as PaymentMethodConfigurationService;

class PaymentMethodConfiguration implements SubscriberInterface
{

    /**
     *
     * @var PaymentMethodConfigurationService
     */
    private $paymentMethodConfigurationService;

    public static function getSubscribedEvents()
    {
        return [
            'PostFinanceCheckout_Payment_Config_Synchronize' => 'onSynchronize'
        ];
    }

    /**
     * Constructor.
     *
     * @param PaymentMethodConfigurationService $paymentMethodConfigurationService
     */
    public function __construct(PaymentMethodConfigurationService $paymentMethodConfigurationService)
    {
        $this->paymentMethodConfigurationService = $paymentMethodConfigurationService;
    }

    public function onSynchronize()
    {
        $this->paymentMethodConfigurationService->synchronize();
    }
}
