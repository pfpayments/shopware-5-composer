<?php

/**
 * PostFinance Checkout Shopware 5
 *
 * This Shopware 5 extension enables to process payments with PostFinance Checkout (https://www.postfinance.ch/checkout/).
 *
 * @package PostFinanceCheckout_Payment
 * @author wallee AG (http://www.wallee.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */

namespace PostFinanceCheckoutPayment\Components\Provider;

use PostFinanceCheckoutPayment\Components\ApiClient;

/**
 * Provider of currency information from the gateway.
 */
class Currency extends AbstractProvider
{

    /**
     * Constructor.
     *
     * @param \PostFinanceCheckout\Sdk\ApiClient $apiClient
     * @param \Zend_Cache_Core $cache
     */
    public function __construct(ApiClient $apiClient, \Zend_Cache_Core $cache)
    {
        parent::__construct($apiClient->getInstance(), $cache, 'postfinancecheckout_payment_currencies');
    }

    /**
     * Returns the currency by the given code.
     *
     * @param int $code
     * @return \PostFinanceCheckout\Sdk\Model\RestCurrency
     */
    public function find($code)
    {
        return parent::find($code);
    }

    /**
     * Returns a list of currencies.
     *
     * @return \PostFinanceCheckout\Sdk\Model\RestCurrency[]
     */
    public function getAll()
    {
        return parent::getAll();
    }

    /**
     * Returns the fraction digits of the given currency.
     *
     * @param string $code
     * @return number
     */
    public function getFractionDigits($code)
    {
        $currency = $this->find($code);
        if ($currency) {
            return $currency->getFractionDigits();
        } else {
            return 2;
        }
    }

    protected function fetchData()
    {
        $methodService = new \PostFinanceCheckout\Sdk\Service\CurrencyService($this->apiClient);
        return $methodService->all();
    }

    protected function getId($entry)
    {
        /* @var \PostFinanceCheckout\Sdk\Model\RestCurrency $entry */
        return $entry->getCurrencyCode();
    }
}
