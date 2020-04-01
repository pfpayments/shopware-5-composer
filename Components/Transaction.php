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

use Shopware\Components\Model\ModelManager;
use Shopware\Components\Plugin\ConfigReader;
use Shopware\Models\Order\Order;
use Shopware\Models\Shop\Shop;
use Symfony\Component\DependencyInjection\ContainerInterface;
use PostFinanceCheckoutPayment\Components\PaymentMethodConfiguration as PaymentMethodConfigurationService;
use PostFinanceCheckoutPayment\Components\TransactionInfo as TransactionInfoService;
use PostFinanceCheckoutPayment\Models\OrderTransactionMapping;
use PostFinanceCheckout\Sdk\Model\EntityQuery;
use PostFinanceCheckout\Sdk\Model\EntityQueryFilter;
use PostFinanceCheckout\Sdk\Model\EntityQueryFilterType;
use PostFinanceCheckout\Sdk\Model\EntityQueryOrderByType;
use PostFinanceCheckout\Sdk\Model\TransactionState;
use Shopware\Models\Customer\Customer;
use PostFinanceCheckout\Sdk\ApiException;

class Transaction extends AbstractService
{

    /**
     *
     * @var \PostFinanceCheckout\Sdk\Model\Transaction[]
     */
    private static $transactionByOrderCache = array();

    /**
     *
     * @var \PostFinanceCheckout\Sdk\Model\Transaction[]
     */
    private static $transactionByBasketCache = null;

    /**
     *
     * @var \PostFinanceCheckout\Sdk\Model\PaymentMethodConfiguration[]
     */
    private static $possiblePaymentMethodByOrderCache = array();

    /**
     *
     * @var \PostFinanceCheckout\Sdk\Model\PaymentMethodConfiguration[]
     */
    private static $possiblePaymentMethodByBasketCache = null;

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
     *
     * @var LineItem
     */
    private $lineItem;

    /**
     *
     * @var PaymentMethodConfigurationService
     */
    private $paymentMethodConfigurationService;

    /**
     *
     * @var TransactionInfoService
     */
    private $transactionInfoService;

    /**
     *
     * @var Session
     */
    private $sessionService;
    
    /**
     * The transaction API service.
     *
     * @var \PostFinanceCheckout\Sdk\Service\TransactionService
     */
    private $transactionService;
    
    /**
     * The transaction iframe API service.
     *
     * @var \PostFinanceCheckout\Sdk\Service\TransactionIframeService
     */
    private $transactionIframeService;
    
    /**
     * The transaction payment page API service.
     *
     * @var \PostFinanceCheckout\Sdk\Service\TransactionPaymentPageService
     */
    private $transactionPaymentPageService;

    /**
     * Constructor.
     *
     * @param ContainerInterface $container
     * @param ModelManager $modelManager
     * @param ConfigReader $configReader
     * @param ApiClient $apiClient
     * @param LineItem $lineItem
     * @param PaymentMethodConfigurationService $paymentMethodConfigurationService
     * @param TransactionInfoService $transactionInfoService
     */
    public function __construct(ContainerInterface $container, ModelManager $modelManager, ConfigReader $configReader, ApiClient $apiClient, LineItem $lineItem, PaymentMethodConfigurationService $paymentMethodConfigurationService, TransactionInfoService $transactionInfoService, Session $sessionService)
    {
        parent::__construct($container);
        $this->container = $container;
        $this->modelManager = $modelManager;
        $this->configReader = $configReader;
        $this->apiClient = $apiClient->getInstance();
        $this->lineItem = $lineItem;
        $this->paymentMethodConfigurationService = $paymentMethodConfigurationService;
        $this->transactionInfoService = $transactionInfoService;
        $this->sessionService = $sessionService;
        $this->transactionService = new \PostFinanceCheckout\Sdk\Service\TransactionService($this->apiClient);
        $this->transactionIframeService = new \PostFinanceCheckout\Sdk\Service\TransactionIframeService($this->apiClient);
        $this->transactionPaymentPageService = new \PostFinanceCheckout\Sdk\Service\TransactionPaymentPageService($this->apiClient);
    }

    /**
     * Returns the transaction with the given id.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @return \PostFinanceCheckout\Sdk\Model\Transaction
     */
    public function getTransaction($spaceId, $transactionId)
    {
        return $this->callApi($this->apiClient, function () use ($spaceId, $transactionId) {
            return $this->transactionService->read($spaceId, $transactionId);
        });
    }

