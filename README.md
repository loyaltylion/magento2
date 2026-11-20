# LoyaltyLion for Magento 2

Connects a Magento 2 or Adobe Commerce store (self-hosted or on Adobe's cloud) to a LoyaltyLion loyalty program.
Magento 2.4.4 and later, PHP 8.1 and later.

## What the module does

- **Tells LoyaltyLion about orders and customers.** A change to an order or a customer is noted in the
  `loyaltylion_outbox` table in the same save. A cron job each minute reads the order or customer as it is then and
  sends it to LoyaltyLion, and retries until it is accepted. Nothing a shopper does waits on LoyaltyLion.
- **Shows the loyalty program on the storefront.** Every page loads LoyaltyLion's SDK and signs the shopper in to
  it. It works on Luma and on Hyvä. A headless storefront reads the same signed proof from GraphQL
  (`customer { loyaltylion_auth { customer_id email date token } }`).
- **Puts a claimed free product in the cart** at no charge, if the website turns that on.
- **Lets LoyaltyLion create reward codes in the store**, through the "LoyaltyLion" integration it registers
  (System > Integrations).

The module draws no loyalty UI of its own and holds no rules about points or rewards.

## Connecting a website

Each Magento website connects to one LoyaltyLion site. Under Stores > Configuration > LoyaltyLion, at website
scope, set:

| Setting | Where to find it in LoyaltyLion |
| --- | --- |
| Site ID | The number after `/sites/` in the address bar |
| Site token | Settings > General |
| API key | An API key with the `write_orders`, `write_customers` and `read_customers` scopes |
| SDK secret token | Settings > General, "SDK secret token" |

The store's cron must be running (`bin/magento cron:run`), or nothing is sent.

## Looking after it

- `bin/magento loyaltylion:outbox:send` sends what is waiting now and prints what is left:
  `waiting`, `parked` and `oldest_waiting_at`.
- A row is **waiting** until LoyaltyLion accepts it, and is retried after one minute, then two, four, and so on up
  to six hours, for as long as it takes. A row is **held** while the loyalty program has not launched or the API
  key is refused. A row is **parked** when LoyaltyLion refuses that order or customer itself; `last_error` says
  why, and it is sent again if the order or customer changes.
- To point a store at another LoyaltyLion environment:
  `bin/magento config:set loyaltylion/connection/api_host <host>` and `loyaltylion/connection/sdk_host <host>`.

## Working on the module

1. PHP 8.1 or later and Composer; `composer install`
2. Lint: `./vendor/bin/phpcs -ns .`
3. Tests: `./vendor/bin/phpunit Test/`

`view/frontend/templates/sdk.phtml` holds LoyaltyLion's SDK loader snippet. Replace it whole from LoyaltyLion's
developer documentation when it changes there; don't edit it in place.

## Releasing

1. Raise the version in `composer.json`
2. Build a zip with `make zip`
3. Submit it at https://developer.magento.com/extensions/ with the same version
