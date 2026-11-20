/**
 * Puts a claimed free product in the cart through the LoyaltyLion SDK's
 * workflow hooks: after a claim, and from "Apply" on a reward claimed earlier.
 * When the store can't add it the SDK shows the reward's code as usual, and
 * the code still works as a coupon.
 */
define(["Magento_Customer/js/customer-data"], function (customerData) {
  "use strict";

  return function (config) {
    var registered = false;

    // Read on every use: the SDK swaps the snippet's placeholder for itself
    // once it has loaded.
    function sdk() {
      return window.loyaltylion;
    }

    function isFreeProduct(claimedReward) {
      var reward = claimedReward.reward || {};
      return (
        Boolean(claimedReward.redeemable && claimedReward.redeemable.code) &&
        reward.kind === "product_discount_voucher" &&
        (reward.discountType || reward.discount_type) === "percentage" &&
        Number(reward.discountAmount || reward.discount_amount) === 100
      );
    }

    function showInCart() {
      customerData.set("messages", {
        messages: [{ type: "success", text: config.message }],
      });
      // The cart and checkout pages are drawn by the server, so only a new
      // page shows the line; every other page has just the mini cart.
      if (/\/checkout(\/|$)/.test(window.location.pathname)) {
        window.location.reload();
        return;
      }
      customerData.invalidate(["cart"]);
      customerData.reload(["cart"], true);
    }

    function add(claimedReward) {
      return fetch(config.url, {
        method: "POST",
        credentials: "same-origin",
        headers: { "X-Requested-With": "XMLHttpRequest" },
        body: new URLSearchParams({ code: claimedReward.redeemable.code }),
      })
        .then(function (response) {
          return response.json();
        })
        .then(function (result) {
          if (!result.added) {
            return false;
          }
          claimedReward.setApplied(true);
          showInCart();
          return true;
        })
        .catch(function () {
          return false;
        });
    }

    function hook(added, notAdded) {
      return function (payload) {
        if (!isFreeProduct(payload.claimedReward)) {
          return Promise.resolve({ action: notAdded });
        }
        return add(payload.claimedReward).then(function (wasAdded) {
          return { action: wasAdded ? added : notAdded };
        });
      };
    }

    function offerApplyOnEarlierClaims() {
      var resources = sdk().resources;
      var customer = resources && resources.customer;
      if (!customer || typeof customer.getClaimedRewards !== "function") {
        return;
      }
      customer
        .getClaimedRewards({ hideUsed: true, hideVoid: true })
        .then(function (claimedRewards) {
          claimedRewards.forEach(function (claimedReward) {
            if (isFreeProduct(claimedReward)) {
              claimedReward.setCanBeApplied(true);
            }
          });
        })
        .catch(function () {});
    }

    function register() {
      if (registered) {
        return;
      }
      try {
        sdk().registerHook(
          "customers.rewards.claimed",
          hook("hide_claim_modal", "show_claim_modal")
        );
        sdk().registerHook(
          "customers.rewards.apply_claimed",
          hook("hide_claimed_reward_modal", "show_claimed_reward_modal")
        );
        registered = true;
        offerApplyOnEarlierClaims();
      } catch (e) {
        // The SDK isn't ready yet: its "ready" event registers the hooks.
      }
    }

    if (!sdk()) {
      return;
    }
    sdk().on("ready", register);
    if (typeof sdk().registerHook === "function") {
      register();
    }
  };
});
