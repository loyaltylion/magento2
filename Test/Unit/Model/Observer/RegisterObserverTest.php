<?php

namespace Loyaltylion\Core\Test\Unit\Model\Observer;

use Loyaltylion\Core\Model\Observer\RegisterObserver;

class RegisterObserverTest extends \PHPUnit\Framework\TestCase
{
    protected $observer;
    protected $events;
    protected $successResponse;
    protected $config;
    protected $logger;
    protected $tracking;
    protected $queue;
    protected $observerMock;

    public function setUp(): void
    {
        $this->events = $this->getMockBuilder(
            "Loyaltylion\Core\Helper\Activities"
        )
            ->disableOriginalConstructor()
            ->getMock();

        $this->successResponse = (object) ["success" => true];

        $this->config = $this->getMockBuilder("Loyaltylion\Core\Helper\Config")
            ->onlyMethods(["getClientForStore"])
            ->disableOriginalConstructor()
            ->getMock();

        $this->logger = $this->getMockBuilder("Psr\Log\LoggerInterface")
            ->disableOriginalConstructor()
            ->getMock();

        $this->tracking = $this->getMockBuilder(
            "Loyaltylion\Core\Helper\Tracking"
        )
            ->disableOriginalConstructor()
            ->getMock();
        $this->tracking->method("getTrackingData")->willReturn([
            "ip_address" => "127.0.0.1",
            "user_agent" => "Some-Browser",
        ]);

        $this->observerMock = $this->createMock(
            "\Magento\Framework\Event\Observer"
        );

        $event = $this->getMockBuilder(\Magento\Framework\Event::class)
            ->addMethods(["getCustomer"])
            ->disableOriginalConstructor()
            ->getMock();

        $customer = $this->getMockBuilder(
            "\Magento\Framework\Model\AbstractModel"
        )
            ->onlyMethods(["getId"])
            ->addMethods(["getStoreId", "getEmail"])
            ->disableOriginalConstructor()
            ->getMock();
        $customer->method("getStoreId")->willReturn(1);
        $customer->method("getId")->willReturn(12345);
        $customer->method("getEmail")->willReturn("person@example.com");

        $this->observerMock
            ->expects($this->any())
            ->method("getEvent")
            ->willReturn($event);
        $event->method("getCustomer")->willReturn($customer);

        $this->queue = $this->getMockBuilder(
            "Loyaltylion\Core\Model\EventQueue"
        )
            ->disableOriginalConstructor()
            ->getMock();

        $this->observer = new RegisterObserver(
            $this->config,
            $this->logger,
            $this->tracking,
            $this->queue
        );
    }

    public function testLogsAllErrors()
    {
        $this->config
            ->expects($this->once())
            ->method("getClientForStore")
            ->will($this->throwException(new \Exception("Oh no!")));
        $this->logger->expects($this->once())->method("error");

        $this->observer->execute($this->observerMock);
    }

    public function testQueuesSignupWhenEnabled()
    {
        $this->config
            ->expects($this->once())
            ->method("getClientForStore")
            ->willReturn([null, $this->events, null]);

        $this->queue
            ->expects($this->once())
            ->method("add")
            ->with(1, "signup", "customer:12345", [
                "name" => '$signup',
                "customer_id" => 12345,
                "customer_email" => "person@example.com",
                "ip_address" => "127.0.0.1",
                "user_agent" => "Some-Browser",
                "date" => date("c"),
            ]);

        $this->observer->execute($this->observerMock);
    }

    public function testDoesNotSubmitWhenDisabled()
    {
        $this->config
            ->expects($this->once())
            ->method("getClientForStore")
            ->willReturn([null, null, null]);

        $this->queue->expects($this->never())->method("add");

        $this->observer->execute($this->observerMock);
    }
}
