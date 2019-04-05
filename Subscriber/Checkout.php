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

namespace PostFinanceCheckoutPayment\Subscriber;

use Enlight\Event\SubscriberInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Plugin\Plugin;
use PostFinanceCheckoutPayment\Components\Transaction as TransactionService;
use PostFinanceCheckoutPayment\Components\Session as SessionService;
use PostFinanceCheckoutPayment\Models\PaymentMethodConfiguration as PaymentMethodConfigurationModel;
use PostFinanceCheckoutPayment\Models\TransactionInfo;
use Shopware\Models\Payment\Payment;

class Checkout implements SubscriberInterface
{

    /**
     *
     * @var ContainerInterface
     */
    private $container;

    /**
     *
     * @var ModelManager
     */
    private $modelManager;

    /**
     *
     * @var TransactionService
     */
    private $transactionService;

    /**
     *
     * @var SessionService
     */
    private $sessionService;

    public static function getSubscribedEvents()
    {
        return [
            'Shopware_Controllers_Frontend_Checkout::preDispatch::after' => 'onPreDispatch',
            'Shopware_Controllers_Frontend_Checkout::confirmAction::after' => 'onConfirmAction',
            'Shopware_Controllers_Frontend_Checkout::getMinimumCharge::after' => 'onGetMinimumCharge'
        ];
    }

    /**
     * Constructor.
     *
     * @param ContainerInterface $container
     * @param ModelManager $modelManager
     * @param TransactionService $transactionService
     * @param SessionService $sessionService
     */
    public function __construct(ContainerInterface $container, ModelManager $modelManager, TransactionService $transactionService, SessionService $sessionService)
    {
        $this->container = $container;
        $this->modelManager = $modelManager;
        $this->transactionService = $transactionService;
        $this->sessionService = $sessionService;
    }
    
    public function onPreDispatch(\Enlight_Hook_HookArgs $args)
    {
        /* @var \Shopware_Controllers_Frontend_Checkout $checkoutController */
        $checkoutController = $args->getSubject();
        
        if ($checkoutController->Request()->getActionName() != 'finish') {
            /* @var \Enlight_Components_Session_Namespace $session */
            $session = $this->container->get('session');
            $session['postfinancecheckout_payment.success'] = false;
        }
    }

    public function onConfirmAction(\Enlight_Hook_HookArgs $args)
    {
        /* @var \Shopware_Controllers_Frontend_Checkout $checkoutController */
        $checkoutController = $args->getSubject();

        $view = $checkoutController->View();
        if (empty($view->sUserLoggedIn)) {
            // When the customer is not logged in, we don't do anything.
            return;
        }

        $paymentData = $checkoutController->getSelectedPayment();
        if ($paymentData != false && isset($paymentData['id'])) {
            /* @var Payment $payment */
            $payment = $this->modelManager->getRepository(Payment::class)->find($paymentData['id']);
            /* @var Plugin $plugin */
            $plugin = $this->modelManager->getRepository(Plugin::class)->findOneBy([
                'name' => $this->container->getParameter('post_finance_checkout_payment.plugin_name')
            ]);
            if ($payment instanceof \Shopware\Models\Payment\Payment && $plugin->getId() == $payment->getPluginId()) {
                $paymentMethodConfiguration = $this->modelManager->getRepository(PaymentMethodConfigurationModel::class)->findOneBy([
                    'paymentId' => $payment->getId()
                ]);
                if ($paymentMethodConfiguration instanceof PaymentMethodConfigurationModel) {
                    $view->addTemplateDir($this->container->getParameter('post_finance_checkout_payment.plugin_dir') . '/Resources/views/');
                    $view->extendsTemplate('frontend/checkout/post_finance_checkout_payment/confirm.tpl');

                    $view->assign('postFinanceCheckoutPaymentJavascriptUrl', $this->transactionService->getJavaScriptUrl());
                    $view->assign('postFinanceCheckoutPaymentPageUrl', $this->transactionService->getPaymentPageUrl());
                    $view->assign('postFinanceCheckoutPaymentConfigurationId', $paymentMethodConfiguration->getConfigurationId());

                    $userFailureMessage = $this->getUserFailureMessage();
                    if (!empty($userFailureMessage)) {
                        $view->assign('postFinanceCheckoutPaymentFailureMessage', $userFailureMessage);
                    }
                }
            }
        }

        $postFinanceCheckoutErrors = $checkoutController->Request()->getParam('postFinanceCheckoutErrors');
        if (!empty($postFinanceCheckoutErrors)) {
            $view->assign('postFinanceCheckoutPaymentFailureMessage', $postFinanceCheckoutErrors);
        }
    }

    private function getUserFailureMessage()
    {
        /* @var \Enlight_Components_Session_Namespace $session */
        $session = $this->container->get('session');
        if (isset($session['postfinancecheckout_payment.failed_transaction']) && !empty($session['postfinancecheckout_payment.failed_transaction'])) {
            /* @var TransactionInfo $transactionInfo */
            $transactionInfo = $this->modelManager
                ->getRepository(TransactionInfo::class)
                ->find($session['postfinancecheckout_payment.failed_transaction']);
            $session['postfinancecheckout_payment.failed_transaction'] = '';

            if ($transactionInfo instanceof TransactionInfo) {
                return $transactionInfo->getUserFailureMessage();
            }
        }
        return null;
    }
    
    public function onGetMinimumCharge(\Enlight_Hook_HookArgs $args)
    {
        /* @var \Enlight_Components_Session_Namespace $session */
        $session = $this->container->get('session');
        if ($session['postfinancecheckout_payment.success'] === true) {
            $args->setReturn(false);
        }
        return $args->getReturn();
    }
}
