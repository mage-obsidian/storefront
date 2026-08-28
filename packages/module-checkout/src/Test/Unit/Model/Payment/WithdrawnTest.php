<?php
declare(strict_types=1);

namespace MageObsidian\Checkout\Test\Unit\Model\Payment;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Api\Data\PaymentMethodInterface;
use Magento\Payment\Api\PaymentMethodListInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use MageObsidian\Checkout\Model\Payment\NeedsOwnUi;
use MageObsidian\Checkout\Model\Payment\Withdrawn;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class WithdrawnTest extends TestCase
{
    /** @var string[] */
    private array $recorded = [];

    private function withdrawn(bool $enabled, array $declaresOwnUi, array $active): Withdrawn
    {
        $needsOwnUi = $this->createStub(NeedsOwnUi::class);
        $needsOwnUi->method('declares')->willReturnCallback(
            static fn (string $code): bool => in_array($code, $declaresOwnUi, true)
        );

        $methods = [];
        foreach ($active as $code => $title) {
            $method = $this->createStub(PaymentMethodInterface::class);
            $method->method('getCode')->willReturn($code);
            $method->method('getTitle')->willReturn($title);
            $methods[] = $method;
        }
        $list = $this->createStub(PaymentMethodListInterface::class);
        $list->method('getActiveList')->willReturn($methods);

        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        $manager = $this->createStub(StoreManagerInterface::class);
        $manager->method('getStore')->willReturn($store);

        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturn($enabled);

        $logger = $this->createStub(LoggerInterface::class);
        $logger->method('notice')->willReturnCallback(function (string $message): void {
            $this->recorded[] = $message;
        });

        return new Withdrawn($needsOwnUi, $list, $manager, $scopeConfig, $logger);
    }

    public function testNothingIsWithdrawnWhileTheStoreHasNotAskedForIt(): void
    {
        $withdrawn = $this->withdrawn(false, ['acme_card'], ['acme_card' => 'Acme card', 'checkmo' => 'Check']);

        $this->assertSame([], $withdrawn->codes([]));
        $this->assertSame([], $this->recorded);
    }

    public function testAMethodWithItsOwnInterfaceAndNoRendererIsWithdrawn(): void
    {
        $withdrawn = $this->withdrawn(true, ['acme_card'], ['acme_card' => 'Acme card', 'checkmo' => 'Check']);

        $this->assertSame(['acme_card'], $withdrawn->codes([]));
    }

    public function testAMethodThatBringsARendererIsKept(): void
    {
        $withdrawn = $this->withdrawn(true, ['acme_card'], ['acme_card' => 'Acme card']);

        $this->assertSame([], $withdrawn->codes(['acme_card']));
    }

    public function testTheMerchantIsToldWhichMethodWentAndWhy(): void
    {
        $this->recorded = [];
        $this->withdrawn(true, ['acme_card'], ['acme_card' => 'Acme card'])->codes([]);

        $this->assertCount(1, $this->recorded);
        $this->assertStringContainsString(Withdrawn::RECORD, $this->recorded[0]);
        $this->assertStringContainsString('Acme card', $this->recorded[0]);
        $this->assertStringContainsString('acme_card', $this->recorded[0]);
    }
}
