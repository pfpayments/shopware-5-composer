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

namespace PostFinanceCheckoutPayment\Components\ArrayBuilder;

use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class AbstractArrayBuilder
{
    /**
     *
     * @var ContainerInterface
     */
    protected $container;

    /**
     * Constructor.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     *
     * @return array
     */
    abstract public function build();

    /**
     *
     * @param array[string,string]|\PostFinanceCheckout\Sdk\Model\DatabaseTranslatedString $string
     * @return string
     */
    protected function translate($string)
    {
        return $this->container->get('postfinancecheckout_payment.translator')->translate($string);
    }
}
