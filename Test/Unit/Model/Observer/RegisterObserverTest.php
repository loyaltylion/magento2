<?php

namespace Loyaltylion\Core\Test\Unit\Model\Observer;
use Loyaltylion\Core\Model\Observer\RegisterObserver;

class RegisterObserverTest extends \PHPUnit\Framework\TestCase
{
    protected $observer;

    public function setUp()
    {

        $this->client = $this->getMockBuilder('Loyaltylion\Core\Helper\Client')
            ->disableOriginalConstructor()
            ->getMock();

        $this->config = $this->getMockBuilder('Loyaltylion\Core\Helper\Config')
            ->disableOriginalConstructor()
            ->getMock();

        $this->logger = $this->getMockBuilder('Psr\Log\LoggerInterface')
            ->disableOriginalConstructor()
            ->getMock();

        $this->referrals = $this->getMockBuilder('Loyaltylion\Core\Helper\Referrals')
            ->disableOriginalConstructor()
            ->getMock();


        $this->observerMock = $this->createMock('\Magento\Framework\Event\Observer', [], [], '', false);
    }

    public function testLogsAllErrors()
    {
        $this->config->expects($this->once())
            ->method('isEnabled')
            ->will($this->throwException(new \Exception('Oh no!')));
        $this->logger->expects($this->once())
            ->method('error');

        $this->observer = new RegisterObserver(
            $this->client,
            $this->config,
            $this->logger,
            $this->referrals
        );

        $this->observer->execute($this->observerMock);
    }
}
