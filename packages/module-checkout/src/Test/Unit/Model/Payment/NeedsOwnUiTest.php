<?php
declare(strict_types=1);

namespace MageObsidian\Checkout\Test\Unit\Model\Payment;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Design\ThemeInterface;
use Magento\Framework\View\DesignInterface;
use Magento\Framework\View\File;
use Magento\Framework\View\File\CollectorInterface;
use MageObsidian\Checkout\Model\Payment\NeedsOwnUi;
use PHPUnit\Framework\TestCase;

class NeedsOwnUiTest extends TestCase
{
    private const string FIXTURE = __DIR__ . '/../../_files/layout/checkout_index_index.xml';

    /** @var array<string, string> */
    private array $stored = [];

    private function needsOwnUi(array $completableGenerically = [], int $calls = 1): NeedsOwnUi
    {
        $collector = $this->createMock(CollectorInterface::class);
        $collector->expects($this->exactly($calls))
            ->method('getFiles')
            ->willReturn([new File(self::FIXTURE, 'Acme_Gateway')]);

        $theme = $this->createStub(ThemeInterface::class);
        $theme->method('getId')->willReturn(4);
        $design = $this->createStub(DesignInterface::class);
        $design->method('getDesignTheme')->willReturn($theme);

        return new NeedsOwnUi($collector, $design, $this->cache(), new Json(), $completableGenerically);
    }

    private function cache(): CacheInterface
    {
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('load')->willReturnCallback(fn (string $key) => $this->stored[$key] ?? false);
        $cache->method('save')->willReturnCallback(function (string $data, string $key): bool {
            $this->stored[$key] = $data;
            return true;
        });

        return $cache;
    }

    public function testItNamesEveryMethodThePlatformDrawsItself(): void
    {
        $codes = $this->needsOwnUi()->codes();

        sort($codes);
        $this->assertSame(['acme_card', 'acme_wallet', 'checkmo'], $codes);
    }

    public function testAMethodDeclaredCompletableGenericallyIsLeftAlone(): void
    {
        $needsOwnUi = $this->needsOwnUi(['checkmo']);

        $this->assertFalse($needsOwnUi->declares('checkmo'));
        $this->assertTrue($needsOwnUi->declares('acme_card'));
    }

    public function testAMethodNoLayoutMentionsNeedsNothingOfItsOwn(): void
    {
        $this->assertFalse($this->needsOwnUi()->declares('banktransfer'));
    }

    public function testTheLayoutIsReadOnceAndThenServedFromTheCache(): void
    {
        $this->stored = [];
        $first = $this->needsOwnUi([], 1);
        $first->codes();

        $second = $this->needsOwnUi([], 0);

        $this->assertSame($first->codes(), $second->codes());
    }
}
