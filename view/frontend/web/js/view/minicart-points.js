/**
 * The points for the cart in the mini cart, from its subtotal. The SDK reads
 * the amount only when it renders, and the mini cart can render after it, so
 * it is asked to refresh whenever this renders or the amount changes.
 */
define(["ko", "uiComponent", "Magento_Customer/js/customer-data"], function (
  ko,
  Component,
  customerData
) {
  "use strict";

  function refreshSdk() {
    if (window.loyaltylion && window.loyaltylion.ui) {
      window.loyaltylion.ui.refresh({ debounce: true });
    }
  }

  return Component.extend({
    defaults: {
      template: "Loyaltylion_Core/minicart-points",
    },
    initialize: function () {
      var cart = customerData.get("cart");

      this._super();
      this.amount = ko.pureComputed(function () {
        var subtotal = Number(cart().subtotalAmount);

        return subtotal > 0 ? String(Math.round(subtotal * 100)) : "";
      });
      this.amount.subscribe(refreshSdk);
      this.refreshSdk = refreshSdk;

      return this;
    },
  });
});
