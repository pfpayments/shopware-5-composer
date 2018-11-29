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

use Symfony\Component\DependencyInjection\ContainerInterface;
use Shopware\Components\Plugin\ConfigReader;
use Shopware\Components\Model\ModelManager;
use PostFinanceCheckoutPayment\Models\TransactionInfo as TransactionInfoModel;

class DeliveryIndication extends AbstractService
{

    /**
     *
     * @var ModelManager
     */
    private $modelManager;

    /**
     *
     * @var ConfigReader
     */
    private $configReader;

    /**
     *
     * @var \PostFinanceCheckout\Sdk\Service\DeliveryIndicationService
     */
    private $deliveryIndicationService;

    /**
     * Constructor.
     *
     * @param ContainerInterface $container
     * @param ModelManager $modelManager
     * @param ConfigReader $configReader
     * @param ApiClient $apiClient
     */
    public function __construct(ContainerInterface $container, ModelManager $modelManager, ConfigReader $configReader, ApiClient $apiClient)
    {
        parent::__construct($container);
        $this->modelManager = $modelManager;
        $this->configReader = $configReader;
        $this->deliveryIndicationService = new \PostFinanceCheckout\Sdk\Service\DeliveryIndicationService($apiClient->getInstance());
    }

    /**
     *
     * @param TransactionInfoModel $transactionInfo
     * @return \PostFinanceCheckout\Sdk\Model\DeliveryIndication[]
     */
    public function getDeliveryIndication(TransactionInfoModel $transactionInfo)
    {
        return $this->callApi($this->deliveryIndicationService->getApiClient(), function () use ($transactionInfo) {
            $query = new \PostFinanceCheckout\Sdk\Model\EntityQuery();
            $query->setFilter($this->createEntityFilter('transaction.id', $transactionInfo->getTransactionId()));
            $result = $this->deliveryIndicationService->search($transactionInfo->getSpaceId(), $query);
            if (count($result) == 1) {
                return current($result);
            }
        });
    }

    public function accept(TransactionInfoModel $transactionInfo)
    {
        $deliveryIndication = $this->getDeliveryIndication($transactionInfo);
        if ($deliveryIndication == null) {
            throw new \Exception('No delivery indication in space ' . $transactionInfo->getSpaceId() . ' for transaction ' . $transactionInfo->getTransactionId() . ' found.');
        }
        $this->deliveryIndicationService->markAsSuitable($transactionInfo->getSpaceId(), $deliveryIndication->getId());

        $this->container->get('postfinancecheckout_payment.transaction')->handleTransactionState($transactionInfo->getSpaceId(), $transactionInfo->getTransactionId());
    }

    public function deny(TransactionInfoModel $transactionInfo)
    {
        $deliveryIndication = $this->getDeliveryIndication($transactionInfo);
        if ($deliveryIndication == null) {
            throw new \Exception('No delivery indication in space ' . $transactionInfo->getSpaceId() . ' for transaction ' . $transactionInfo->getTransactionId() . ' found.');
        }
        $this->deliveryIndicationService->markAsNotSuitable($transactionInfo->getSpaceId(), $deliveryIndication->getId());

        $this->container->get('postfinancecheckout_payment.transaction')->handleTransactionState($transactionInfo->getSpaceId(), $transactionInfo->getTransactionId());
    }
}
