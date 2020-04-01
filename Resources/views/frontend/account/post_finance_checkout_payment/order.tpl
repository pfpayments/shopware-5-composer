{#
/**
 * PostFinance Checkout Shopware 5
 *
 * This Shopware 5 extension enables to process payments with PostFinance Checkout (https://www.postfinance.ch/checkout/).
 *
 * @package PostFinanceCheckout_Payment
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */
#}

{block name='frontend_account_order_item_repeat_order'}
	{if $offerPosition.postFinanceCheckoutTransaction && ($offerPosition.postFinanceCheckoutTransaction.canDownloadInvoice || $offerPosition.postFinanceCheckoutTransaction.canDownloadPackingSlip)}
		<div class="panel--tr is--odd">
			<div class="panel--td">
				{if $offerPosition.postFinanceCheckoutTransaction.canDownloadInvoice}
					<a href="{url controller='PostFinanceCheckoutPaymentTransaction' action='downloadInvoice' id=$offerPosition.postFinanceCheckoutTransaction.id}" title="{s name="account/button/download_invoice" namespace="frontend/postfinancecheckout_payment/main"}Download Invoice{/s}" class="btn is--small">
						{s name="account/button/download_invoice" namespace="frontend/postfinancecheckout_payment/main"}Download Invoice{/s}
					</a>
				{/if}
				{if $offerPosition.postFinanceCheckoutTransaction.canDownloadPackingSlip}
					<a href="{url controller='PostFinanceCheckoutPaymentTransaction' action='downloadPackingSlip' id=$offerPosition.postFinanceCheckoutTransaction.id}" title="{s name="account/button/download_packing_slip" namespace="frontend/postfinancecheckout_payment/main"}Download Packing Slip{/s}" class="btn is--small">
						{s name="account/button/download_packing_slip" namespace="frontend/postfinancecheckout_payment/main"}Download Packing Slip{/s}
					</a>
				{/if}
			</div>
		</div>
	{/if}
	{if $offerPosition.postFinanceCheckoutTransaction.refunds  && $offerPosition.postFinanceCheckoutTransaction.canDownloadRefunds}
		<div class="panel--tr is--odd">
			<div class="panel--td column--name">
				<p class="is--strong">{s name="account/header/refunds" namespace="frontend/postfinancecheckout_payment/main"}Refunds{/s}</p>
				{foreach $offerPosition.postFinanceCheckoutTransaction.refunds as $refund}
					<p>
                        {$refund.date|date}
					</p>
				{/foreach}
			</div>
			<div class="panel--td column--price">
				<p>&nbsp;</p>
				{foreach $offerPosition.postFinanceCheckoutTransaction.refunds as $refund}
					<p>
						{if $offerPosition.currency_position == "32"}
                            {$offerPosition.currency_html} {$refund.amount}
                        {else}
                            {$refund.amount} {$offerPosition.currency_html}
                        {/if}
					</p>
				{/foreach}
			</div>
			<div class="panel--td column--total">
				<p>&nbsp;</p>
				{foreach $offerPosition.postFinanceCheckoutTransaction.refunds as $refund}
					<p>
						{if $refund.canDownload}
                        	<a href="{url controller='PostFinanceCheckoutPaymentTransaction' action='downloadRefund' id=$offerPosition.postFinanceCheckoutTransaction.id refund=$refund.id}" title="{s name="account/button/download" namespace="frontend/postfinancecheckout_payment/main"}Download{/s}">
								{s name="account/button/download" namespace="frontend/postfinancecheckout_payment/main"}Download{/s}
							</a>
                        {/if}
					</p>
				{/foreach}
			</div>
		</div>
	{/if}
	{$smarty.block.parent}
{/block}