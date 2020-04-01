<?php

/**
 * PostFinance Checkout Shopware 5
 *
 * This Shopware 5 extension enables to process payments with PostFinance Checkout (https://www.postfinance.ch/checkout/).
 *
 * @package PostFinanceCheckout_Payment
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */

namespace PostFinanceCheckoutPayment\Components;

use Symfony\Component\DependencyInjection\ContainerInterface;
use PostFinanceCheckoutPayment\Components\Webhook\Entity;
use Shopware\Components\Plugin\ConfigReader;
use Shopware\Models\Shop\Shop;
use Shopware\Components\Model\ModelManager;

class Webhook extends AbstractService
{

    /**
     *
     * @var ConfigReader
     */
    private $configReader;

    /**
     *
     * @var ModelManager
     */
    private $modelManager;

    /**
     *
     * @var \PostFinanceCheckout\Sdk\ApiClient
     */
    private $apiClient;

    /**
     * The transaction url API service.
     *
     * @var \PostFinanceCheckout\Sdk\Service\WebhookUrlService
     */
    private $webhookUrlService;

    /**
     * The transaction listener API service.
     *
     * @var \PostFinanceCheckout\Sdk\Service\WebhookListenerService
     */
    private $webhookListenerService;

    private $webhookEntities = array();

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
        $this->apiClient = $apiClient->getInstance();
        $this->webhookUrlService = new \PostFinanceCheckout\Sdk\Service\WebhookUrlService($this->apiClient);
        $this->webhookListenerService = new \PostFinanceCheckout\Sdk\Service\WebhookListenerService($this->apiClient);

