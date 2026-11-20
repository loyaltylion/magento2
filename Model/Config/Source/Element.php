<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * The SDK elements a merchant can place with the LoyaltyLion widget, from the
 * SDK's documented components: https://developers.loyaltylion.com/sdk
 */
class Element implements OptionSourceInterface
{
    /** Each element's tag, attribute and attribute value, by the widget's value */
    public const ELEMENTS = [
        'integrated-page' => ['div', 'data-lion-integrated-page', ''],
        'rules-list' => ['div', 'data-lion-rules-list', ''],
        'rewards-list' => ['div', 'data-lion-rewards-list', ''],
        'claimed-rewards-list' => ['div', 'data-lion-claimed-rewards-list', ''],
        'history-table' => ['div', 'data-lion-history-table', ''],
        'join-program' => ['div', 'data-lion-join-program', ''],
        'challenges' => ['div', 'data-lion-challenges', ''],
        'tier-overview' => ['div', 'data-lion-tier-overview', ''],
        'tier-progress' => ['div', 'data-lion-tier-progress', 'single'],
        'tier-benefits-compare' => ['div', 'data-lion-tier-benefits-compare', ''],
        'points' => ['span', 'data-lion-points', 'approved'],
        'referral-url' => ['span', 'data-lion-referral-url', ''],
    ];

    public function toOptionArray(): array
    {
        $labels = [
            'integrated-page' => __('Loyalty page'),
            'rules-list' => __('Ways to earn'),
            'rewards-list' => __('Rewards'),
            'claimed-rewards-list' => __('Claimed rewards'),
            'history-table' => __('Points history'),
            'join-program' => __('Join the program'),
            'challenges' => __('Challenges'),
            'tier-overview' => __('Tier overview'),
            'tier-progress' => __('Tier progress'),
            'tier-benefits-compare' => __('Tier benefits'),
            'points' => __('Points balance'),
            'referral-url' => __('Referral link'),
        ];

        return array_map(
            static fn (string $value): array => ['value' => $value, 'label' => $labels[$value]],
            array_keys(self::ELEMENTS)
        );
    }
}
