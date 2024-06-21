<?php

namespace Loyaltylion\Core\Test\Unit\Model\Observer;

use Loyaltylion\Core\Model\Observer\RegisterObserver;

class RegisterObserverTest extends \PHPUnit\Framework\TestCase
{
    protected $observer;

    public function setUp(): void
    {
        $this->events = $this->getMockBuilder(
            "Loyaltylion\Core\Helper\Activities"
        )
            ->disableOriginalConstructor()
            ->getMock();

        $this->successResponse = (object) ["success" => true];

        $this->config = $this->getMockBuilder("Loyaltylion\Core\Helper\Config")
            ->setMethods(["getClientForStore"])
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
            "\Magento\Framework\Event\Observer",
            [],
            [],
            "",
            false
        );

        $event = $this->getMockBuilder(\Magento\Framework\Event::class)
            ->setMethods(["getCustomer"])
            ->disableOriginalConstructor()
            ->getMock();

        $customer = $this->getMockBuilder(
            "\Magento\Framework\Model\AbstractModel"
        )
            ->setMethods(["getStoreId", "getId", "getEmail"])
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

        $this->observer = new RegisterObserver(
            $this->config,
            $this->logger,
            $this->tracking
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

    public function testSubmitsSignupWhenEnabled()
    {
        $this->config
            ->expects($this->once())
            ->method("getClientForStore")
            ->willReturn([null, $this->events, null]);

        $this->events
            ->expects($this->once())
            ->method("track")
            ->with('$signup', [
                "customer_id" => 12345,
                "customer_email" => "person@example.com",
                "ip_address" => "127.0.0.1",
                "user_agent" => "Some-Browser",
                "date" => date("c"),
            ])
            ->willReturn($this->successResponse);

        $this->observer->execute($this->observerMock);
    }

    public function testDoesNotSubmitWhenDisabled()
    {
        $this->config
            ->expects($this->once())
            ->method("getClientForStore")
            ->willReturn([null, null, null]);

        $this->events->expects($this->never())->method("track");

        $this->observer->execute($this->observerMock);
    }
}