        $this->webhookEntities[] = new Entity(1487165678181, 'Manual Task', array(
            \PostFinanceCheckout\Sdk\Model\ManualTaskState::DONE,
            \PostFinanceCheckout\Sdk\Model\ManualTaskState::EXPIRED,
            \PostFinanceCheckout\Sdk\Model\ManualTaskState::OPEN
        ));
        $this->webhookEntities[] = new Entity(1472041857405, 'Payment Method Configuration', array(
            \PostFinanceCheckout\Sdk\Model\CreationEntityState::ACTIVE,
            \PostFinanceCheckout\Sdk\Model\CreationEntityState::DELETED,
            \PostFinanceCheckout\Sdk\Model\CreationEntityState::DELETING,
            \PostFinanceCheckout\Sdk\Model\CreationEntityState::INACTIVE
        ), true);
        $this->webhookEntities[] = new Entity(1472041829003, 'Transaction', array(
            \PostFinanceCheckout\Sdk\Model\TransactionState::AUTHORIZED,
            \PostFinanceCheckout\Sdk\Model\TransactionState::DECLINE,
            \PostFinanceCheckout\Sdk\Model\TransactionState::FAILED,
            \PostFinanceCheckout\Sdk\Model\TransactionState::FULFILL,
            \PostFinanceCheckout\Sdk\Model\TransactionState::VOIDED,
            \PostFinanceCheckout\Sdk\Model\TransactionState::COMPLETED,
            \PostFinanceCheckout\Sdk\Model\TransactionState::PROCESSING,
            \PostFinanceCheckout\Sdk\Model\TransactionState::CONFIRMED
        ));
        $this->webhookEntities[] = new Entity(1472041819799, 'Delivery Indication', array(
            \PostFinanceCheckout\Sdk\Model\DeliveryIndicationState::MANUAL_CHECK_REQUIRED
        ));
        $this->webhookEntities[] = new Entity(1472041816898, 'Transaction Invoice', array(
            \PostFinanceCheckout\Sdk\Model\TransactionInvoiceState::NOT_APPLICABLE,
            \PostFinanceCheckout\Sdk\Model\TransactionInvoiceState::PAID,
            \PostFinanceCheckout\Sdk\Model\TransactionInvoiceState::DERECOGNIZED
        ));
    }

    /**
     * Installs the necessary webhooks in PostFinance Checkout.
     */
    public function install()
    {
        $spaceIds = array();
        foreach ($this->modelManager->getRepository(Shop::class)->findAll() as $shop) {
            $pluginConfig = $this->configReader->getByPluginName('PostFinanceCheckoutPayment', $shop);
            $spaceId = $pluginConfig['spaceId'];
            if ($spaceId && ! in_array($spaceId, $spaceIds)) {
                $webhookUrl = $this->getWebhookUrl($spaceId);
                if ($webhookUrl == null) {
                    $webhookUrl = $this->createWebhookUrl($spaceId);
                }

                $existingListeners = $this->getWebhookListeners($spaceId, $webhookUrl);
                foreach ($this->webhookEntities as $webhookEntity) {
                    /* @var Entity $webhookEntity */
                    $exists = false;
                    foreach ($existingListeners as $existingListener) {
                        if ($existingListener->getEntity() == $webhookEntity->getId()) {
                            $exists = true;
                        }
                    }

                    if (! $exists) {
                        $this->createWebhookListener($webhookEntity, $spaceId, $webhookUrl);
                    }
                }
                $spaceIds[] = $spaceId;
            }
        }
    }

    /**
     * Create a webhook listener.
     *
     * @param Entity $entity
     * @param int $spaceId
     * @param \PostFinanceCheckout\Sdk\Model\WebhookUrl $webhookUrl
     * @return \PostFinanceCheckout\Sdk\Model\WebhookListenerCreate
     */
    private function createWebhookListener(Entity $entity, $spaceId, \PostFinanceCheckout\Sdk\Model\WebhookUrl $webhookUrl)
    {
        $webhookListener = new \PostFinanceCheckout\Sdk\Model\WebhookListenerCreate();
        $webhookListener->setEntity($entity->getId());
        $webhookListener->setEntityStates($entity->getStates());
        $webhookListener->setName('Shopware ' . $entity->getName());
        $webhookListener->setState(\PostFinanceCheckout\Sdk\Model\CreationEntityState::ACTIVE);
        $webhookListener->setUrl($webhookUrl->getId());
        $webhookListener->setNotifyEveryChange($entity->isNotifyEveryChange());
        return $this->webhookListenerService->create($spaceId, $webhookListener);
    }

    /**
     * Returns the existing webhook listeners.
     *
     * @param int $spaceId
     * @param \PostFinanceCheckout\Sdk\Model\WebhookUrl $webhookUrl
     * @return \PostFinanceCheckout\Sdk\Model\WebhookListener[]
     */
    private function getWebhookListeners($spaceId, \PostFinanceCheckout\Sdk\Model\WebhookUrl $webhookUrl)
    {
        $query = new \PostFinanceCheckout\Sdk\Model\EntityQuery();
        $filter = new \PostFinanceCheckout\Sdk\Model\EntityQueryFilter();
        $filter->setType(\PostFinanceCheckout\Sdk\Model\EntityQueryFilterType::_AND);
        $filter->setChildren(array(
            $this->createEntityFilter('state', \PostFinanceCheckout\Sdk\Model\CreationEntityState::ACTIVE),
            $this->createEntityFilter('url.id', $webhookUrl->getId())
        ));
        $query->setFilter($filter);
        return $this->webhookListenerService->search($spaceId, $query);
    }

    /**
     * Creates a webhook url.
     *
     * @param int $spaceId
     * @return \PostFinanceCheckout\Sdk\Model\WebhookUrlCreate
     */
    private function createWebhookUrl($spaceId)
    {
        $webhookUrl = new \PostFinanceCheckout\Sdk\Model\WebhookUrlCreate();
        $webhookUrl->setUrl($this->getHandleUrl());
        $webhookUrl->setState(\PostFinanceCheckout\Sdk\Model\CreationEntityState::ACTIVE);
        $webhookUrl->setName('Shopware 5');
        return $this->webhookUrlService->create($spaceId, $webhookUrl);
    }

    /**
     * Returns the existing webhook url if there is one.
     *
     * @param int $spaceId
     * @return \PostFinanceCheckout\Sdk\Model\WebhookUrl
     */
    private function getWebhookUrl($spaceId)
    {
        $query = new \PostFinanceCheckout\Sdk\Model\EntityQuery();
        $query->setNumberOfEntities(1);
        $filter = new \PostFinanceCheckout\Sdk\Model\EntityQueryFilter();
        $filter->setType(\PostFinanceCheckout\Sdk\Model\EntityQueryFilterType::_AND);
        $filter->setChildren(array(
            $this->createEntityFilter('state', \PostFinanceCheckout\Sdk\Model\CreationEntityState::ACTIVE),
            $this->createEntityFilter('url', $this->getHandleUrl())
        ));
        $query->setFilter($filter);
        $result = $this->webhookUrlService->search($spaceId, $query);
        if (! empty($result)) {
            return $result[0];
        } else {
            return null;
        }
    }

    /**
     * Returns the webhook endpoint URL.
     *
     * @return string
     */
    private function getHandleUrl()
    {
        return $this->getUrl('PostFinanceCheckoutPaymentWebhook', 'handle');
    }
}
