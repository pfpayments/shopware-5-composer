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

use PostFinanceCheckoutPayment\Components\Controller\Backend;

class Shopware_Controllers_Backend_PostFinanceCheckoutPaymentSynchronize extends Backend
{
    public function synchronizeAction()
    {
        $pluginConfig = $this->get('shopware.plugin.config_reader')->getByPluginName('PostFinanceCheckoutPayment');
        $userId = $pluginConfig['applicationUserId'];
        $applicationKey = $pluginConfig['applicationUserKey'];
        if ($userId && $applicationKey) {
            try {
                $this->get('events')->notify('PostFinanceCheckout_Payment_Config_Synchronize');

                $this->view->assign([
                    'success' => true
                ]);
            } catch (\Exception $e) {
                $this->view->assign([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
        } else {
            $this->view->assign([
                'success' => false,
                'message' => $this->get('snippets')->getNamespace('backend/postfinancecheckout_payment/main')->get('synchronize/message/config_incomplete', 'The configuration is incomplete.')
            ]);
        }
    }
}
