<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Observer;

use Loyaltylion\Core\Model\Reward\FreeProduct;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Takes the "Reward" label off a line an admin's edited order or reorder
 * copies from a free reward line. Magento copies the label with the line, but
 * not the claim, so the new line is an ordinary one the customer pays for.
 */
class UnlabelReorderedReward implements ObserverInterface
{
    public function execute(Observer $observer): void
    {
        $orderItem = $observer->getEvent()->getData('order_item');
        $quoteItem = $observer->getEvent()->getData('quote_item');

        if ($orderItem && $quoteItem && $orderItem->getProductOptionByCode(FreeProduct::OPTION_CODE)) {
            $quoteItem->removeOption('additional_options');
        }
    }
}
