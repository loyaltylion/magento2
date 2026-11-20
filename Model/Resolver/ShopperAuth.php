<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Model\Resolver;

use Loyaltylion\Core\Model\Shopper\Signer;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

/**
 * The signed packet for a headless storefront to pass to LoyaltyLion's SDK.
 */
class ShopperAuth implements ResolverInterface
{
    public function __construct(private readonly Signer $signer)
    {
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null)
    {
        $customer = $value['model'] ?? null;
        if ($customer === null) {
            return null;
        }

        $packet = $this->signer->packet(
            (string) $customer->getId(),
            (string) $customer->getEmail(),
            $context->getExtensionAttributes()->getStore()->getWebsiteId()
        );

        return $packet === null ? null : [
            'customer_id' => $packet['customer']['id'],
            'email' => $packet['customer']['email'],
            'date' => $packet['auth']['date'],
            'token' => $packet['auth']['token'],
        ];
    }
}
