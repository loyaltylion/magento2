<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Observer;

use Loyaltylion\Core\Model\Config;
use Loyaltylion\Core\Model\OldSecret;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Removes module 1.x's site secret once an admin has saved a website's new
 * connection settings in full.
 */
class ForgetOldSecret implements ObserverInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly OldSecret $oldSecret
    ) {
    }

    public function execute(Observer $observer): void
    {
        $websiteId = (int) $observer->getEvent()->getData('website');
        if ($websiteId === 0) {
            return;
        }

        $connection = $this->config->forWebsite($websiteId);
        if ($connection->sendsChanges() && $connection->hasSdkToken) {
            $this->oldSecret->forget($websiteId);
        }
    }
}
