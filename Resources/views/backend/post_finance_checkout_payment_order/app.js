/**
 * PostFinance Checkout Shopware
 *
 * This Shopware extension enables to process payments with PostFinance Checkout (https://www.postfinance.ch/).
 *
 * @package PostFinanceCheckout_Payment
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */

//{block name="backend/order/application"}
	//{$smarty.block.parent}
    //{include file="backend/post_finance_checkout_payment_index/components/CTemplate.js"}
    //{include file="backend/post_finance_checkout_payment_index/components/ComponentColumn.js"}
	//{include file="backend/post_finance_checkout_payment_transaction/controller/refund.js"}
    //{include file="backend/post_finance_checkout_payment_transaction/controller/transaction.js"}
	//{include file="backend/post_finance_checkout_payment_transaction/view/transaction/transaction.js"}
	//{include file="backend/post_finance_checkout_payment_transaction/view/transaction/details.js"}
    //{include file="backend/post_finance_checkout_payment_transaction/view/transaction/line_items.js"}
    //{include file="backend/post_finance_checkout_payment_transaction/view/transaction/line_items/grid.js"}
    //{include file="backend/post_finance_checkout_payment_transaction/view/transaction/refunds.js"}
    //{include file="backend/post_finance_checkout_payment_transaction/view/transaction/refunds/grid.js"}
    //{include file="backend/post_finance_checkout_payment_transaction/view/transaction/refunds/details.js"}
    //{include file="backend/post_finance_checkout_payment_transaction/model/transaction.js"}
    //{include file="backend/post_finance_checkout_payment_transaction/model/line_item.js"}
    //{include file="backend/post_finance_checkout_payment_transaction/model/refund_line_item.js"}
    //{include file="backend/post_finance_checkout_payment_transaction/model/refund.js"}
	//{include file="backend/post_finance_checkout_payment_transaction/store/transaction.js"}

	Ext.define('Shopware.apps.Order.PostFinanceCheckoutPaymentTransaction', {
		
	    override: 'Shopware.apps.Order',
		
		launch: function() {
			var me = this;
			me.getController('Shopware.apps.PostFinanceCheckoutPaymentTransaction.controller.Transaction');
			me.getController('Shopware.apps.PostFinanceCheckoutPaymentTransaction.controller.Refund');
			return me.callParent(arguments);
		}
	
	});
//{/block}