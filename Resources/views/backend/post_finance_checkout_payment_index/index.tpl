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

{extends file='parent:backend/index/parent.tpl'}
 
{block name="backend/base/header/css"}
	{$smarty.block.parent}
   <link rel="stylesheet" type="text/css" href="{link file="backend/_resources/styles/postfinancecheckout_payment.css"}" />
{/block}

{block name="backend/base/header/javascript"}
	{$smarty.block.parent}
	<script type="text/javascript">
		window.PostFinanceCheckoutActive = true;
	</script>
{/block}