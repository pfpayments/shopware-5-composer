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

use PostFinanceCheckoutPayment\Components\Webhook\Request as WebhookRequest;
use Shopware\Components\CSRFWhitelistAware;
use PostFinanceCheckoutPayment\Components\Controller\Frontend;

class Shopware_Controllers_Frontend_PostFinanceCheckoutPaymentWebhook extends Frontend implements CSRFWhitelistAware
{
    
    public function getWhitelistedCSRFActions()
    {
        return [
            'handle'
        ];
    }

    public function preDispatch()
    {
        parent::preDispatch();

        $this->Front()->Plugins()->ViewRenderer()->setNoRender();
    }

    public function handleAction()
    {
        $this->Response()->setHttpResponseCode(500);
        try {
            $request = $this->getWebhookRequest();
            $this->get('events')->notify('PostFinanceCheckout_Payment_Webhook_' . $request->getListenerEntityTechnicalName(), [
                'request' => $request
            ]);
            if (! $this->Response()->isException()) {
                $this->Response()->setHttpResponseCode(200);
            }
        } catch (\PostFinanceCheckoutPayment\Components\Webhook\Exception $e) {
            $this->get('corelogger')->critical($e);
            echo $e->getMessage();
        } catch (\Exception $e) {
            $this->get('corelogger')->critical($e);
            echo $e->getMessage();
        }
    }
    
    private function getWebhookRequest() {
        $data = $this->Request()->getRawBody();
        if (empty($data)) {
            throw new \Exception('Empty request data.');
        }
        $decodedData = json_decode($data);
        if (json_last_error() != JSON_ERROR_NONE) {
            throw new \Exception('Invalid request data.');
        }
        return new WebhookRequest($decodedData);
    }
}
