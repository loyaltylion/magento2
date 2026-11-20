<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Model\Shopper;

use Loyaltylion\Core\Model\Config;

/**
 * Proves to LoyaltyLion's SDK that the store has signed this customer in, with
 * the packet `loyaltylion.authenticateCustomer` takes.
 */
class Signer
{
    public function __construct(private readonly Config $config)
    {
    }

    /**
     * @param int|string|null $websiteId
     * @return array|null null when the website has nothing to sign with
     */
    public function packet(string $customerId, string $email, $websiteId): ?array
    {
        $connection = $this->config->forWebsite($websiteId);
        if (!$connection->signsShoppers()) {
            return null;
        }

        return self::sign($customerId, $email, date('c'), $connection->signingKey);
    }

    /**
     * The signature covers the id, the date and the email, in that order, as
     * LoyaltyLion's SDK documentation sets out. Signing the email stops a
     * packet being replayed with another customer's address beside it.
     */
    public static function sign(string $customerId, string $email, string $date, string $signingKey): array
    {
        return [
            'customer' => ['id' => $customerId, 'email' => $email],
            'auth' => ['date' => $date, 'token' => sha1($customerId . $date . $email . $signingKey)],
        ];
    }
}
