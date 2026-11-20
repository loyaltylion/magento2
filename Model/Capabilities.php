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

    /**
     * @return string[]
     */
    public function all(): array
    {
        return [self::OUTBOX, self::SDK_CART, self::SEAMLESS_FREE_PRODUCT];
    }
}
