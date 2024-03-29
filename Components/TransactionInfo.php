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

namespace PostFinanceCheckoutPayment\Components;

use \PostFinanceCheckout\Sdk\Model\Transaction as TransactionModel;
use Shopware\Components\Model\ModelManager;
use Shopware\Components\Plugin\ConfigReader;
use Symfony\Component\DependencyInjection\ContainerInterface;
use PostFinanceCheckout\Sdk\Model\PaymentMethod;
use PostFinanceCheckout\Sdk\Model\Refund as RefundModel;
use PostFinanceCheckout\Sdk\Model\TransactionInvoice;
use PostFinanceCheckout\Sdk\Model\TransactionLineItemVersion;
use PostFinanceCheckoutPayment\Components\ArrayBuilder\TransactionInfo as TransactionInfoArrayBuilder;
use PostFinanceCheckoutPayment\Models\TransactionInfo as TransactionInfoModel;
use PostFinanceCheckoutPayment\Models\PaymentMethodConfiguration as PaymentMethodConfigurationModel;
use Shopware\Models\Order\Order;
use PostFinanceCheckout\Sdk\Model\FailureReason;
use Shopware\Models\Customer\Customer;

class TransactionInfo extends AbstractService
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
     * @var \PostFinanceCheckout\Sdk\ApiClient
     */
    private $apiClient;

    /**
     * Constructor.
     *
     * @param ContainerInterface $container
     * @param ModelManager $modelManager
     * @param ConfigReader $configReader
     * @param ApiClient $apiClient
     * @param LineItem $lineItem
     */
    public function __construct(ContainerInterface $container, ModelManager $modelManager, ConfigReader $configReader, ApiClient $apiClient)
    {
        parent::__construct($container);
        $this->container = $container;
        $this->modelManager = $modelManager;
        $this->configReader = $configReader;
        $this->apiClient = $apiClient->getInstance();
    }

    /**
     * Stores the transaction data in the database.
     *
     * @param \PostFinanceCheckout\Sdk\Model\Transaction $transaction
     * @param Order $order
     * @return TransactionInfoModel
     */
    public function updateTransactionInfoByOrder(\PostFinanceCheckout\Sdk\Model\Transaction $transaction, Order $order)
    {
        return $this->updateTransactionInfo($transaction, $order->getId(), $order->getShop()->getId(), $order->getPayment()->getId(), $order->getCustomer());
    }
    
    /**
     * Stores the transaction data in the database.
     *
     * @param \PostFinanceCheckout\Sdk\Model\Transaction $transaction
     * @param int $orderId
     * @param int $shopId
     * @param int $paymentId
     * @param Customer $customer
     * @return TransactionInfoModel
     */
    public function updateTransactionInfo(\PostFinanceCheckout\Sdk\Model\Transaction $transaction, $orderId, $shopId, $paymentId, Customer $customer)
    {
        try {
            $info = $this->loadTransactionInfo($transaction, $orderId);
            if (! ($info instanceof TransactionInfoModel)) {
                $info = new TransactionInfoModel();
            }
            $info->setTransactionId($transaction->getId());
            $info->setAuthorizationAmount($transaction->getAuthorizationAmount());
            $info->setOrderId($orderId);
            $info->setShopId($shopId);
            $info->setState($transaction->getState());
            $info->setSpaceId($transaction->getLinkedSpaceId());
            $info->setSpaceViewId($transaction->getSpaceViewId());
            $info->setLanguage($transaction->getLanguage());
            $info->setCurrency($transaction->getCurrency());
            $info->setConnectorId($transaction->getPaymentConnectorConfiguration() != null ? $transaction->getPaymentConnectorConfiguration()
                ->getConnector() : null);
            $info->setPaymentMethodId($transaction->getPaymentConnectorConfiguration() != null && $transaction->getPaymentConnectorConfiguration()
                ->getPaymentMethodConfiguration() != null ? $transaction->getPaymentConnectorConfiguration()
                ->getPaymentMethodConfiguration()
                ->getPaymentMethod() : null);
            $info->setImage($this->getPaymentMethodImage($transaction, $paymentId));
            $info->setLabels($this->getTransactionLabels($transaction));
            if ($transaction->getState() == \PostFinanceCheckout\Sdk\Model\TransactionState::FAILED || $transaction->getState() == \PostFinanceCheckout\Sdk\Model\TransactionState::DECLINE) {
                $info->setFailureReason($transaction->getFailureReason() instanceof FailureReason ? $transaction->getFailureReason()->getDescription() : null);
                $info->setUserFailureMessage($transaction->getUserFailureMessage());
            }
            $this->modelManager->persist($info);
            $this->modelManager->flush($info);
            $this->updateCustomerData($customer, $transaction);
            return $info;
        } catch (\PDOException $e) {
            return $this->loadTransactionInfo($transaction, $orderId);
        } catch (\Doctrine\DBAL\DBALException $e) {
            return $this->loadTransactionInfo($transaction, $orderId);
        }
    }
    
    private function loadTransactionInfo(\PostFinanceCheckout\Sdk\Model\Transaction $transaction, $orderId)
    {
        $info = $this->modelManager->getRepository(TransactionInfoModel::class)->findOneBy([
            'orderId' => $orderId
        ]);
        if (! ($info instanceof TransactionInfoModel)) {
            $info = $this->modelManager->getRepository(TransactionInfoModel::class)->findOneBy([
                'spaceId' => $transaction->getLinkedSpaceId(),
                'transactionId' => $transaction->getId()
            ]);
        }
        return $info;
    }
    
    private function updateCustomerData(Customer $customer, \PostFinanceCheckout\Sdk\Model\Transaction $transaction)
    {
        $billingAddress = $customer->getDefaultBillingAddress();
        
        if ($customer->getBirthday() == null && $transaction->getBillingAddress()->getDateOfBirth() != null) {
            $customer->setBirthday($transaction->getBillingAddress()->getDateOfBirth());
        }
        
        $customerBillingPhone = $billingAddress->getPhone();
        if (empty($customerBillingPhone)) {
            $transactionBillingPhone = $transaction->getBillingAddress()->getPhoneNumber();
            $transactionBillingMobile = $transaction->getBillingAddress()->getPhoneNumber();
            if (!empty($transactionBillingPhone)) {
                $billingAddress->setPhone($transactionBillingPhone);
            } elseif (!empty($transactionBillingMobile)) {
                $billingAddress->setPhone($transactionBillingMobile);
            }
        }
        
        $billingVatId = $billingAddress->getVatId();
        $transactionSalesTaxNumber = $transaction->getBillingAddress()->getSalesTaxNumber();
        if (empty($billingVatId) && !empty($transactionSalesTaxNumber)) {
            $billingAddress->setVatId($transactionSalesTaxNumber);
        }
        
        $this->modelManager->persist($billingAddress);
        $this->modelManager->persist($customer);
        $this->modelManager->flush([$billingAddress, $customer]);
    }

    /**
     * Returns an array of the transaction's labels.
     *
     * @param \PostFinanceCheckout\Sdk\Model\Transaction $transaction
     * @return string[]
     */
    private function getTransactionLabels(\PostFinanceCheckout\Sdk\Model\Transaction $transaction)
    {
        $chargeAttempt = $this->getChargeAttempt($transaction);
        if ($chargeAttempt != null) {
            $labels = array();
            foreach ($chargeAttempt->getLabels() as $label) {
                $labels[$label->getDescriptor()->getId()] = $label->getContentAsString();
            }

            return $labels;
        } else {
            return array();
        }
    }

    /**
     * Returns the successful charge attempt of the transaction.
     *
     * @return \PostFinanceCheckout\Sdk\Model\ChargeAttempt
     */
    private function getChargeAttempt(\PostFinanceCheckout\Sdk\Model\Transaction $transaction)
    {
        return $this->callApi($this->apiClient, function () use ($transaction) {
            $chargeAttemptService = new \PostFinanceCheckout\Sdk\Service\ChargeAttemptService($this->apiClient);
            $query = new \PostFinanceCheckout\Sdk\Model\EntityQuery();
            $filter = new \PostFinanceCheckout\Sdk\Model\EntityQueryFilter();
            $filter->setType(\PostFinanceCheckout\Sdk\Model\EntityQueryFilterType::_AND);
            $filter->setChildren(array(
                $this->createEntityFilter('charge.transaction.id', $transaction->getId()),
                $this->createEntityFilter('state', \PostFinanceCheckout\Sdk\Model\ChargeAttemptState::SUCCESSFUL)
            ));
            $query->setFilter($filter);
            $query->setNumberOfEntities(1);
            $result = $chargeAttemptService->search($transaction->getLinkedSpaceId(), $query);
            if ($result != null && ! empty($result)) {
                return current($result);
            } else {
                return null;
            }
        });
    }

    /**
     * Returns the payment method's image.
     *
     * @param \PostFinanceCheckout\Sdk\Model\Transaction $transaction
     * @param int $paymentId
     * @return string
     */
    private function getPaymentMethodImage(\PostFinanceCheckout\Sdk\Model\Transaction $transaction, $paymentId)
    {
        if ($transaction->getPaymentConnectorConfiguration() == null) {
            if ($paymentId == null) {
                return null;
            }
            /* @var PaymentMethodConfigurationModel $paymentMethodConfiguration */
            $paymentMethodConfiguration = $this->modelManager->getRepository(PaymentMethodConfigurationModel::class)->findOneBy([
                'paymentId' => $paymentId
            ]);
            if ($paymentMethodConfiguration instanceof PaymentMethodConfigurationModel) {
                return $paymentMethodConfiguration->getImage();
            } else {
                return null;
            }
        }

        /* @var \PostFinanceCheckoutPayment\Components\Provider\PaymentConnector $connectorProvider */
        $connectorProvider = $this->container->get('postfinancecheckout_payment.provider.payment_connector');
        $connector = $connectorProvider->find($transaction->getPaymentConnectorConfiguration()
            ->getConnector());

        /* @var \PostFinanceCheckoutPayment\Components\Provider\PaymentMethod $methodProvider */
        $methodProvider = $this->container->get('postfinancecheckout_payment.provider.payment_method');
        $method = $transaction->getPaymentConnectorConfiguration()->getPaymentMethodConfiguration() != null ? $methodProvider->find($transaction->getPaymentConnectorConfiguration()
            ->getPaymentMethodConfiguration()
            ->getPaymentMethod()) : null;

        if ($transaction->getPaymentConnectorConfiguration()->getPaymentMethodConfiguration() != null) {
            return $this->getImagePath($transaction->getPaymentConnectorConfiguration()
                    ->getPaymentMethodConfiguration()
                    ->getResolvedImageUrl());
        } elseif ($method != null) {
            return $method->getImagePath();
        } else {
            if ($paymentId == null) {
                return null;
            }
            /* @var PaymentMethodConfigurationModel $paymentMethodConfiguration */
            $paymentMethodConfiguration = $this->modelManager->getRepository(PaymentMethodConfigurationModel::class)->findOneBy([
                'paymentId' => $paymentId
            ]);
            if ($paymentMethodConfiguration instanceof PaymentMethodConfigurationModel) {
                return $paymentMethodConfiguration->getImage();
            } else {
                return null;
            }
        }
    }
    
    /**
     * @param string $resolvedImageUrl
     * @return string
     */
    private function getImagePath($resolvedImageUrl)
    {
        $index = strpos($resolvedImageUrl, 'resource/');
        return substr($resolvedImageUrl, $index + strlen('resource/'));
    }

    /**
     *
     * @param TransactionInfoModel $transactionInfo
     * @return array
     */
    public function buildTransactionInfoAsArray(TransactionInfoModel $transactionInfo)
    {
        $builder = new TransactionInfoArrayBuilder($this->container, $transactionInfo);
        $builder->setPaymentMethod($this->getPaymentMethod($transactionInfo));
        $builder->setTransaction($this->getTransaction($transactionInfo));
        $builder->setLineItemVersion($this->getLineItemVersion($transactionInfo));
        $builder->setInvoice($this->getInvoice($transactionInfo));
        $builder->setRefunds($this->getRefunds($transactionInfo));
        return $builder->build();
    }

    /**
     *
     * @param TransactionInfoModel $transactionInfo
     * @return PaymentMethod|NULL
     */
    private function getPaymentMethod(TransactionInfoModel $transactionInfo)
    {
        try {
            $paymentMethod = $this->container->get('postfinancecheckout_payment.provider.payment_method')->find($transactionInfo->getPaymentMethodId());
            if ($paymentMethod instanceof PaymentMethod) {
                return $paymentMethod;
            }
        } catch (\Exception $e) {
        }
        // If payment methods cannot be loaded from PostFinance Checkout, information about the payment method cannot be displayed.
        return null;
    }

    /**
     *
     * @param TransactionInfoModel $transactionInfo
     * @return TransactionModel|NULL
     */
    private function getTransaction(TransactionInfoModel $transactionInfo)
    {
        try {
            $transaction = $this->container->get('postfinancecheckout_payment.transaction')->getTransaction($transactionInfo->getSpaceId(), $transactionInfo->getTransactionId());
            if ($transaction instanceof TransactionModel) {
                return $transaction;
            }
        } catch (\Exception $e) {
        }
        return null;
    }

    /**
     *
     * @param TransactionInfoModel $transactionInfo
     * @return TransactionInvoice|NULL
     */
    private function getInvoice(TransactionInfoModel $transactionInfo)
    {
        try {
            $invoice = $this->container->get('postfinancecheckout_payment.invoice')->getInvoice($transactionInfo->getSpaceId(), $transactionInfo->getTransactionId());
            if ($invoice instanceof TransactionInvoice) {
                return $invoice;
            }
        } catch (\Exception $e) {
        }
        return null;
    }

    /**
     *
     * @param TransactionInfoModel $transactionInfo
     * @return TransactionLineItemVersion|NULL
     */
    private function getLineItemVersion(TransactionInfoModel $transactionInfo)
    {
        try {
            $lineItemVersion = $this->container->get('postfinancecheckout_payment.transaction')->getLineItemVersion($transactionInfo->getSpaceId(), $transactionInfo->getTransactionId());
            if ($lineItemVersion instanceof TransactionLineItemVersion) {
                return $lineItemVersion;
            }
        } catch (\Exception $e) {
        }
        return null;
    }

    /**
     *
     * @param TransactionInfoModel $transactionInfo
     * @return RefundModel[]|array
     */
    private function getRefunds(TransactionInfoModel $transactionInfo)
    {
        try {
            $refunds = $this->container->get('postfinancecheckout_payment.refund')->getRefunds($transactionInfo->getSpaceId(), $transactionInfo->getTransactionId());
            if (is_array($refunds)) {
                return $refunds;
            }
        } catch (\Exception $e) {
        }
        return [];
    }
}
