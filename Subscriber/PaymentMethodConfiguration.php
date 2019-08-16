<?php

/**
 * PostFinance Checkout Shopware
 *
 * This Shopware extension enables to process payments with PostFinance Checkout (https://www.postfinance.ch/checkout/).
 *
 * @package PostFinanceCheckout_Payment
 * @author customweb GmbH (http://www.customweb.com/)
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
