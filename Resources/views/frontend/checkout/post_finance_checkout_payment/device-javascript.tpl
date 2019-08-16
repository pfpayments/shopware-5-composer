{#
/**
 * PostFinance Checkout Shopware
 *
 * This Shopware extension enables to process payments with PostFinance Checkout (https://www.postfinance.ch/checkout/).
 *
 * @package PostFinanceCheckout_Payment
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */
#}

{block name="frontend_index_header_javascript_jquery"}
	{$smarty.block.parent}
	<script type="text/javascript" src="{$postFinanceCheckoutPaymentDeviceJavascriptUrl}"></script>
{/block}