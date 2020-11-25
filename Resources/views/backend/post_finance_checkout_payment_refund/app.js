/**
 * PostFinance Checkout Shopware 5
 *
 * This Shopware 5 extension enables to process payments with PostFinance Checkout (https://www.postfinance.ch/checkout/).
 *
 * @package PostFinanceCheckout_Payment
 * @author wallee AG (http://www.wallee.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */

//{block name="backend/postfinancecheckout_payment_refund/application"}
    //{include file="backend/post_finance_checkout_payment_index/components/CTemplate.js"}
    //{include file="backend/post_finance_checkout_payment_index/components/ComponentColumn.js"}

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