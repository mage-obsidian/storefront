<?php
declare(strict_types=1);

namespace MageObsidian\Storefront\Test\Unit\Model\Seo;

use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\LayoutInterface;
use MageObsidian\Storefront\Model\Seo\ClaimedSocialProperties;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Asks the blocks that already write og properties which ones they write, so
 * the list never has to be maintained by hand. Needs Magento View types, so it
 * runs in a Magento root.
 */
class ClaimedSocialPropertiesTest extends TestCase
{
    private const array CLAIMANTS = ['opengraph.general' => 'open_graph'];

    private LayoutInterface&MockObject $layout;

    protected function setUp(): void
    {
        if (!interface_exists(LayoutInterface::class)) {
            $this->markTestSkipped('Magento View is not available in this runtime.');
        }
        $this->layout = $this->createMock(LayoutInterface::class);
    }

    /**
     * @param array<string, string> $properties
     */
    private function withBlockEmitting(array $properties): void
    {
        $source = new class ($properties) {
            /**
             * @param array<string, string> $properties
             */
            public function __construct(private readonly array $properties)
            {
            }

            /**
             * @return array<string, string>
             */
            public function getProperties(): array
            {
                return $this->properties;
            }
        };

        $block = $this->createMock(AbstractBlock::class);
        $block->method('getData')->with('open_graph')->willReturn($source);
        $this->layout->method('getBlock')->with('opengraph.general')->willReturn($block);
    }

    public function testReportsThePropertiesTheBlockActuallyEmits(): void
    {
        $this->withBlockEmitting([
            'og:type' => 'product',
            'og:title' => 'Joust Duffle Bag',
            'og:url' => 'https://shop.test/joust-duffle-bag.html',
            'og:image' => 'https://shop.test/media/catalog/product/j/o/joust.jpg',
        ]);

        $this->assertSame(
            ['og:type', 'og:title', 'og:url', 'og:image'],
            (new ClaimedSocialProperties($this->layout, self::CLAIMANTS))->get()
        );
    }

    public function testClaimsNothingWhenTheBlockIsNotOnThePage(): void
    {
        $this->layout->method('getBlock')->willReturn(false);

        $this->assertSame([], (new ClaimedSocialProperties($this->layout, self::CLAIMANTS))->get());
    }

    public function testClaimsNothingWhenTheArgumentIsNotAPropertySource(): void
    {
        $block = $this->createMock(AbstractBlock::class);
        $block->method('getData')->willReturn('not an object');
        $this->layout->method('getBlock')->willReturn($block);

        $this->assertSame([], (new ClaimedSocialProperties($this->layout, self::CLAIMANTS))->get());
    }

    public function testClaimsNothingWhenTheSourceReturnsSomethingOtherThanAnArray(): void
    {
        $source = new class {
            public function getProperties(): ?array
            {
                return null;
            }
        };
        $block = $this->createMock(AbstractBlock::class);
        $block->method('getData')->willReturn($source);
        $this->layout->method('getBlock')->willReturn($block);

        $this->assertSame([], (new ClaimedSocialProperties($this->layout, self::CLAIMANTS))->get());
    }

    public function testClaimsNothingWhenTheSourceThrows(): void
    {
        $source = new class {
            /**
             * @return array<string, string>
             */
            public function getProperties(): array
            {
                throw new RuntimeException('no product in the registry');
            }
        };
        $block = $this->createMock(AbstractBlock::class);
        $block->method('getData')->willReturn($source);
        $this->layout->method('getBlock')->willReturn($block);

        $this->assertSame([], (new ClaimedSocialProperties($this->layout, self::CLAIMANTS))->get());
    }

    public function testClaimsNothingWithoutClaimants(): void
    {
        $this->layout->expects($this->never())->method('getBlock');

        $this->assertSame([], (new ClaimedSocialProperties($this->layout))->get());
    }
}
