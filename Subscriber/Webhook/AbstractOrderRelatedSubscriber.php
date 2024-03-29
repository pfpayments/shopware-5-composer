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

use PostFinanceCheckoutPayment\Components\Webhook\Request as WebhookRequest;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Order\Order;
use PostFinanceCheckoutPayment\Models\OrderTransactionMapping;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Connection;
use PostFinanceCheckoutPayment\Models\TransactionInfo;

abstract class AbstractOrderRelatedSubscriber extends AbstractSubscriber
{

    /**
     *
     * @var ModelManager
     */
    protected $modelManager;

    public function __construct(ModelManager $modelManager)
    {
        $this->modelManager = $modelManager;
    }

    public function handle(\Enlight_Event_EventArgs $args)
    {
        /* @var WebhookRequest $request */
        $request = $args->get('request');

        $entity = $this->loadEntity($request);
        $this->process($entity);
    }

    public function process($entity)
    {
        $this->beginTransaction();
        try {
            $orderId = $this->getOrderId($entity);
            if ($orderId != null) {
                /* @var Order $order */
                $order = $this->modelManager->getRepository(Order::class)->find($orderId);
                if ($order instanceof Order) {
                    /* @var OrderTransactionMapping $orderTransactionMapping */
                    $orderTransactionMapping = $this->modelManager->getRepository(OrderTransactionMapping::class)->findOneBy([
                        'orderId' => $order->getId()
                    ]);
                    if (! ($orderTransactionMapping instanceof OrderTransactionMapping) || $orderTransactionMapping->getTransactionId() != $this->getTransactionId($entity)) {
                        return;
                    }
                    $order = $this->modelManager->getRepository(Order::class)->find($order->getId(), LockMode::PESSIMISTIC_WRITE);
                    $this->handleOrderRelatedInner($order, $entity);
                }
            }

            $this->modelManager->commit();
        } catch (\Exception $e) {
            $this->modelManager->rollback();
            throw $e;
        }
    }

    protected function beginTransaction()
    {
        $this->modelManager->getConnection()->setTransactionIsolation(Connection::TRANSACTION_READ_COMMITTED);
        $this->modelManager->beginTransaction();
    }

    /**
     * Loads and returns the entity for the webhook request.
     *
     * @param WebhookRequest $request
     * @return object
     */
    abstract protected function loadEntity(WebhookRequest $request);

    /**
     * Returns the ID of the order linked to the entity.
     *
     * @param object $entity
     * @return int
     */
    protected function getOrderId($entity)
    {
        /* @var TransactionInfo $transactionInfo */
        $transactionInfo = $this->modelManager->getRepository(TransactionInfo::class)->findOneBy([
            'spaceId' => $entity->getLinkedSpaceId(),
            'transactionId' => $this->getTransactionId($entity)
        ]);
        if ($transactionInfo instanceof TransactionInfo) {
            return $transactionInfo->getOrderId();
        } else {
            return null;
        }
    }

    /**
     * Returns the transaction's id linked to the entity.
     *
     * @param object $entity
     * @return int
     */
    abstract protected function getTransactionId($entity);

    /**
     * Actually processes the order related webhook request.
     *
     * This must be implemented
     *
     * @param Order $order
     * @param mixed $entity
     */
    abstract protected function handleOrderRelatedInner(Order $order, $entity);
}
