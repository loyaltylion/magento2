<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Controller\Reward;

use Loyaltylion\Core\Model\Reward\ClaimedFreeProducts;
use Loyaltylion\Core\Model\Reward\FreeProduct;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;

/**
 * Adds a claimed free product to the signed-in shopper's cart at no charge.
 * The LoyaltyLion SDK's hooks post the claim here: a seamless free product's
 * cart session, or a free product code reward's code.
 */
class Add implements HttpPostActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly CustomerSession $customerSession,
        private readonly CheckoutSession $checkoutSession,
        private readonly FreeProduct $freeProduct,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        if (!$this->freeProduct->isOn()) {
            return $result->setHttpResponseCode(404)->setData(['added' => false, 'reason' => 'off']);
        }

        if (!$this->customerSession->isLoggedIn()) {
            return $result->setData(['added' => false, 'reason' => 'signed_out']);
        }

        $session = (string) $this->request->getParam('session');

        try {
            return $result->setData(
                $this->freeProduct->addToCart(
                    $this->checkoutSession->getQuote(),
                    $this->customerSession->getCustomerId(),
                    $session !== '' ? ClaimedFreeProducts::SESSION : ClaimedFreeProducts::CODE,
                    $session !== '' ? $session : (string) $this->request->getParam('code')
                )
            );
        } catch (\Exception $e) {
            $this->logger->error('[LoyaltyLion] Could not add a free product to the cart: ' . $e->getMessage());

            return $result->setData(['added' => false, 'reason' => 'error']);
        }
    }
}
