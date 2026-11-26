<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Controller\Order;

use Loyaltylion\Core\Model\Outbox\Sender;
use Loyaltylion\Core\Model\Storefront\Placements;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;

/**
 * Sends the order the shopper has just placed to LoyaltyLion at once. The
 * order confirmation page calls it, because the scheduled run can be a minute
 * away and the page says what the order earned. It sends only the order in
 * the shopper's own checkout session, and only while that order is waiting.
 */
class Send implements HttpPostActionInterface
{
    public function __construct(
        private readonly JsonFactory $jsonFactory,
        private readonly CheckoutSession $checkoutSession,
        private readonly Placements $placements,
        private readonly Sender $sender
    ) {
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        $orderId = (int) $this->checkoutSession->getLastOrderId();
        // PHP holds the session for the whole request, and the shopper's other
        // requests wait for it. Nothing below needs it.
        $this->checkoutSession->writeClose();

        if ($orderId === 0 || !$this->placements->isOn(Placements::ORDER_CONFIRMATION)) {
            return $result->setData(['sent' => false]);
        }

        return $result->setData(['sent' => $this->sender->sendOrder($orderId) !== null]);
    }
}
