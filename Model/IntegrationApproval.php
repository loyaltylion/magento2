<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Model;

use Magento\Integration\Api\IntegrationServiceInterface;
use Magento\Integration\Model\Integration;

/**
 * Whether the store's admin has approved the LoyaltyLion integration with the
 * permissions this version of the module asks for. Magento withdraws approval
 * when an upgrade changes those permissions, and LoyaltyLion's calls to the
 * store fail until someone approves again.
 */
class IntegrationApproval
{
    /** The name in etc/integration.xml */
    private const NAME = 'LoyaltyLion';

    public function __construct(private readonly IntegrationServiceInterface $integrations)
    {
    }

    public function state(): string
    {
        $integration = $this->integrations->findByName(self::NAME);
        if (!$integration->getId()) {
            return 'missing';
        }

        return match ((int) $integration->getStatus()) {
            Integration::STATUS_ACTIVE => 'approved',
            Integration::STATUS_RECREATED => 'needs_reapproval',
            default => 'not_approved',
        };
    }
}
