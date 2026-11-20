<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Api;

/**
 * Lets LoyaltyLion set a website's connection settings itself when the store's
 * admin connects it, so nobody copies them over by hand.
 */
interface ConnectionManagementInterface
{
    /**
     * Save the settings that connect a website to a LoyaltyLion site.
     *
     * @param int $websiteId
     * @param int $siteId
     * @param string $siteToken
     * @param string $apiKey
     * @param string $sdkToken
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function save(int $websiteId, int $siteId, string $siteToken, string $apiKey, string $sdkToken): bool;
}
