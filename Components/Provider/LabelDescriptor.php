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

namespace PostFinanceCheckoutPayment\Components\Provider;

use PostFinanceCheckoutPayment\Components\ApiClient;

/**
 * Provider of label descriptor information from the gateway.
 */
class LabelDescriptor extends AbstractProvider
{

    /**
     * Constructor.
     *
     * @param \PostFinanceCheckout\Sdk\ApiClient $apiClient
     * @param \Zend_Cache_Core $cache
     */
    public function __construct(ApiClient $apiClient, \Zend_Cache_Core $cache)
    {
        parent::__construct($apiClient->getInstance(), $cache, 'postfinancecheckout_payment_label_descriptors');
    }

    /**
     * Returns the label descriptor by the given code.
     *
     * @param int $code
     * @return \PostFinanceCheckout\Sdk\Model\LabelDescriptor
     */
    public function find($code)
    {
        return parent::find($code);
    }

    /**
     * Returns a list of label descriptors.
     *
     * @return \PostFinanceCheckout\Sdk\Model\LabelDescriptor[]
     */
    public function getAll()
    {
        return parent::getAll();
    }

    protected function fetchData()
    {
        $labelDescriptorService= new \PostFinanceCheckout\Sdk\Service\LabelDescriptionService($this->apiClient);
        return $labelDescriptorService->all();
    }

    protected function getId($entry)
    {
        /* @var \PostFinanceCheckout\Sdk\Model\LabelDescriptor $entry */
        return $entry->getId();
    }
}
