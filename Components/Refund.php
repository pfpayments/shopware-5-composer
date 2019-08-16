<?php

/**
 * PostFinance Checkout Shopware
 *
 * This Shopware extension enables to process payments with PostFinance Checkout (https://www.postfinance.ch/checkout/).
 *
 * @package PostFinanceCheckout_Payment
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */

namespace PostFinanceCheckoutPayment\Components;

use Shopware\Components\Model\ModelManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use PostFinanceCheckout\Sdk\Service\RefundService;
use PostFinanceCheckout\Sdk\Model\EntityQuery;
use Shopware\Models\Order\Order;

class Refund extends AbstractService
{

    /**
     *
     * @var ModelManager
     */
    private $modelManager;

    /**
     *
     * @var RefundService
     */
    private $refundService;

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
        $this->refundService = new RefundService($apiClient->getInstance());
    }

    /**
     *
     * @param int $spaceId
     * @param int $transactionId
     * @return \PostFinanceCheckout\Sdk\Model\Refund[]
     */
    public function getRefunds($spaceId, $transactionId)
    {
        return $this->callApi($this->refundService->getApiClient(), function () use ($spaceId, $transactionId) {
            $query = new EntityQuery();
            $query->setFilter($this->createEntityFilter('transaction.id', $transactionId));
            $query->setOrderBys([
                $this->createEntityOrderBy('createdOn', \PostFinanceCheckout\Sdk\Model\EntityQueryOrderByType::DESC)
            ]);
            $query->setNumberOfEntities(50);
            return $this->refundService->search($spaceId, $query);
        });
    }

    /**
     *
     * @param \PostFinanceCheckout\Sdk\Model\TransactionInvoice $invoice
     * @param \PostFinanceCheckout\Sdk\Model\Refund[] $refunds
     * @return \PostFinanceCheckout\Sdk\Model\LineItem[]
     */
    public function getRefundBaseLineItems(\PostFinanceCheckout\Sdk\Model\TransactionInvoice $invoice = null, array $refunds = [])
    {
        $refund = $this->getLastSuccessfulRefund($refunds);
        if ($refund) {
            return $refund->getReducedLineItems();
        } elseif ($invoice != null) {
            return $invoice->getLineItems();
        } else {
            return [];
        }
    }

    /**
     *
     * @param \PostFinanceCheckout\Sdk\Model\Refund[] $refunds
     */
    private function getLastSuccessfulRefund(array $refunds)
    {
        foreach ($refunds as $refund) {
            if ($refund->getState() == \PostFinanceCheckout\Sdk\Model\RefundState::SUCCESSFUL) {
                return $refund;
            }
        }
        return false;
    }

    /**
     *
     * @param Order $order
     * @param \PostFinanceCheckout\Sdk\Model\Transaction $transaction
     * @param array $reductions
     */
    public function createRefund(Order $order, \PostFinanceCheckout\Sdk\Model\Transaction $transaction, array $reductions)
    {
        $refund = new \PostFinanceCheckout\Sdk\Model\RefundCreate();
        $refund->setExternalId(uniqid($order->getNumber() . '-'));
        $refund->setReductions($reductions);
        $refund->setTransaction($transaction->getId());
        $refund->setType(\PostFinanceCheckout\Sdk\Model\RefundType::MERCHANT_INITIATED_ONLINE);
        return $refund;
    }

    /**
     *
     * @param int $spaceId
     * @param \PostFinanceCheckout\Sdk\Model\RefundCreate $refundRequest
     * @return \PostFinanceCheckout\Sdk\Model\Refund
     */
    public function refund($spaceId, \PostFinanceCheckout\Sdk\Model\RefundCreate $refundRequest)
    {
        return $this->refundService->refund($spaceId, $refundRequest);
    }
}
