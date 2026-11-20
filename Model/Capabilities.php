<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Model;

/**
 * What this install of the module can do, by the names LoyaltyLion knows them
 * by. LoyaltyLion offers a feature to a store only when the store reports the
 * capability it depends on, so a name goes here when the code for it ships,
 * and comes out for an install on which that code cannot work.
 */
class Capabilities
{
    /** Adds a claimed free product to the cart at no charge and holds it there */
    public const SEAMLESS_FREE_PRODUCT = 'seamless_free_product';

    /** Lets the SDK read and change the cart through the module's own routes */
    public const SDK_CART = 'sdk_cart';

    /** Sends order and customer changes from the outbox, retrying until taken */
    public const OUTBOX = 'outbox';

    /** Takes a website's connection settings from LoyaltyLion, so nobody pastes them in */
    public const CONNECTION_SETUP = 'connection_setup';

    /** Tells LoyaltyLion when a customer subscribes to the newsletter */
    public const NEWSLETTER_SIGNUP = 'newsletter_signup';

    /** Has the customer attribute LoyaltyLion writes a member's tier to */
    public const CUSTOMER_TIER = 'customer_tier';

    /**
     * @return string[]
     */
    public function all(): array
    {
        return [
            self::CONNECTION_SETUP,
            self::CUSTOMER_TIER,
            self::NEWSLETTER_SIGNUP,
            self::OUTBOX,
            self::SDK_CART,
            self::SEAMLESS_FREE_PRODUCT,
        ];
    }
}
