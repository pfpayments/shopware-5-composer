/**
 * PostFinance Checkout Shopware 5
 *
 * This Shopware 5 extension enables to process payments with PostFinance Checkout (https://www.postfinance.ch/checkout/).
 *
 * @package PostFinanceCheckout_Payment
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */

//{block name="backend/postfinancecheckout_payment_transaction/model/refund"}
Ext.define('Shopware.apps.PostFinanceCheckoutPaymentTransaction.model.Refund', {
    
    extend: 'Ext.data.Model',
 
    fields: [
        //{block name="backend/postfinancecheckout_payment_transaction/model/refund/fields"}{/block}
        { name: 'id', type: 'int' },
        { name: 'state', type: 'string' },
        { name: 'createdOn', type: 'date' },
        { name: 'amount', type: 'float' },
        { name: 'externalId', type: 'string' },
        { name: 'failureReason', type: 'string' },
        { name: 'labels', type: 'object' }
    ],
    
    associations:[
        {
            type: 'hasMany',
            model: 'Shopware.apps.PostFinanceCheckoutPaymentTransaction.model.LineItem',
            name: 'getLineItems',
            associationKey: 'lineItems'
        }
    ]

});
//{/block}