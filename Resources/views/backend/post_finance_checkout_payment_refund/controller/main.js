/**
 * PostFinance Checkout Shopware 5
 *
 * This Shopware 5 extension enables to process payments with PostFinance Checkout (https://www.postfinance.ch/checkout/).
 *
 * @package PostFinanceCheckout_Payment
 * @author wallee AG (http://www.wallee.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */

//{block name="backend/postfinancecheckout_payment_refund/controller/main"}
//{namespace name=backend/postfinancecheckout_payment/main}
Ext.define('Shopware.apps.PostFinanceCheckoutPaymentRefund.controller.Main', {

    extend: 'Ext.app.Controller',

    views: [ 'main.Window', 'main.Form' ],

    refs: [
        { ref: 'refundForm', selector: 'postfinancecheckout-payment-refund-main-form' },
        { ref: 'transactionView', selector: 'postfinancecheckout-payment-transaction-transaction' },
        { ref: 'refundGrid', selector: 'postfinancecheckout-payment-transaction-refunds-grid' }
    ],

    snippets: {
        createRefund: {
            successMessage: '{s name="refund/message/create_success"}The refund has been successfully created.{/s}',
            failureMessage: '{s name="refund/message/create_failure"}An error has occurred while creating the refund.{/s}'
        },
        growlTitle: '{s name="growl_title"}PostFinance Checkout Payment{/s}'
    },

    mainWindow: null,

    init: function() {
        var me = this;

        me.control({
            'postfinancecheckout-payment-refund-main-window button[action=postfinancecheckout-payment-refund-main-window-cancel]': {
                click: me.onCloseWindow
            },
            'postfinancecheckout-payment-refund-main-window button[action=postfinancecheckout-payment-refund-main-window-create]': {
                click: me.onCreateRefund
            },
        });
        
        me.mainWindow = me.getView('main.Window').create({
            record: me.subApplication.record
        });
        
        me.callParent(arguments);
    },
    
    onCloseWindow: function() {
        var me = this;
        me.mainWindow.destroy();
    },
    
    onCreateRefund: function() {
        var me = this;
        this.createRefund();
    },
    
    createRefund: function(){
        var me = this,
            record = me.subApplication.record,
            store = record.getRefundBaseLineItems();
        
        var params = {
            spaceId: record.get('spaceId'),
            transactionId: record.get('transactionId')
        };
        store.each(function(lineItem){
            params['reductions[' + lineItem.get('uniqueId') + '][quantity]'] = lineItem.quantityReduction;
            params['reductions[' + lineItem.get('uniqueId') + '][unitPrice]'] = lineItem.unitPriceReduction;
        });

        me.mainWindow.setLoading(true);
        Ext.Ajax.request({
            url: '{url controller="PostFinanceCheckoutPaymentRefund" action="createRefund"}',
            params: params,
            success: function(response) {
                me.mainWindow.setLoading(false);
                var data = Ext.decode(response.responseText);
                if (data.success) {
                    Shopware.Notification.createGrowlMessage(me.snippets.growlTitle, data.message || me.snippets.createRefund.successMessage);
                    me.onCloseWindow();
                    me.getTransactionView().setLoading(true);
                    me.subApplication.getStore('Shopware.apps.PostFinanceCheckoutPaymentTransaction.store.Transaction').load({
                        params: {
                            transactionId: record.get('id')
                        },
                        callback:function (records) {
                            me.getTransactionView().updateRecord(records[0]);
                            me.getTransactionView().setLoading(false);
                            if (data.refundId && records[0].getRefunds().getById(data.refundId)) {
                                me.getRefundGrid().getSelectionModel().select(records[0].getRefunds().getById(data.refundId));
                            }
                        }
                    });
                } else {
                    Shopware.Notification.createGrowlMessage(me.snippets.growlTitle, data.message || me.snippets.createRefund.failureMessage);
                }
            }
        });
    }

});
//{/block}