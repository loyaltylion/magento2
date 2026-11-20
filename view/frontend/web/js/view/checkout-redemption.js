/**
 * The checkout's element for LoyaltyLion's "redeem points" form. Announces
 * each change to the checkout's totals as `loyaltylion:cart-totals-changed`,
 * so the SDK notices a code the shopper applied or removed through Magento's
 * own discount form.
 */
define(['uiComponent', 'Magento_Checkout/js/model/quote'], function (Component, quote) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Loyaltylion_Core/checkout-redemption'
        },

        initialize: function () {
            var stylesheet = document.createElement('link');

            this._super();

            stylesheet.rel = 'stylesheet';
            stylesheet.href = require.toUrl('Loyaltylion_Core/css/checkout-redemption.css');
            document.head.appendChild(stylesheet);

            quote.totals.subscribe(function () {
                window.dispatchEvent(new CustomEvent('loyaltylion:cart-totals-changed'));
            });

            return this;
        }
    });
});
