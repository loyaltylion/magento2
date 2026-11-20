<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Model;

/**
 * How one Magento website is connected to its LoyaltyLion site.
 */
class Connection
{
    public function __construct(
        public readonly ?int $siteId,
        public readonly ?string $siteToken,
        public readonly ?string $apiKey,
        public readonly ?string $sdkToken,
        public readonly string $apiHost,
        public readonly string $sdkHost
    ) {
    }

    public function sendsChanges(): bool
    {
        return $this->siteId !== null && $this->apiKey !== null;
    }

    public function showsSdk(): bool
    {
        return $this->siteToken !== null;
    }

    public function signsShoppers(): bool
    {
        return $this->showsSdk() && $this->sdkToken !== null;
    }
}
