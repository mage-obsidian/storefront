<?php
declare(strict_types=1);

namespace MageObsidian\Customer\Test\Unit\ViewModel;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Helper\Session\CurrentCustomer;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use MageObsidian\Customer\ViewModel\AccountSummary;
use PHPUnit\Framework\TestCase;

/**
 * The rail greets the signed-in customer. What matters here is the monogram
 * fallback chain and that a session without a customer degrades to blanks
 * instead of blowing up the whole account area.
 */
class AccountSummaryTest extends TestCase
{
    protected function setUp(): void
    {
        if (!interface_exists(CustomerInterface::class)) {
            $this->markTestSkipped('Magento framework is not available in this runtime.');
        }
    }

    private function buildViewModel(?CustomerInterface $customer): AccountSummary
    {
        $currentCustomer = $this->createMock(CurrentCustomer::class);
        if ($customer === null) {
            $currentCustomer->method('getCustomer')
                ->willThrowException(new NoSuchEntityException(__('No customer.')));
        } else {
            $currentCustomer->method('getCustomer')->willReturn($customer);
        }

        // A fixed store timezone, so the year assertion does not depend on where
        // the suite runs.
        $timezone = $this->createMock(TimezoneInterface::class);
        $timezone->method('date')->willReturnCallback(
            static fn (string $date): \DateTime => new \DateTime($date, new \DateTimeZone('UTC'))
        );

        return new AccountSummary($currentCustomer, $timezone);
    }

    private function customer(
        string $firstname = 'Jean',
        string $lastname = 'Juarez',
        string $email = 'jean@example.com',
        string $createdAt = '2024-03-01 10:00:00',
        array $addresses = []
    ): CustomerInterface {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getFirstname')->willReturn($firstname);
        $customer->method('getLastname')->willReturn($lastname);
        $customer->method('getEmail')->willReturn($email);
        $customer->method('getCreatedAt')->willReturn($createdAt);
        $customer->method('getAddresses')->willReturn($addresses);

        return $customer;
    }

    public function testReturnsTheFullName(): void
    {
        $this->assertSame('Jean Juarez', $this->buildViewModel($this->customer())->getName());
    }

    public function testBuildsTheMonogramFromBothNames(): void
    {
        $this->assertSame('JJ', $this->buildViewModel($this->customer())->getInitials());
    }

    public function testFallsBackToTheEmailForTheMonogramWhenNoNameIsOnFile(): void
    {
        $summary = $this->buildViewModel($this->customer('', '', 'zoe@example.com'));

        $this->assertSame('Z', $summary->getInitials());
    }

    public function testUsesASingleInitialWhenOnlyOneNameIsOnFile(): void
    {
        $summary = $this->buildViewModel($this->customer('Jean', ''));

        $this->assertSame('J', $summary->getInitials());
    }

    public function testCountsTheAddressesOnFile(): void
    {
        $summary = $this->buildViewModel($this->customer(addresses: ['a', 'b', 'c']));

        $this->assertSame(3, $summary->getAddressCount());
    }

    public function testReturnsTheSignUpYear(): void
    {
        $summary = $this->buildViewModel($this->customer(createdAt: '2024-03-01 10:00:00'));

        $this->assertSame('2024', $summary->getMemberSinceYear());
    }

    public function testDegradesToBlanksWhenThereIsNoCustomer(): void
    {
        $summary = $this->buildViewModel(null);

        $this->assertSame('', $summary->getName());
        $this->assertSame('', $summary->getEmail());
        $this->assertSame('', $summary->getInitials());
        $this->assertSame('', $summary->getMemberSince());
        $this->assertSame('', $summary->getMemberSinceYear());
        $this->assertSame(0, $summary->getAddressCount());
    }
}
