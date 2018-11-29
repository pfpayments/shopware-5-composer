/**
 * PostFinance Checkout Shopware
 *
 * This Shopware extension enables to process payments with PostFinance Checkout (https://www.postfinance.ch/).
 *
 * @package PostFinanceCheckout_Payment
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */

//{block name="backend/postfinancecheckout_payment_refund/application"}
    //{include file="backend/postfinancecheckout_payment_index/components/CTemplate.js"}
    //{include file="backend/postfinancecheckout_payment_index/components/ComponentColumn.js"}

    Ext.define('Shopware.apps.PostFinanceCheckoutPaymentRefund', {
        
        extend: 'Enlight.app.SubApplication',
        
        name: 'Shopware.apps.PostFinanceCheckoutPaymentRefund',
        
        loadPath: '{url controller="PostFinanceCheckoutPaymentRefund" action=load}',
        
        controllers: [
            'Main'
        ],
        
        launch: function() {
            var me = this,
                mainController = me.getController('Main');
            return mainController.mainWindow;
        }
        
    });
//{/block}