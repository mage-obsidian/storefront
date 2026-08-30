<?php
declare(strict_types=1);

namespace MageObsidian\Storefront\Test\Unit\Model\Seo;

use Magento\Framework\View\Asset\GroupedCollection;
use Magento\Framework\View\Asset\Remote;
use Magento\Framework\View\Page\Config as PageConfig;
use MageObsidian\Storefront\Model\Seo\EmittedCanonical;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Reads the page asset collection, which is where Magento's own canonical lives
 * (`Page\Config::addRemotePageAsset($url, 'canonical', ...)`). Needs Magento
 * View types, so it runs in a Magento root.
 */
class EmittedCanonicalTest extends TestCase
{
    private PageConfig&MockObject $pageConfig;

    protected function setUp(): void
    {
        if (!class_exists(PageConfig::class)) {
            $this->markTestSkipped('Magento View is not available in this runtime.');
        }
        $this->pageConfig = $this->createMock(PageConfig::class);
    }

    /**
     * @param array<int, array{0: string, 1: string}> $assets
     */
    private function withAssets(array $assets): void
    {
        $collection = $this->createMock(GroupedCollection::class);
        $collection->method('getAll')->willReturn(
            array_map(
                function (array $asset): Remote {
                    $remote = $this->createMock(Remote::class);
                    $remote->method('getContentType')->willReturn($asset[0]);
                    $remote->method('getUrl')->willReturn($asset[1]);

                    return $remote;
                },
                $assets
            )
        );
        $this->pageConfig->method('getAssetCollection')->willReturn($collection);
    }

    public function testFindsTheCanonicalMagentoAlreadyAdded(): void
    {
        $this->withAssets([
            ['icon', 'https://shop.test/favicon.ico'],
            ['canonical', 'https://shop.test/joust-duffle-bag.html'],
        ]);

        $this->assertSame(
            'https://shop.test/joust-duffle-bag.html',
            (new EmittedCanonical($this->pageConfig))->find()
        );
    }

    public function testFindsNothingWhenNoCanonicalWasAdded(): void
    {
        $this->withAssets([['icon', 'https://shop.test/favicon.ico']]);

        $this->assertSame('', (new EmittedCanonical($this->pageConfig))->find());
    }

    public function testFindsNothingOnAnEmptyCollection(): void
    {
        $this->withAssets([]);

        $this->assertSame('', (new EmittedCanonical($this->pageConfig))->find());
    }

    public function testDegradesRatherThanBreakingTheHead(): void
    {
        $this->pageConfig->method('getAssetCollection')->willThrowException(new RuntimeException('no layout'));

        $this->assertSame('', (new EmittedCanonical($this->pageConfig))->find());
    }
}
