/**
 * Created by patrick on 24/03/2017.
 */
define(['uiComponent', 'Magento_Customer/js/customer-data'], function(
  Component,
  customerData
) {
  'use strict'
  return Component.extend({
    handleCustomer: function(lion_customer) {
      if (lion_customer.logged_in) {
        lion.identify_customer(lion_customer.customer)
        lion.auth_customer({
          date: lion_customer.date,
          auth_token: lion_customer.auth_token,
        })
      }
    },
    initialize: function() {
      this._super()
      this.lion_customer = customerData.get('lion-customer')
      this.lion_customer.subscribe(this.handleCustomer)
      this.handleCustomer(this.lion_customer())
    },
  })
})
