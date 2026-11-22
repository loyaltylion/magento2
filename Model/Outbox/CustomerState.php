<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Model\Outbox;

use Magento\Customer\Api\Data\CustomerInterface;

/**
 * A customer as the store holds them now, in Magento's own field names.
 */
class CustomerState
{
    public function of(CustomerInterface $customer): array
    {
        $state = [
            'id' => (int) $customer->getId(),
            'email' => (string) $customer->getEmail(),
            'firstname' => $customer->getFirstname() ?: null,
            'lastname' => $customer->getLastname() ?: null,
            'created_at' => $customer->getCreatedAt() ? Timestamp::utc($customer->getCreatedAt()) : null,
            'dob' => $customer->getDob() ?: null,
            'website_id' => $customer->getWebsiteId() !== null ? (int) $customer->getWebsiteId() : null,
            'group_id' => $customer->getGroupId() !== null ? (int) $customer->getGroupId() : null,
        ];

        return array_filter($state, static fn ($value) => $value !== null);
    }
}
