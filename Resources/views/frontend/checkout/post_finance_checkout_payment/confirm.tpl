{#
/**
 * PostFinance Checkout Shopware 5
 *
 * This Shopware 5 extension enables to process payments with PostFinance Checkout (https://postfinance.ch/en/business/products/e-commerce/postfinance-checkout-all-in-one.html/).
 *
 * @package PostFinanceCheckout_Payment
 * @author wallee AG (http://www.wallee.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */
#}

{block name="frontend_index_header_javascript_jquery"}
	{$smarty.block.parent}
	<script type="text/javascript" src="{$postFinanceCheckoutPaymentJavascriptUrl}"></script>
	<script type="text/javascript">
	var ShopwarePostFinanceCheckoutCheckoutInit = function(){
		ShopwarePostFinanceCheckout.Checkout.init('postfinancecheckout_payment_method_form', '{$postFinanceCheckoutPaymentConfigurationId}', '{url controller='PostFinanceCheckoutPaymentCheckout' action='saveOrder'}', '{$postFinanceCheckoutPaymentPageUrl}');
	};
	{if $theme.asyncJavascriptLoading}
		if (typeof document.asyncReady == 'function') {
			document.asyncReady(function(){
				$(document).ready(ShopwarePostFinanceCheckoutCheckoutInit);
			});
		} else {
			$(document).ready(ShopwarePostFinanceCheckoutCheckoutInit);
		}
	{/if}
	</script>
{/block}

{block name="frontend_index_javascript_async_ready"}
	{$smarty.block.parent}
	{if !$theme.asyncJavascriptLoading}
		<script type="text/javascript">
			$(document).ready(ShopwarePostFinanceCheckoutCheckoutInit);
		</script>
	{/if}
{/block}

{block name='frontend_checkout_confirm_premiums'}
	<div class="panel has--border" id="postfinancecheckout_payment_method_form_container" style="position: absolute; left: -10000px;">
		<div class="panel--title is--underline">
			{s name="checkout/payment_information namespace=frontend/postfinancecheckout_payment/main"}Payment Information{/s}
		</div>
		<div class="panel--body is--wide">
			<div id="postfinancecheckout_payment_method_form"></div>
		</div>
	</div>
	{$smarty.block.parent}
{/block}

{block name="frontend_checkout_confirm_error_messages"}
	{$smarty.block.parent}
	{if $postFinanceCheckoutPaymentFailureMessage}
		{include file="frontend/_includes/messages.tpl" type="error" content=$postFinanceCheckoutPaymentFailureMessage}
	{/if}
	<div class="postfinancecheckout-payment-validation-failure-message" style="display: none;">
		{include file="frontend/_includes/messages.tpl" type="error" content=""}
	</div>
{/block}
