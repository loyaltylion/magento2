/**
 * Luma's side of putting a claimed free product in the cart: the message and
 * the cart refresh.
 */
define([
  "Magento_Customer/js/customer-data",
  "Loyaltylion_Core/js/free-product-hooks",
], function (customerData, registerHooks) {
  "use strict";

  return function (config) {
    registerHooks(config, function (message) {
      customerData.set("messages", {
        messages: [{ type: "success", text: message }],
      });
      // The cart and checkout pages are drawn by the server, so only a new
      // page shows the line; every other page has just the mini cart.
      if (/\/checkout(\/|$)/.test(window.location.pathname)) {
        window.location.reload();
        return;
      }
      customerData.invalidate(["cart"]);
      customerData.reload(["cart"], true);
    });
  };
});
