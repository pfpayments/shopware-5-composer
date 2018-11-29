/**
 * PostFinance Checkout Shopware
 *
 * This Shopware extension enables to process payments with PostFinance Checkout (https://www.postfinance.ch/).
 *
 * @package PostFinanceCheckout_Payment
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */

//{block name="backend/postfinancecheckout_payment_synchronize/application"}
Ext.define('Shopware.apps.PostFinanceCheckoutPaymentSynchronize', {
    
    extend: 'Enlight.app.SubApplication',
    
    name: 'Shopware.apps.PostFinanceCheckoutPaymentSynchronize',
    
    loadPath: '{url action=load}',
    
    controllers: [
        'Synchronize'
    ],
    
    launch: function() {
        var me = this;
        me.getController('Synchronize').directSynchronize();
    }
    
});
//{/block}