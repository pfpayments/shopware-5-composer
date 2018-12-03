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

use PostFinanceCheckout\Sdk\Model\TransactionLineItemVersion as LineItemVersionModel;
use PostFinanceCheckoutPayment\Components\ArrayBuilder\LineItem as LineItemArrayBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

class LineItemVersion extends AbstractArrayBuilder
{
    /**
     *
     * @var LineItemVersionModel
     */
    private $lineItemVersion;

    /**
     * Constructor.
     *
     * @param ContainerInterface $container
     * @param LineItemVersionModel $lineItem
     */
    public function __construct(ContainerInterface $container, LineItemVersionModel $lineItemVersion)
    {
        parent::__construct($container);
        $this->lineItemVersion = $lineItemVersion;
    }

    public function build()
    {
        $result = [];
        $transactionLineItems = [];
        foreach ($this->lineItemVersion->getTransaction()->getLineItems() as $lineItem) {
            $transactionLineItems[$lineItem->getUniqueId()] = $lineItem;
        }
        foreach ($this->lineItemVersion->getLineItems() as $lineItem) {
            $lineItemBuilder = new LineItemArrayBuilder($this->container, $lineItem);
            $item = $lineItemBuilder->build();
            $item['originalAmountIncludingTax'] = $transactionLineItems[$lineItem->getUniqueId()]->getAmountIncludingTax();
            $item['originalUnitPriceIncludingTax'] = $transactionLineItems[$lineItem->getUniqueId()]->getUnitPriceIncludingTax();
            $item['originalQuantity'] = $transactionLineItems[$lineItem->getUniqueId()]->getQuantity();
            $result[] = $item;
        }
        return $result;
    }
}