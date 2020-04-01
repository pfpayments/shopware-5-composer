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

namespace PostFinanceCheckoutPayment\Commands;

use Shopware\Commands\ShopwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use PostFinanceCheckoutPayment\Models\TransactionInfo as TransactionInfoModel;
use PostFinanceCheckout\Sdk\Model\TransactionInvoice;
use PostFinanceCheckout\Sdk\Model\Transaction as TransactionModel;

class UpdateTransactionCommand extends ShopwareCommand
{
    protected function configure()
    {
        $this
            ->setName('postfinancecheckout:transaction:update')
            ->setDescription('PostFinance Checkout: Update transactions');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
            require_once __DIR__ . '/../vendor/autoload.php';
        }
        
        if (Shopware()->Front()->Request() == null) {
            Shopware()->Front()->setRequest(new \Enlight_Controller_Request_RequestHttp());
        }
        
        /* @var \PostFinanceCheckoutPayment\Components\Transaction $transactionService */
        $transactionService = $this->getContainer()->get('postfinancecheckout_payment.transaction');
        /* @var \PostFinanceCheckoutPayment\Components\Invoice $invoiceService */
        $invoiceService = $this->getContainer()->get('postfinancecheckout_payment.invoice');
        /* @var \PostFinanceCheckoutPayment\Components\TransactionInfo $transactionInfoService */
        $transactionInfoService = $this->getContainer()->get('postfinancecheckout_payment.transaction_info');
        /* @var \PostFinanceCheckoutPayment\Subscriber\Webhook\Transaction $transactionWebhookService */
        $transactionWebhookService = $this->getContainer()->get('postfinancecheckout_payment.subscriber.webhook.transaction');
        /* @var \PostFinanceCheckoutPayment\Subscriber\Webhook\TransactionInvoice $invoiceWebhookService */
        $invoiceWebhookService = $this->getContainer()->get('postfinancecheckout_payment.subscriber.webhook.transaction_invoice');
        
        // Can only update one transaction at a time, because the context changes.
        $transactionInfos = $this->getContainer()->get('models')->getRepository(TransactionInfoModel::class)->findBy([
            'state' => 'CONFIRMED'
        ], null, 1, null);
        foreach ($transactionInfos as $transactionInfo) {
            /* @var TransactionInfoModel $transactionInfo */
            $this->getContainer()->set('shop', $transactionInfo->getShop());
            $transaction = $transactionService->getTransaction($transactionInfo->getSpaceId(), $transactionInfo->getTransactionId());
            if ($transaction instanceof TransactionModel) {
                $transactionWebhookService->process($transaction);
            
                $invoice = $invoiceService->getInvoice($transactionInfo->getSpaceId(), $transactionInfo->getTransactionId());
                if ($invoice instanceof TransactionInvoice) {
                    $invoiceWebhookService->process($invoice);
                }
            
                $output->writeln('Updated transaction ' . $transaction->getId() . '.');
            }
        }
        
        return 0;
    }
}
