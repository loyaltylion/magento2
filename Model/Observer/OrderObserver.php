<?php

namespace Loyaltylion\Core\Model\Observer;


use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;


class OrderObserver implements ObserverInterface {
    public function __construct(
        \Loyaltylion\Core\Helper\Client $client,
        \Loyaltylion\Core\Block\Sdk $sdk,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Customer\Model\Session $session,
        \Magento\Framework\App\ObjectManager $objectManager
    ) {
        $this->_client = $client;
        $this->_sdk = $sdk;
        $this->_logger = $logger;
        $this->_session = $session;
        $this->_objectManager = $objectManager;
    }

    public function execute(Observer $observer) {
        if (!$this->_sdk->isEnabled()) return;

        $order = $observer->getEvent()->getOrder();

        # We can't track an order without a merchant_id
        if (!$order || !$order->getId()) return;

        $data = array(
            'merchant_id' => $order->getId(),
            'customer_id' => $order->getCustomerId(),
            'customer_email' => $order->getCustomerEmail(),
            'total' => (string) $order->getBaseGrandTotal(),
            'total_shipping' => (string) $order->getBaseShippingAmount(),
            'number' => (string) $order->getIncrementId(),
            'guest' => (bool) $order->getCustomerIsGuest(),
            'ip_address' => Mage::helper('core/http')->getRemoteAddr(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
            '$magento_payload' => $order->toArray()
        );

        $data['$magento_payload']['order_items'] = $this->getItems($order->getId());
        $data['$magento_payload']['order_comments'] = $this->getComments($order->getId());
        $data['$magento_payload']['addresses'] = $this->getAddresses($order->getId());
        $data = array_merge($data, $this->getVersionInfo());

        if ($order->getBaseTotalDue() == $order->getBaseGrandTotal()) {
            $data['payment_status'] = 'not_paid';
        } else if ($order->getBaseTotalDue() == 0) {
            $data['payment_status'] = 'paid';
        } else {
            $data['payment_status'] = 'partially_paid';
            $total_paid = $order->getBaseTotalPaid();
            $data['total_paid'] = $total_paid === null ? 0 : $total_paid;
        }

        if ($order->getCouponCode()) {
            $data['discount_codes'] = array(
                array(
                    'code' => $order->getCouponCode(),
                    'amount' => abs($order->getDiscountAmount()),
                ),
            );
        }

        if ($this->session->getLoyaltyLionReferralId())
            $data['referral_id'] = $this->session->getLoyaltyLionReferralId();

        $tracking_id = $this->getTrackingIdFromSession();

        if ($tracking_id)
            $data['tracking_id'] = $tracking_id;

        $response = $this->client->orders->create($data);

        if ($response->success) {
            $this->_logger->debug('[LoyaltyLion] Tracked order OK');
        } else {
            $this->_logger->error('[LoyaltyLion] Failed to track order - status: ' . $response->status . ', error: ' . $response->error);
        }
    }

    private function getItems($orderId) {
        $collection = Mage::getResourceModel('sales/order_item_collection');
        $collection->addAttributeToFilter('order_id', array($orderId));
        $items = array();
        foreach ($collection->getItems() as $item) {
            $items[] = $item->toArray();
        }
        return $items;
    }

    private function getAddresses($orderId) {
        $addresses = array();
        $collection = Mage::getResourceModel('sales/order_address_collection');
        $collection->addAttributeToFilter('parent_id', array($orderId));
        foreach ($collection->getItems() as $item) {
            $addresses[] = $item->toArray();
        }
        return $addresses;
    }

    private function getComments($orderId) {
        $comments = array();
        $collection = Mage::getResourceModel('sales/order_status_history_collection');
        $collection->setOrderFilter(array($orderId));
        foreach ($collection->getItems() as $item) {
            $comments[] = $item->toArray();
        }
        return $comments;

    }

    private function getVersionInfo() {
        $version_info = Array();
        $version_info['$magento_version'] = $this->_objectManager->get('Magento\Framework\App\ProductMetadataInterface')->getVersion();
        $version_info['$magento_module_version'] = (string) $this->_objectManager->get('Magento\Framework\Module\ModuleList')->getOne('Loyaltylion_Core')['setup_version'];
        return $version_info;
    }
}