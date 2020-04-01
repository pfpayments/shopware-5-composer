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

namespace PostFinanceCheckoutPayment\Components\ArrayBuilder;

use PostFinanceCheckout\Sdk\Model\Refund as RefundModel;
use PostFinanceCheckoutPayment\Components\ArrayBuilder\LineItem as LineItemArrayBuilder;
use PostFinanceCheckoutPayment\Components\ArrayBuilder\Label as LabelArrayBuilder;
use PostFinanceCheckoutPayment\Components\ArrayBuilder\LabelGroup as LabelGroupArrayBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use PostFinanceCheckout\Sdk\Model\TransactionInvoice;

class Refund extends AbstractArrayBuilder
{
    /**
     *
     * @var RefundModel
     */
    private $refund;

    /**
     * Constructor.
     *
     * @param ContainerInterface $container
     * @param RefundModel $refund
     */
    public function __construct(ContainerInterface $container, RefundModel $refund)
    {
        parent::__construct($container);
        $this->refund = $refund;
    }

    public function build()
    {
        return [
            'id' => $this->refund->getId(),
            'state' => $this->refund->getState(),
            'createdOn' => $this->refund->getCreatedOn(),
            'amount' => $this->refund->getAmount(),
            'externalId' => $this->refund->getExternalId(),
            'failureReason' => $this->refund->getFailureReason() != null ? $this->translate($this->refund->getFailureReason()
                ->getDescription()) : null,
            'labels' => LabelGroupArrayBuilder::buildGrouped($this->container, $this->getLabelBuilders()),
            'lineItems' => $this->getLineItems()
        ];
    }

    /**
     *
     * @param ContainerInterface $container
     * @param TransactionInvoice $invoice
     * @param Refund[] $refunds
     * @return array
     */
    public static function buildBaseLineItems(ContainerInterface $container, TransactionInvoice $invoice = null, array $refunds = [])
    {
        /* @var \PostFinanceCheckoutPayment\Components\Refund $refundService */
        $refundService = $container->get('postfinancecheckout_payment.refund');

        $result = [];
        foreach ($refundService->getRefundBaseLineItems($invoice, $refunds) as $lineItem) {
            $lineItemBuilder = new LineItemArrayBuilder($container, $lineItem);
            $result[] = $lineItemBuilder->build();
        }
        return $result;
    }

    /**
     *
     * @return LabelArrayBuilder[]
     */
    private function getLabelBuilders()
    {
        /** @var \PostFinanceCheckoutPayment\Components\Provider\LabelDescriptorProvider $labelDescriptorProvider */
        $labelDescriptorProvider = $this->container->get('postfinancecheckout_payment.provider.label_descriptor');

        $labels = [];
        try {
            foreach ($this->refund->getLabels() as $label) {
                $labels[] = new LabelArrayBuilder($this->container, $label->getDescriptor(), $label->getContentAsString());
            }
        } catch (\Exception $e) {
            // If label descriptors and label descriptor groups cannot be loaded from PostFinance Checkout, the labels cannot be displayed.
        }
        return $labels;
    }

    /**
     *
     * @return LineItemArrayBuilder[]
     */
    private function getLineItems()
    {
        $lineItems = [];
        foreach ($this->refund->getLineItems() as $lineItem) {
            $lineItemBuilder = new LineItemArrayBuilder($this->container, $lineItem);
            $lineItems[] = $lineItemBuilder->build();
        }
        return $lineItems;
    }
}
