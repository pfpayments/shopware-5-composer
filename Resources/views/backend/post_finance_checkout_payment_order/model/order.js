/**
 * PostFinance Checkout Shopware
 *
 * This Shopware extension enables to process payments with PostFinance Checkout (https://www.postfinance.ch/).
 *
 * @package PostFinanceCheckout_Payment
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */

//{namespace name=backend/postfinancecheckout_payment/main}
//{block name="backend/order/model/order/fields"}
//{$smarty.block.parent}
	{ name: 'postfinancecheckout_payment', type: 'boolean' },
//{/block}