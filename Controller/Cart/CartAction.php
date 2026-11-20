<?php

declare(strict_types=1);

namespace Loyaltylion\Core\Controller\Cart;

use Loyaltylion\Core\Model\Cart\SdkCart;
use Loyaltylion\Core\Model\Reward\FreeProduct;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;

/**
 * The shopper's cart for LoyaltyLion's SDK, at the three routes the SDK calls
 * on a Shopify store (read, add a line, change quantities), answered in the
 * same shapes.
 */
abstract class CartAction implements CsrfAwareActionInterface
{
    public function __construct(
        protected readonly RequestInterface $request,
        protected readonly JsonFactory $jsonFactory,
        protected readonly CheckoutSession $checkoutSession,
        protected readonly FreeProduct $freeProduct,
        protected readonly SdkCart $sdkCart
    ) {
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * The SDK posts JSON with no form key. These routes can only add a free
     * product the signed-in shopper has claimed, or take a reward line out of
     * their cart, so a forged request gains nothing.
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    protected function cart(): Json
    {
        return $this->json($this->sdkCart->of($this->checkoutSession->getQuote()));
    }

    protected function json(array $body, int $status = 200): Json
    {
        return $this->jsonFactory->create()
            ->setHttpResponseCode($status)
            ->setHeader('Cache-Control', 'no-store', true)
            ->setData($body);
    }

    protected function refuse(int $status, string $description): Json
    {
        return $this->json(['status' => $status, 'message' => 'Cart Error', 'description' => $description], $status);
    }

    protected function body(): array
    {
        $body = json_decode((string) $this->request->getContent(), true);

        return is_array($body) ? $body : [];
    }
}
