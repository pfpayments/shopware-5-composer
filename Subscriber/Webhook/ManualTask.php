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

namespace PostFinanceCheckoutPayment\Subscriber\Webhook;

use PostFinanceCheckoutPayment\Components\ManualTask as ManualTaskService;

class ManualTask extends AbstractSubscriber
{
    public static function getSubscribedEvents()
    {
        return [
            'PostFinanceCheckout_Payment_Webhook_ManualTask' => 'handle'
        ];
    }

    /**
     *
     * @var ManualTaskService
     */
    private $manualTaskService;

    /**
     *
     * @param ManualTaskService $manualTaskService
     */
    public function __construct(ManualTaskService $manualTaskService)
    {
        $this->manualTaskService = $manualTaskService;
    }

    public function handle(\Enlight_Event_EventArgs $args)
    {
        $this->manualTaskService->update();
    }
}
