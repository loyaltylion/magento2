/**
 * Created by patrick on 24/03/2017.
 */
define(["uiComponent", "Magento_Customer/js/customer-data"], function(
  Component,
  customerData
) {
  "use strict";
  return Component.extend({
    handleCustomer: function(lion_customer) {
      if (lion_customer.logged_in) {
        window.lion.authenticateCustomer({
          customer: lion_customer.customer,
          auth: {
            date: lion_customer.date,
            token: lion_customer.auth_token
          }
        });
      }
    },
    initialize: function() {
      this._super();
      this.lion_customer = customerData.get("lion-customer");
      this.lion_customer.subscribe(this.handleCustomer);
      this.handleCustomer(this.lion_customer());
    }
  });
});
