/**
 * Keeps the price the SDK reads for the product's points in step with the
 * price box, which changes as the shopper picks a configurable product's
 * options. The SDK watches the price element and recounts the points.
 */
define(["jquery"], function ($) {
  "use strict";

  return function (config) {
    var selector = "[data-lion-price-for-product-id='" + config.productId + "']";

    $(".product-info-main [data-role=priceBox][data-product-id='" + config.productId + "']").on(
      "priceUpdated",
      function (event, prices) {
        var price = document.querySelector(selector);

        if (price && prices && prices.finalPrice) {
          price.textContent = Number(prices.finalPrice.amount).toFixed(2);
        }
      }
    );
  };
});
