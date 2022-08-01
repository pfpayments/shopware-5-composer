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

namespace PostFinanceCheckoutPayment\Components;

use Shopware\Components\Plugin\ConfigReader;

class ApiClient
{

    /**
     *
     * @var ConfigReader
     */
    private $configReader;

    /**
     *
     * @var string
     */
    private $baseGatewayUrl;

    /**
     *
     * @var \PostFinanceCheckout\Sdk\ApiClient
     */
    private $instance;

    /**
     * Constructor.
     *
     * @param ConfigReader $config
     * @apram string $baseGatewayUrl
     */
    public function __construct(ConfigReader $configReader, $baseGatewayUrl)
    {
        $this->configReader = $configReader;
        $this->baseGatewayUrl = $baseGatewayUrl;
    }

    /**
     * Returns the instance of the PostFinance Checkout API client.
     *
     * @throws \Exception
     * @return \PostFinanceCheckout\Sdk\ApiClient
     */
    public function getInstance()
    {
        if ($this->instance == null) {
            $pluginConfig = $this->configReader->getByPluginName('PostFinanceCheckoutPayment');
            $userId = $pluginConfig['applicationUserId'];
            $applicationKey = $pluginConfig['applicationUserKey'];
            if ($userId && $applicationKey) {
                $this->instance = new \PostFinanceCheckout\Sdk\ApiClient($userId, $applicationKey);
                $this->instance->setBasePath($this->baseGatewayUrl . '/api');
            } else {
                throw new \Exception('The PostFinance Checkout API user data are incomplete.');
            }
        }
        return $this->instance;
    }
}
