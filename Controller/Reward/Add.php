<?php

namespace Loyaltylion\Core\Controller\Reward;

use Loyaltylion\Core\Model\Reward\FreeProduct;

/**
 * Adds a claimed free product to the signed-in shopper's cart at no charge.
 * The LoyaltyLion SDK posts the claim's code here after the shopper claims.
 */
class Add implements \Magento\Framework\App\Action\HttpPostActionInterface
{
    private $_request;
    private $_jsonFactory;
    private $_customerSession;
    private $_checkoutSession;
    private $_freeProduct;
    private $_logger;

    public function __construct(
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\Controller\Result\JsonFactory $jsonFactory,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        FreeProduct $freeProduct,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->_request = $request;
        $this->_jsonFactory = $jsonFactory;
        $this->_customerSession = $customerSession;
        $this->_checkoutSession = $checkoutSession;
        $this->_freeProduct = $freeProduct;
        $this->_logger = $logger;
    }

    public function execute()
    {
        $result = $this->_jsonFactory->create();

        if (!$this->_freeProduct->isOn()) {
            return $result->setHttpResponseCode(404)->setData(
                ["added" => false, "reason" => "off"]
            );
        }

        if (!$this->_customerSession->isLoggedIn()) {
            return $result->setData(
                ["added" => false, "reason" => "signed_out"]
            );
        }

        try {
            return $result->setData(
                $this->_freeProduct->addToCart(
                    $this->_checkoutSession->getQuote(),
                    $this->_customerSession->getCustomerId(),
                    $this->_request->getParam("code")
                )
            );
        } catch (\Exception $e) {
            $this->_logger->error(
                "[LoyaltyLion] Could not add a free product to the cart: " .
                    $e->getMessage()
            );
            return $result->setData(["added" => false, "reason" => "error"]);
        }
    }
}
