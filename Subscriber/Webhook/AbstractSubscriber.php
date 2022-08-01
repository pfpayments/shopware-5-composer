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

namespace PostFinanceCheckoutPayment\Subscriber\Webhook;

use Enlight\Event\SubscriberInterface;

abstract class AbstractSubscriber implements SubscriberInterface
{
    
    /**
     * In case a \PostFinanceCheckout\Sdk\Http\ConnectionException or a \PostFinanceCheckout\Sdk\VersioningException occurs, the {@code $callback} function is called again.
     *
     * @param \PostFinanceCheckout\Sdk\ApiClient $apiClient
     * @param callable $callback
     * @throws \PostFinanceCheckout\Sdk\Http\ConnectionException
     * @throws \PostFinanceCheckout\Sdk\VersioningException
     * @return mixed
     */
    protected function callApi(\PostFinanceCheckout\Sdk\ApiClient $apiClient, $callback)
    {
        $lastException = null;
        $apiClient->setConnectionTimeout(5);
        for ($i = 0; $i < 5; $i++) {
            try {
                return $callback();
            } catch (\PostFinanceCheckout\Sdk\VersioningException $e) {
                $lastException = $e;
            } catch (\PostFinanceCheckout\Sdk\Http\ConnectionException $e) {
                $lastException = $e;
            } finally {
                $apiClient->setConnectionTimeout(20);
            }
        }
        throw $lastException;
    }
}