    /**
     *
     * @param int $spaceId
     * @param int $transactionId
     */
    public function handleTransactionState($spaceId, $transactionId)
    {
        $transaction = $this->getTransaction($spaceId, $transactionId);
        $this->container->get('postfinancecheckout_payment.subscriber.webhook.transaction')->process($transaction);
    }

    /**
     * Returns the transaction's latest line item version.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @return \PostFinanceCheckout\Sdk\Model\TransactionLineItemVersion
     */
    public function getLineItemVersion($spaceId, $transactionId)
    {
        return $this->callApi($this->apiClient, function () use ($spaceId, $transactionId) {
            return $this->transactionService->getLatestTransactionLineItemVersion($spaceId, $transactionId);
        });
    }

    /**
     * Updates the line items of the given transaction.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @param \PostFinanceCheckout\Sdk\Model\LineItem[] $lineItems
     * @return \PostFinanceCheckout\Sdk\Model\TransactionLineItemVersion
     */
    public function updateLineItems($spaceId, $transactionId, $lineItems)
    {
        $updateRequest = new \PostFinanceCheckout\Sdk\Model\TransactionLineItemUpdateRequest();
        $updateRequest->setTransactionId($transactionId);
        $updateRequest->setNewLineItems($lineItems);
        return $this->transactionService->updateTransactionLineItems($spaceId, $updateRequest);
    }

    /**
     * Returns the URL to PostFinance Checkout's JavaScript library that is necessary to display the payment form.
     *
     * @param Order $order
     * @return string
     */
    public function getJavaScriptUrl()
    {
        $transaction = $this->getTransactionByBasket();
        if (! ($transaction instanceof \PostFinanceCheckout\Sdk\Model\Transaction)) {
            throw new \Exception('No order transaction mapping found to build javascript URL.');
        }

        return $this->callApi($this->apiClient, function () use ($transaction) {
        	return $this->transactionIframeService->javascriptUrl($transaction->getLinkedSpaceId(), $transaction->getId());
        });
    }

    public function getDeviceJavascriptUrl()
    {
        $baseUrl = $this->container->getParameter('post_finance_checkout_payment.base_gateway_url');

        $pluginConfig = $this->configReader->getByPluginName('PostFinanceCheckoutPayment', $this->container->get('shop'));
        $spaceId = $pluginConfig['spaceId'];

        $deviceId = Shopware()->Front()
            ->Request()
            ->getCookie('postfinancecheckout_device_id');
        if (empty($deviceId)) {
            $deviceId = $this->generateDeviceId();
            Shopware()->Front()
                ->Response()
                ->setCookie('postfinancecheckout_device_id', $deviceId);
        }

        return $baseUrl . '/s/' . $spaceId . '/payment/device.js?sessionIdentifier=' . $deviceId;
    }

