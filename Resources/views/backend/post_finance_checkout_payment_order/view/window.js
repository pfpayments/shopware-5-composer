/**
 * PostFinance Checkout Shopware 5
 *
 * This Shopware 5 extension enables to process payments with PostFinance Checkout (https://postfinance.ch/en/business/products/e-commerce/postfinance-checkout-all-in-one.html/).
 *
 * @package PostFinanceCheckout_Payment
 * @author wallee AG (http://www.wallee.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */

/**
 * This view extends the order window with a order transactions tab.
 * 
 * @author Simon Schurter
 */

//{block name="backend/order/view/detail/window"}
//{$smarty.block.parent}
//{namespace name=backend/postfinancecheckout_payment/main}
Ext.define('Shopware.apps.Order.PluginPostFinanceCheckoutPayment.view.window.TransactionTab', {

    /**
     * Return order transaction tab.
     *
     * @return Ext.container.Container
     */
    createOrderTransactionTab: function(parent) {
        var me = this,
            storeLoaded = false,
            tabTransactionStore = Ext.create('Shopware.apps.PostFinanceCheckoutPaymentTransaction.store.Transaction');

        me.transactionDetails = Ext.create('Shopware.apps.PostFinanceCheckoutPaymentTransaction.view.transaction.Transaction', {
            region: 'center'
        });

        parent.orderTransactionsTab = Ext.create('Ext.container.Container', {
            title: '{s name="order_view/tab/title"}PostFinance Checkout Payment{/s}',
            disabled: parent.record.get('id') === null,
            layout: 'border',
            items: [
                me.transactionDetails
            ],
            listeners: {
                activate: function() {
                    if (!me.transactionDetails.record && !storeLoaded) {
                        parent.setLoading(true);
                    }
                    parent.fireEvent('orderTransactionsTabActivated', parent);
                }
            }
        });

        tabTransactionStore.load({
            params: {
                orderId: parent.record.get('id')
            },
            callback: function(records, operation, success){
                storeLoaded = true;
                if (this.count() > 0) {
                    me.transactionDetails.setRecord(this.first());
                }
                parent.setLoading(false);
            }
        });
 
        return parent.orderTransactionsTab;
    }

});

Ext.define('Shopware.apps.Order.PostFinanceCheckoutPayment.view.Window', {
    
    override: 'Shopware.apps.Order.view.detail.Window',
    
    alias: 'widget.postfinancecheckout-payment-order-transaction-window',
 
    /**
     * @Override
     * Create the main tab panel which displays the different tabs.
     *
     * @return Ext.tab.Panel
     */
    createTabPanel: function() {
        var me = this, result;
 
        result = me.callParent(arguments);
        
        if (me.record.get('postfinancecheckout_payment') == true) {
            me.transactionTab = Ext.create('Shopware.apps.Order.PluginPostFinanceCheckoutPayment.view.window.TransactionTab');
            result.add(me.transactionTab.createOrderTransactionTab(me));
        }
 
        return result;
    },
    
    updateRecord: function(record) {
        var me = this;
        me.transactionTab.transactionDetails.updateRecord(record);
        
        me.loadOrder(function(order){
            me.down('order-detail-panel').fireEvent('updateForms', order, me);
        });
    },
    
    loadOrder: function(_callback){
        var me = this,
            orderStore = Ext.create('Shopware.apps.Order.store.Order');
        orderStore.load({
            id: me.record.get('id'),
            callback: function(records, operation, success){
                if (success) {
                    _callback(records[0]);
                }
            }
        });
    }
    
});
//{/block}