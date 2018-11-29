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

namespace PostFinanceCheckoutPayment\Subscriber\Webhook;

use PostFinanceCheckoutPayment\Components\Webhook\Request as WebhookRequest;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Order\Order;
use Shopware\Models\Order\Status;
use PostFinanceCheckoutPayment\Components\ApiClient;
use PostFinanceCheckout\Sdk\Service\DeliveryIndicationService;

class DeliveryIndication extends AbstractOrderRelatedSubscriber
{
    public static function getSubscribedEvents()
    {
        return [
            'PostFinanceCheckout_Payment_Webhook_DeliveryIndication' => 'handle'
        ];
    }

    /**
     *
     * @var DeliveryIndicationService
     */
    private $deliveryIndicationService;

    /**
     *
     * @param ModelManager $modelManager
     * @param ApiClient $apiClient
     */
    public function __construct(ModelManager $modelManager, ApiClient $apiClient)
    {
        parent::__construct($modelManager);
        $this->deliveryIndicationService = new DeliveryIndicationService($apiClient->getInstance());
    }

    /**
     *
     * @param WebhookRequest $request
     * @return \PostFinanceCheckout\Sdk\Model\DeliveryIndication
     */
    protected function loadEntity(WebhookRequest $request)
    {
        return $this->callApi($this->deliveryIndicationService->getApiClient(), function () use ($request) {
            $this->deliveryIndicationService->read($request->getSpaceId(), $request->getEntityId());
        });
    }

    /**
     *
     * @param \PostFinanceCheckout\Sdk\Model\DeliveryIndication $deliveryIndication
     * @return string
     */
    protected function getOrderNumber($deliveryIndication)
    {
        return $deliveryIndication->getTransaction()->getMerchantReference();
    }

    /**
     *
     * @param \PostFinanceCheckout\Sdk\Model\DeliveryIndication $deliveryIndication
     * @return int
     */
    protected function getTransactionId($deliveryIndication)
    {
        return $deliveryIndication->getLinkedTransaction();
    }

    /**
     *
     * @param Order $order
     * @param \PostFinanceCheckout\Sdk\Model\DeliveryIndication $deliveryIndication
     */
    protected function handleOrderRelatedInner(Order $order, $deliveryIndication)
    {
        switch ($deliveryIndication->getState()) {
            case \PostFinanceCheckout\Sdk\Model\DeliveryIndicationState::MANUAL_CHECK_REQUIRED:
                $this->review($order, $deliveryIndication);
                break;
            default:
                // Nothing to do.
                break;
        }
    }

    private function review(Order $order, \PostFinanceCheckout\Sdk\Model\DeliveryIndication $deliveryIndication)
    {
        $order->setOrderStatus($this->getStatus(Status::ORDER_STATE_CLARIFICATION_REQUIRED));
        $this->modelManager->flush($order);
    }

    private function getStatus($statusId)
    {
        return $this->modelManager->getRepository(Status::class)->find($statusId);
    }
}