    private function generateDeviceId()
    {
        $data = \openssl_random_pseudo_bytes(16);
        $data[6] = \chr(\ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = \chr(\ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
        return \vsprintf('%s%s-%s-%s-%s-%s%s%s', \str_split(\bin2hex($data), 4));
    }

    /**
     * Returns the URL to PostFinance Checkout's payment page.
     *
     * @param Order $order
     * @return string
     */
    public function getPaymentPageUrl()
    {
        $transaction = $this->getTransactionByBasket();
        if (! ($transaction instanceof \PostFinanceCheckout\Sdk\Model\Transaction)) {
            throw new \Exception('No order transaction mapping found to build javascript URL.');
        }

        return $this->callApi($this->apiClient, function () use ($transaction) {
            return $this->transactionPaymentPageService->paymentPageUrl($transaction->getLinkedSpaceId(), $transaction->getId());
        });
    }

    /**
     * Returns the payment methods that can be used with the given order.
     *
     * @param Order $order
     * @return \PostFinanceCheckout\Sdk\Model\PaymentMethodConfiguration[]
     */
    public function getPossiblePaymentMethods(Order $order)
    {
        if (! isset(static::$possiblePaymentMethodByOrderCache[$order->getId()]) || static::$possiblePaymentMethodByOrderCache[$order->getId()] == null) {
            $transaction = $this->getTransactionByOrder($order);
            $paymentMethods = $this->callApi($this->apiClient, function () use ($transaction) {
                return $this->transactionService->fetchPossiblePaymentMethods($transaction->getLinkedSpaceId(), $transaction->getId());
            });

            foreach ($paymentMethods as $paymentMethod) {
                $this->paymentMethodConfigurationService->updateData($paymentMethod);
            }

            static::$possiblePaymentMethodByOrderCache[$order->getId()] = $paymentMethods;
        }

        return static::$possiblePaymentMethodByOrderCache[$order->getId()];
    }

    /**
     * Returns the payment methods that can be used with the given basket.
     *
     * @return \PostFinanceCheckout\Sdk\Model\PaymentMethodConfiguration[]
     */
    public function getPossiblePaymentMethodsByBasket()
    {
        /* @var \Enlight_Components_Session_Namespace $session */
        $session = $this->container->get('session');

        if (static::$possiblePaymentMethodByBasketCache == null) {
            if (! $this->isBasketTransactionChanged() && isset($session['postfinancecheckout_payment.possible_payment_methods']) && ! empty($session['postfinancecheckout_payment.possible_payment_methods'])) {
                $paymentMethods = $session['postfinancecheckout_payment.possible_payment_methods'];
            } else {
                $transaction = $this->getTransactionByBasket();
                try {
                    $paymentMethods = $this->callApi($this->apiClient, function () use ($transaction) {
                        return $this->transactionService->fetchPossiblePaymentMethods($transaction->getLinkedSpaceId(), $transaction->getId());
                    });
                } catch (ApiException $e) {
                    static::$possiblePaymentMethodByBasketCache = [];
                    throw $e;
                }

                foreach ($paymentMethods as $paymentMethod) {
                    $this->paymentMethodConfigurationService->updateData($paymentMethod);
                }

                $session['postfinancecheckout_payment.possible_payment_methods'] = $paymentMethods;
            }

            static::$possiblePaymentMethodByBasketCache = $paymentMethods;
        }

        return static::$possiblePaymentMethodByBasketCache;
    }

    /**
     * Returns the transaction for the given order.
     *
     * If no transaction exists, a new one is created.
     *
     * @param Order $order
     * @return \PostFinanceCheckout\Sdk\Model\Transaction
     */
    public function getTransactionByOrder(Order $order)
    {
        if (! isset(static::$transactionByOrderCache[$order->getId()]) || static::$transactionByOrderCache[$order->getId()] == null) {
            $orderTransactionMapping = $this->getOrderTransactionMapping($order);
            if ($orderTransactionMapping instanceof OrderTransactionMapping) {
                $this->updateTransaction($order, $orderTransactionMapping->getTransactionId(), $orderTransactionMapping->getSpaceId());
            } else {
                $this->createTransaction($order);
            }
        }
        return static::$transactionByOrderCache[$order->getId()];
    }

    /**
     * Returns the transaction for the given basket.
     *
     * If no transaction exists, a new one is created.
     *
     * @return \PostFinanceCheckout\Sdk\Model\Transaction
     */
    public function getTransactionByBasket()
    {
        if (static::$transactionByBasketCache == null) {
            $orderTransactionMapping = $this->getBasketTransactionMapping();
            if ($orderTransactionMapping instanceof OrderTransactionMapping) {
                $this->updateBasketTransaction($orderTransactionMapping->getTransactionId(), $orderTransactionMapping->getSpaceId());
            } else {
                $this->createBasketTransaction();
            }
        }
        return static::$transactionByBasketCache;
    }

    /**
     * Returns whether the transaction linked to the basket is in pending state.
     *
     * @return boolean
     */
    public function isBasketTransactionPending()
    {
        $orderTransactionMapping = $this->getBasketTransactionMapping();
        if ($orderTransactionMapping instanceof OrderTransactionMapping) {
            $transaction = $this->transactionService->read($orderTransactionMapping->getSpaceId(), $orderTransactionMapping->getTransactionId());
            return $transaction->getState() == TransactionState::PENDING;
        } else {
            return false;
        }
    }

    /**
     * Creates a transaction for the given order.
     *
     * @param Order $order
     * @param boolean $confirm
     * @return \PostFinanceCheckout\Sdk\Model\TransactionCreate
     */
    public function createTransaction(Order $order, $confirm = false)
    {
        $existingTransaction = $this->findExistingTransaction($order->getShop(), $order->getCustomer());
        if ($existingTransaction instanceof \PostFinanceCheckout\Sdk\Model\Transaction) {
            return $this->updateTransaction($order, $existingTransaction->getId(), $existingTransaction->getLinkedSpaceId(), $confirm);
        } else {
            $transaction = new \PostFinanceCheckout\Sdk\Model\TransactionCreate();
            $transaction->setCustomersPresence(\PostFinanceCheckout\Sdk\Model\CustomersPresence::VIRTUAL_PRESENT);
            $this->assembleTransactionData($transaction, $order);
            $this->addDeviceSessionIdentifier($transaction);

            $pluginConfig = $this->configReader->getByPluginName('PostFinanceCheckoutPayment', $order->getShop());
            $spaceId = $pluginConfig['spaceId'];

            $transaction = $this->transactionService->create($spaceId, $transaction);

            $this->updateOrCreateTransactionMapping($transaction, $order);
            static::$transactionByOrderCache[$order->getId()] = $transaction;
            return $transaction;
        }
    }

    /**
     * Creates a transaction for the given basket.
     *
     * @return \PostFinanceCheckout\Sdk\Model\TransactionCreate
     */
    public function createBasketTransaction()
    {
        $existingTransaction = $this->findExistingTransaction($this->container->get('shop'), $this->modelManager->getRepository(Customer::class)
            ->find($this->container->get('session')
            ->get('sUserId')));
        if ($existingTransaction instanceof \PostFinanceCheckout\Sdk\Model\Transaction) {
            return $this->updateBasketTransaction($existingTransaction->getId(), $existingTransaction->getLinkedSpaceId());
        } else {
            $transaction = new \PostFinanceCheckout\Sdk\Model\TransactionCreate();
            $transaction->setCustomersPresence(\PostFinanceCheckout\Sdk\Model\CustomersPresence::VIRTUAL_PRESENT);
            $this->assembleBasketTransactionData($transaction);
            $this->addDeviceSessionIdentifier($transaction);

            $pluginConfig = $this->configReader->getByPluginName('PostFinanceCheckoutPayment', $this->container->get('shop'));
            $spaceId = $pluginConfig['spaceId'];

            $transaction = $this->transactionService->create($spaceId, $transaction);

            $this->updateOrCreateBasketTransactionMapping($transaction);
            static::$transactionByBasketCache = $transaction;
            return $transaction;
        }
    }

    private function findExistingTransaction(Shop $shop, Customer $customer)
    {
        $pluginConfig = $this->configReader->getByPluginName('PostFinanceCheckoutPayment', $shop);
        $spaceId = $pluginConfig['spaceId'];

        $query = new EntityQuery();
        $filter = new EntityQueryFilter();
        $filter->setType(EntityQueryFilterType::_AND);
        $children = [];
        $children[] = $this->createEntityFilter('state', TransactionState::PENDING);
        $children[] = $this->createEntityFilter('customerEmailAddress', $customer->getEmail());
        if ($customer->getAccountMode() == Customer::ACCOUNT_MODE_CUSTOMER) {
            $children[] = $this->createEntityFilter('customerId', $customer->getId());
        }
        $filter->setChildren($children);
        $query->setFilter($filter);
        $query->setOrderBys([
            $this->createEntityOrderBy('createdOn', EntityQueryOrderByType::DESC)
        ]);
        $query->setNumberOfEntities(1);
        $transactions = $this->callApi($this->apiClient, function () use ($spaceId, $query) {
            $this->transactionService->search($spaceId, $query);
        });
        if (is_array($transactions) && ! empty($transactions)) {
            return current($transactions);
        } else {
            return null;
        }
    }

    /**
     * Updates the transaction for the given order.
     *
     * If the transaction is not in pending state, a new one is created.
     *
     * @param Order $order
     * @param int $transactionId
     * @param int $spaceId
     * @param boolean $confirm
     * @return \PostFinanceCheckout\Sdk\Model\AbstractTransactionPending
     */
    public function updateTransaction(Order $order, $transactionId, $spaceId, $confirm = false)
    {
        return $this->callApi($this->apiClient, function () use ($order, $transactionId, $spaceId, $confirm) {
            $transaction = $this->transactionService->read($spaceId, $transactionId);
            if ($transaction->getState() != \PostFinanceCheckout\Sdk\Model\TransactionState::PENDING) {
                $newTransaction = $this->createTransaction($order);
                $this->apiClient->setConnectionTimeout(20);
                return $newTransaction;
            }
            $this->transactionInfoService->updateTransactionInfoByOrder($transaction, $order);

            $pendingTransaction = new \PostFinanceCheckout\Sdk\Model\TransactionPending();
            $pendingTransaction->setId($transaction->getId());
            $pendingTransaction->setVersion($transaction->getVersion());
            $this->assembleTransactionData($pendingTransaction, $order);

            if ($confirm) {
                $this->transactionService->confirm($spaceId, $pendingTransaction);
            } else {
                $this->transactionService->update($spaceId, $pendingTransaction);
            }

            $this->updateOrCreateTransactionMapping($transaction, $order);
            static::$transactionByOrderCache[$order->getId()] = $transaction;
        });
    }

    /**
     * Updates the transaction for the given basket.
     *
     * If the transaction is not in pending state, a new one is created.
     *
     * @param int $transactionId
     * @param int $spaceId
     * @return \PostFinanceCheckout\Sdk\Model\AbstractTransactionPending
     */
    public function updateBasketTransaction($transactionId, $spaceId)
    {
        return $this->callApi($this->apiClient, function () use ($transactionId, $spaceId) {
            $transaction = $this->transactionService->read($spaceId, $transactionId);
            if ($transaction->getState() != TransactionState::PENDING) {
                return $this->createBasketTransaction();
            }

            $pendingTransaction = new \PostFinanceCheckout\Sdk\Model\TransactionPending();
            $pendingTransaction->setId($transaction->getId());
            $pendingTransaction->setVersion($transaction->getVersion());
            $this->assembleBasketTransactionData($pendingTransaction);

            if ($this->isTransactionChanged($spaceId, $transactionId, $pendingTransaction)) {
                $updatedTransaction = $this->transactionService->update($spaceId, $pendingTransaction);
                $this->updateOrCreateBasketTransactionMapping($updatedTransaction);
                $this->updateTransactionHash($spaceId, $transactionId, $pendingTransaction);
                static::$transactionByBasketCache = $updatedTransaction;
            } else {
                static::$transactionByBasketCache = $transaction;
            }
            return $updatedTransaction;
        });
    }

    private function getTransactionHash()
    {
        /* @var \Enlight_Components_Session_Namespace $session */
        $session = $this->container->get('session');
        if (isset($session['postfinancecheckout_payment.basket_transaction_data']) && ! empty($session['postfinancecheckout_payment.basket_transaction_data'])) {
            return $session['postfinancecheckout_payment.basket_transaction_data'];
        } else {
            return null;
        }
    }

    private function updateTransactionHash($spaceId, $transactionId, \PostFinanceCheckout\Sdk\Model\AbstractTransactionPending $transaction)
    {
        /* @var \Enlight_Components_Session_Namespace $session */
        $session = $this->container->get('session');
        $session['postfinancecheckout_payment.basket_transaction_data'] = $this->generateTransactionHash($spaceId, $transactionId, $transaction);
    }

    private function isBasketTransactionChanged()
    {
        $orderTransactionMapping = $this->getBasketTransactionMapping();
        if ($orderTransactionMapping instanceof OrderTransactionMapping) {
            $transaction = new \PostFinanceCheckout\Sdk\Model\TransactionPending();
            $transaction->setId((float) $orderTransactionMapping->getTransactionId());
            $this->assembleBasketTransactionData($transaction);
            return $this->isTransactionChanged($orderTransactionMapping->getSpaceId(), $orderTransactionMapping->getTransactionId(), $transaction);
        } else {
            return true;
        }
    }

    private function isTransactionChanged($spaceId, $transactionId, \PostFinanceCheckout\Sdk\Model\AbstractTransactionPending $transaction)
    {
        $transactionHash = $this->getTransactionHash();
        if ($transactionHash != null) {
            return $this->generateTransactionHash($spaceId, $transactionId, $transaction) != $transactionHash;
        } else {
            return true;
        }
    }

    private function generateTransactionHash($spaceId, $transactionId, \PostFinanceCheckout\Sdk\Model\AbstractTransactionPending $transaction)
    {
        $serializer = new \PostFinanceCheckout\Sdk\ObjectSerializer();
        $sanitized = $serializer->sanitizeForSerialization($transaction);
        unset($sanitized->version);
        return $spaceId . '_' . $transactionId . '_' . \hash("sha256", \json_encode($sanitized));
    }

    private function addDeviceSessionIdentifier(\PostFinanceCheckout\Sdk\Model\AbstractTransactionPending $transaction)
    {
        if ($transaction instanceof \PostFinanceCheckout\Sdk\Model\TransactionCreate) {
            $deviceId = Shopware()->Front()
                ->Request()
                ->getCookie('postfinancecheckout_device_id');
            $transaction->setDeviceSessionIdentifier($deviceId);
        }
    }

    /**
     * Assembles the transaction data for the given order.
     *
     * @param \PostFinanceCheckout\Sdk\Model\AbstractTransactionPending $transaction
     * @param Order $order
     */
    private function assembleTransactionData(\PostFinanceCheckout\Sdk\Model\AbstractTransactionPending $transaction, Order $order)
    {
        if ($order->getNumber() != '0') {
            $transaction->setMerchantReference($order->getNumber());
        }
        $transaction->setCurrency($order->getCurrency());
        $transaction->setBillingAddress($this->getBillingAddress($order->getCustomer()));
        $transaction->setShippingAddress($this->getShippingAddress($order->getCustomer()));
        $transaction->setCustomerEmailAddress($order->getCustomer()
            ->getEmail());
        if ($order->getCustomer()->getAccountMode() == Customer::ACCOUNT_MODE_CUSTOMER) {
            $transaction->setCustomerId($order->getCustomer()
                ->getId());
        }
        $transaction->setLanguage($order->getLanguageSubShop()
            ->getLocale()
            ->getLocale());
        if ($order->getDispatch() instanceof \Shopware\Models\Dispatch\Dispatch) {
            $transaction->setShippingMethod($this->fixLength($order->getDispatch()
                ->getName(), 200));
        }
        
        $pluginConfig = $this->configReader->getByPluginName('PostFinanceCheckoutPayment', $order->getShop());

        if ($transaction instanceof \PostFinanceCheckout\Sdk\Model\TransactionCreate) {
            $transaction->setSpaceViewId($pluginConfig['spaceViewId']);
            $transaction->setAutoConfirmationEnabled(false);
            $transaction->setChargeRetryEnabled(false);
        }

        $transaction->setLineItems($this->lineItem->collectLineItems($order));
        $transaction->setAllowedPaymentMethodConfigurations([]);

        if (! ($transaction instanceof \PostFinanceCheckout\Sdk\Model\TransactionCreate)) {
            $transaction->setSuccessUrl($this->getUrl('PostFinanceCheckoutPaymentTransaction', 'success', null, null, [
                'spaceId' => $pluginConfig['spaceId'],
                'transactionId' => $transaction->getId()
            ]) . '?utm_nooverride=1');
            $transaction->setFailedUrl($this->getUrl('PostFinanceCheckoutPaymentTransaction', 'failure', null, null, [
                'spaceId' => $pluginConfig['spaceId'],
                'transactionId' => $transaction->getId()
            ]) . '?utm_nooverride=1');
        }
    }

    /**
     * Assembles the transaction data for the given basket.
     *
     * @param \PostFinanceCheckout\Sdk\Model\AbstractTransactionPending $transaction
     */
    private function assembleBasketTransactionData(\PostFinanceCheckout\Sdk\Model\AbstractTransactionPending $transaction)
    {
        /* @var Shop $shop */
        $shop = $this->container->get('shop');

        /* @var Customer $customer */
        $customer = $this->modelManager->getRepository(Customer::class)->find($this->container->get('session')
            ->get('sUserId'));
        
        $currency = Shopware()->Shop()->getCurrency()->getCurrency();
        if (empty($currency)) {
            $currency = 'EUR';
        }

        $transaction->setCurrency($currency);
        $transaction->setBillingAddress($this->getBillingAddress($customer));
        $transaction->setShippingAddress($this->getShippingAddress($customer));
        $transaction->setCustomerEmailAddress($customer->getEmail());
        if ($customer->getAccountMode() == Customer::ACCOUNT_MODE_CUSTOMER) {
            $transaction->setCustomerId($customer->getId());
        }
        $transaction->setLanguage($shop->getLocale()
            ->getLocale());
        
        $pluginConfig = $this->configReader->getByPluginName('PostFinanceCheckoutPayment', $shop);

        if ($transaction instanceof \PostFinanceCheckout\Sdk\Model\TransactionCreate) {
            $transaction->setSpaceViewId($pluginConfig['spaceViewId']);
            $transaction->setAutoConfirmationEnabled(false);
            $transaction->setChargeRetryEnabled(false);
        }

        $transaction->setLineItems($this->lineItem->collectBasketLineItems());
        $transaction->setAllowedPaymentMethodConfigurations([]);

        if (! ($transaction instanceof \PostFinanceCheckout\Sdk\Model\TransactionCreate)) {
            $transaction->setSuccessUrl($this->getUrl('PostFinanceCheckoutPaymentTransaction', 'success', null, null, [
                'spaceId' => $pluginConfig['spaceId'],
                'transactionId' => $transaction->getId()
            ]));
            $transaction->setFailedUrl($this->getUrl('PostFinanceCheckoutPaymentTransaction', 'failure', null, null, [
                'spaceId' => $pluginConfig['spaceId'],
                'transactionId' => $transaction->getId()
            ]));
        }
    }

    private function getBillingAddress(Customer $customer)
    {
        $billingAddressId = $this->container->get('session')->offsetGet('checkoutBillingAddressId', null);
        if (empty($billingAddressId)) {
            $billingAddressId = $customer->getDefaultBillingAddress()->getId();
        }
        $billingAddress = $this->modelManager->getRepository(\Shopware\Models\Customer\Address::class)->getOneByUser($billingAddressId, $customer->getId());

        $address = $this->getAddress($billingAddress);
        if ($customer->getBirthday() instanceof \DateTime && $customer->getBirthday() != new \DateTime('0000-00-00')) {
            $address->setDateOfBirth($customer->getBirthday()
                ->format(\DateTime::W3C));
        }
        $address->setEmailAddress($customer->getEmail());
        return $address;
    }

    private function getShippingAddress(Customer $customer)
    {
        $shippingAddressId = $this->container->get('session')->offsetGet('checkoutShippingAddressId', null);
        if (empty($shippingAddressId)) {
            $shippingAddressId = $customer->getDefaultShippingAddress()->getId();
        }
        $shippingAddress = $this->modelManager->getRepository(\Shopware\Models\Customer\Address::class)->getOneByUser($shippingAddressId, $customer->getId());

        $address = $this->getAddress($shippingAddress);
        $address->setEmailAddress($customer->getEmail());
        return $address;
    }

    private function getAddress(\Shopware\Models\Customer\Address $customerAddress)
    {
        $address = new \PostFinanceCheckout\Sdk\Model\AddressCreate();
        $address->setSalutation($this->fixLength($customerAddress->getSalutation(), 20));
        $address->setCity($this->fixLength($customerAddress->getCity(), 100));
        $address->setCountry($customerAddress->getCountry()
            ->getIso());
        $address->setFamilyName($this->fixLength($customerAddress->getLastName(), 100));
        $address->setGivenName($this->fixLength($customerAddress->getFirstName(), 100));
        $address->setOrganizationName($this->fixLength($customerAddress->getCompany(), 100));
        $address->setPhoneNumber($customerAddress->getPhone());
        if ($customerAddress->getState() instanceof \Shopware\Models\Country\State) {
            $address->setPostalState($customerAddress->getState()
                ->getShortCode());
        }
        $address->setPostCode($this->fixLength($customerAddress->getZipCode(), 40));
        $address->setStreet($this->fixLength($customerAddress->getStreet(), 300));
        $address->setSalesTaxNumber($this->fixLength($customerAddress->getVatId(), 100));
        return $address;
    }

    /**
     *
     * @param Order $order
     * @return OrderTransactionMapping
     */
    private function getOrderTransactionMapping(Order $order)
    {
        $filter = [
            'orderId' => $order->getId()
        ];
        if ($order->getTemporaryId() != null) {
            $filter = [
                'temporaryId' => $order->getTemporaryId()
            ];
        }
        return $this->modelManager->getRepository(OrderTransactionMapping::class)->findOneBy($filter);
    }

    /**
     *
     * @return OrderTransactionMapping
     */
    private function getBasketTransactionMapping()
    {
        $filter = [
            'temporaryId' => $this->sessionService->getSessionId()
        ];
        return $this->modelManager->getRepository(OrderTransactionMapping::class)->findOneBy($filter);
    }

    private function updateOrCreateTransactionMapping(\PostFinanceCheckout\Sdk\Model\Transaction $transaction, Order $order)
    {
        if ($this->modelManager->getConnection()->isTransactionActive()) {
            throw new \Exception('This method cannot be called from within a database transaction.');
        }

        $lastException = null;
        for ($i = 0; $i < 5; $i ++) {
            $this->modelManager = $this->getEntityManager();

            $existingMapping = $this->modelManager->getRepository(OrderTransactionMapping::class)->findBy([
                'orderId' => $order->getId(),
                'transactionId' => $transaction->getId(),
                'spaceId' => $transaction->getLinkedSpaceId(),
                'shopId' => $this->container->get('shop')
                    ->getId()
            ]);
            if (! empty($existingMapping)) {
                return;
            }

            $this->modelManager->beginTransaction();
            try {
                if ($order->getTemporaryId() != null) {
                    /* @var OrderTransactionMapping $orderTransactionMapping */
                    $orderTransactionMappings = $this->modelManager->getRepository(OrderTransactionMapping::class)->findBy([
                        'temporaryId' => $order->getTemporaryId()
                    ]);
                    foreach ($orderTransactionMappings as $mapping) {
                        $this->modelManager->remove($mapping);
                    }
                    $this->modelManager->flush();
                }

                /* @var OrderTransactionMapping $orderTransactionMapping */
                $orderTransactionMappings = $this->modelManager->getRepository(OrderTransactionMapping::class)->findBy([
                    'transactionId' => $transaction->getId(),
                    'spaceId' => $transaction->getLinkedSpaceId()
                ]);
                if (count($orderTransactionMappings) > 1) {
                    foreach ($orderTransactionMappings as $mapping) {
                        $this->modelManager->remove($mapping);
                    }
                    $this->modelManager->flush();
                    $orderTransactionMapping = null;
                } else {
                    $orderTransactionMapping = \current($orderTransactionMappings);
                }

                if (! ($orderTransactionMapping instanceof OrderTransactionMapping)) {
                    $orderTransactionMapping = new OrderTransactionMapping();
                    $orderTransactionMapping->setSpaceId($transaction->getLinkedSpaceId());
                    $orderTransactionMapping->setTransactionId($transaction->getId());
                }
                $orderTransactionMapping->setOrder($order);
                $this->modelManager->persist($orderTransactionMapping);
                $this->modelManager->flush($orderTransactionMapping);
                $this->modelManager->commit();
                return;
            } catch (\Exception $e) {
                $pdoException = $this->findCause($e, \PDOException::class);
                if ($pdoException !== null && $pdoException->getCode() == 23000) {
                    // Try again on integrity contraint violation.
                    $lastException = $e;
                } else {
                    $this->modelManager->rollback();
                    throw $e;
                }
            }
        }
        throw $lastException;
    }

    private function updateOrCreateBasketTransactionMapping(\PostFinanceCheckout\Sdk\Model\Transaction $transaction)
    {
        if ($this->modelManager->getConnection()->isTransactionActive()) {
            throw new \Exception('This method cannot be called from within a database transaction.');
        }

        $sessionId = $this->sessionService->getSessionId();

        $lastException = null;
        for ($i = 0; $i < 5; $i ++) {
            $this->modelManager = $this->getEntityManager();

            $existingMapping = $this->modelManager->getRepository(OrderTransactionMapping::class)->findBy([
                'temporaryId' => $sessionId,
                'transactionId' => $transaction->getId(),
                'spaceId' => $transaction->getLinkedSpaceId(),
                'shopId' => $this->container->get('shop')
                    ->getId()
            ]);
            if (! empty($existingMapping)) {
                return;
            }

            $this->modelManager->beginTransaction();
            try {
                /* @var OrderTransactionMapping $orderTransactionMapping */
                $orderTransactionMappings = $this->modelManager->getRepository(OrderTransactionMapping::class)->findBy([
                    'temporaryId' => $sessionId
                ]);
                foreach ($orderTransactionMappings as $mapping) {
                    $this->modelManager->remove($mapping);
                }
                $this->modelManager->flush();

                /* @var OrderTransactionMapping $orderTransactionMapping */
                $orderTransactionMappings = $this->modelManager->getRepository(OrderTransactionMapping::class)->findBy([
                    'transactionId' => $transaction->getId(),
                    'spaceId' => $transaction->getLinkedSpaceId()
                ]);
                if (\count($orderTransactionMappings) > 1) {
                    foreach ($orderTransactionMappings as $mapping) {
                        $this->modelManager->remove($mapping);
                    }
                    $this->modelManager->flush();
                    $orderTransactionMapping = null;
                } else {
                    $orderTransactionMapping = \current($orderTransactionMappings);
                }

                if (! ($orderTransactionMapping instanceof OrderTransactionMapping)) {
                    $orderTransactionMapping = new OrderTransactionMapping();
                    $orderTransactionMapping->setSpaceId($transaction->getLinkedSpaceId());
                    $orderTransactionMapping->setTransactionId($transaction->getId());
                }
                $orderTransactionMapping->setTemporaryId($sessionId);
                $orderTransactionMapping->setShop($this->container->get('shop'));
                $this->modelManager->persist($orderTransactionMapping);
                $this->modelManager->flush($orderTransactionMapping);
                $this->modelManager->commit();
                return;
            } catch (\Exception $e) {
                $pdoException = $this->findCause($e, \PDOException::class);
                if ($pdoException !== null && $pdoException->getCode() == 23000) {
                    // Try again on integrity contraint violation.
                    $lastException = $e;
                } else {
                    $this->modelManager->rollback();
                    throw $e;
                }
            }
        }
        throw $lastException;
    }

    private function getEntityManager()
    {
        if (! $this->modelManager->isOpen()) {
            $this->modelManager = $this->modelManager->create($this->modelManager->getConnection(), $this->modelManager->getConfiguration());
        }
        return $this->modelManager;
    }
}
