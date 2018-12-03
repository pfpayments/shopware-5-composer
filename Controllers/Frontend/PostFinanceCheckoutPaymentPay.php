<?php

/**
 * PostFinance Checkout Shopware
 *
 * This Shopware extension enables to process payments with PostFinance Checkout (https://www.postfinance.ch/).
 *
 * @package PostFinanceCheckout_Payment
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */

use PostFinanceCheckoutPayment\Components\Controller\Frontend;

class Shopware_Controllers_Frontend_PostFinanceCheckoutPaymentPay extends Frontend
{
    public function indexAction()
    {
        $namespace = $this->container->get('snippets')->getNamespace('frontend/postfinancecheckout_payment/main');
        return $this->forward('confirm', 'checkout', null, ['postFinanceCheckoutErrors' => $namespace->get('checkout/javascript_error', 'The order could not be submitted because it was not correctly transmitted to PostFinance Checkout.')]);
    }
}