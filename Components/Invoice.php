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

namespace PostFinanceCheckoutPayment\Components;

use Shopware\Components\Model\ModelManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use PostFinanceCheckout\Sdk\Model\EntityQuery;
use PostFinanceCheckout\Sdk\Service\TransactionInvoiceService;

class Invoice extends AbstractService
{

    /**
     *
     * @var ModelManager
     */
    private $modelManager;

    /**
     *
     * @var TransactionInvoiceService
     */
    private $invoiceService;

    /**
     * Constructor.
     *
     * @param ContainerInterface $container
     * @param ModelManager $modelManager
     * @param ApiClient $apiClient
     */
    public function __construct(ContainerInterface $container, ModelManager $modelManager, ApiClient $apiClient)
    {
        parent::__construct($container);
        $this->modelManager = $modelManager;
        $this->invoiceService = new TransactionInvoiceService($apiClient->getInstance());
    }

    /**
     *
     * @param int $spaceId
     * @param int $transactionId
     * @return \PostFinanceCheckout\Sdk\Model\TransactionInvoice
     */
    public function getInvoice($spaceId, $transactionId)
    {
        return $this->callApi($this->invoiceService->getApiClient(), function () use ($spaceId, $transactionId) {
            $query = new EntityQuery();
            $query->setFilter($this->createEntityFilter('completion.lineItemVersion.transaction.id', $transactionId));
            $query->setNumberOfEntities(1);
            return current($this->invoiceService->search($spaceId, $query));
        });
    }
}
