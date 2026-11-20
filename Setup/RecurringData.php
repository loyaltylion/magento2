<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Setup;

use Loyaltylion\Core\Model\Heartbeat;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

/**
 * Reports in to LoyaltyLion at the end of every setup:upgrade, so a store that
 * has just installed or updated the module is offered what the new version can
 * do straight away rather than after the next hourly report.
 */
class RecurringData implements InstallDataInterface
{
    public function __construct(private readonly Heartbeat $heartbeat)
    {
    }

    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context): void
    {
        try {
            $this->heartbeat->send();
        } catch (\Throwable $e) {
            // The hourly report follows, and an upgrade must not fail because LoyaltyLion could not be reached.
        }
    }
}
