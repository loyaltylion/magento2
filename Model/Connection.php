<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Model;

/**
 * How one Magento website is connected to its LoyaltyLion site.
 */
class Connection
{
    /**
     * @param string|null $signingKey the SDK secret token, or the site secret a store upgraded from
     *                                module 1.x still holds until its SDK secret token is entered
     * @param bool $connectedByOldModule the website holds module 1.x's token and secret
     */
    public function __construct(
        public readonly ?int $siteId,
        public readonly ?string $siteToken,
        public readonly ?string $apiKey,
        public readonly ?string $signingKey,
        public readonly string $apiHost,
        public readonly string $sdkHost,
        public readonly bool $addsFreeProductsToCart = false,
        public readonly bool $connectedByOldModule = false,
        public readonly bool $hasSdkToken = false
    ) {
    }

    public function sendsChanges(): bool
    {
        return $this->siteId !== null && $this->apiKey !== null;
    }

    /**
     * A website module 1.x connected keeps noting its changes after an
     * upgrade, so nothing is lost while its new settings are still to be
     * entered: the rows wait in the outbox.
     */
    public function queuesChanges(): bool
    {
        return $this->sendsChanges() || $this->connectedByOldModule;
    }

    public function needsNewSettings(): bool
    {
        return $this->connectedByOldModule && (!$this->sendsChanges() || !$this->hasSdkToken);
    }

    public function showsSdk(): bool
    {
        return $this->siteToken !== null;
    }

    public function signsShoppers(): bool
    {
        return $this->showsSdk() && $this->signingKey !== null;
    }
}
