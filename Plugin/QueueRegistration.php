<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Plugin;

use Loyaltylion\Core\Model\Outbox\Outbox;
use Loyaltylion\Core\Model\Outbox\Queue;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\Data\CustomerInterface;

/**
 * Notes a shopper's own registration, which is what a "create an account" rule
 * rewards. It is declared for the storefront and GraphQL only: a customer an
 * admin, an import or another system creates has not signed up for anything.
 */
class QueueRegistration
{
    public function __construct(private readonly Queue $queue)
    {
    }

    /**
     * Every way of registering goes through this method, createAccount included.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterCreateAccountWithPasswordHash(
        AccountManagementInterface $subject,
        CustomerInterface $customer
    ): CustomerInterface {
        $this->queue->add(Outbox::CUSTOMER_REGISTERED, $customer->getId(), $customer->getWebsiteId());

        return $customer;
    }
}
