<?php
declare(strict_types=1);

namespace MageObsidian\Storefront\Test\Unit\Model\Seo;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\View\Page\Config as PageConfig;
use MageObsidian\Storefront\Model\Config\SeoConfig;
use MageObsidian\Storefront\Model\Seo\CurrentEntity;
use MageObsidian\Storefront\Model\Seo\MetaDescription;
use MageObsidian\Storefront\Model\Seo\TextSummarizer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The bug this closes: every page repeated `design/head/default_description`.
 * A product or category with its own meta description wins; one without gets a
 * summary of its own body; a page that already carries something specific is
 * never touched. Needs Magento View/Config types, so it runs in a Magento root.
 */
class MetaDescriptionTest extends TestCase
{
    private const string STORE_DEFAULT = 'MageObsidian — a modern Magento 2 storefront.';

    private PageConfig&MockObject $pageConfig;
    private ScopeConfigInterface&MockObject $scopeConfig;
    private CurrentEntity&MockObject $currentEntity;
    private SeoConfig&MockObject $config;

    protected function setUp(): void
    {
        if (!class_exists(PageConfig::class)) {
            $this->markTestSkipped('Magento View is not available in this runtime.');
        }
        $this->pageConfig = $this->createMock(PageConfig::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->currentEntity = $this->createMock(CurrentEntity::class);
        $this->config = $this->createMock(SeoConfig::class);
        $this->config->method('isMetaDescriptionFallbackEnabled')->willReturn(true);
        $this->scopeConfig->method('getValue')->willReturn(self::STORE_DEFAULT);
    }

    private function subject(): MetaDescription
    {
        return new MetaDescription(
            $this->pageConfig,
            $this->scopeConfig,
            $this->currentEntity,
            new TextSummarizer(),
            $this->config
        );
    }

    /**
     * @param array<string, string> $data
     */
    private function onProductPage(array $data, string $currentDescription = self::STORE_DEFAULT): void
    {
        $this->currentEntity->method('getProduct')->willReturn(new DataObject($data));
        $this->pageConfig->method('getDescription')->willReturn($currentDescription);
    }

    public function testUsesTheEntitysOwnMetaDescription(): void
    {
        $this->onProductPage(['meta_description' => 'A duffle bag for the gym.']);

        $this->assertSame('A duffle bag for the gym.', $this->subject()->derive());
    }

    public function testSummarisesTheShortDescriptionWhenTheEntityHasNoMetaDescription(): void
    {
        $this->onProductPage([
            'meta_description' => '',
            'short_description' => '<p>The <b>Joust Duffle Bag</b> carries a full day of gear.</p>',
            'description' => 'Long copy nobody should see here.',
        ]);

        $this->assertSame('The Joust Duffle Bag carries a full day of gear.', $this->subject()->derive());
    }

    public function testFallsBackToTheLongDescriptionWhenThereIsNoShortOne(): void
    {
        $this->onProductPage(['short_description' => '', 'description' => 'Roomy, rugged, cheap.']);

        $this->assertSame('Roomy, rugged, cheap.', $this->subject()->derive());
    }

    public function testLeavesAPageThatAlreadyHasItsOwnDescriptionAlone(): void
    {
        $this->onProductPage(
            ['short_description' => 'Would be derived.'],
            'A description another extension already set.'
        );

        $this->assertSame('', $this->subject()->derive());
    }

    public function testDerivesWhenThePageHasNoDescriptionAtAll(): void
    {
        $this->onProductPage(['short_description' => 'Roomy, rugged, cheap.'], '');

        $this->assertSame('Roomy, rugged, cheap.', $this->subject()->derive());
    }

    public function testFallsBackToTheCategoryWhenThereIsNoProduct(): void
    {
        $this->currentEntity->method('getProduct')->willReturn(null);
        $this->currentEntity->method('getCategory')->willReturn(
            new DataObject(['description' => '<p>Bags for every trip.</p>'])
        );
        $this->pageConfig->method('getDescription')->willReturn(self::STORE_DEFAULT);

        $this->assertSame('Bags for every trip.', $this->subject()->derive());
    }

    public function testDoesNothingOffAnEntityPage(): void
    {
        $this->currentEntity->method('getProduct')->willReturn(null);
        $this->currentEntity->method('getCategory')->willReturn(null);

        $this->assertSame('', $this->subject()->derive());
    }

    public function testDoesNothingWhenTheMerchantTurnedItOff(): void
    {
        $config = $this->createMock(SeoConfig::class);
        $config->method('isMetaDescriptionFallbackEnabled')->willReturn(false);
        $this->currentEntity->expects($this->never())->method('getProduct');

        $subject = new MetaDescription(
            $this->pageConfig,
            $this->scopeConfig,
            $this->currentEntity,
            new TextSummarizer(),
            $config
        );

        $this->assertSame('', $subject->derive());
    }

    public function testMatchesTheStoreDefaultThroughHtmlEscaping(): void
    {
        $this->currentEntity->method('getProduct')->willReturn(
            new DataObject(['short_description' => 'Roomy, rugged, cheap.'])
        );
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->scopeConfig->method('getValue')->willReturn('Bags & packs');
        $this->pageConfig->method('getDescription')->willReturn('Bags &amp; packs');

        $subject = new MetaDescription(
            $this->pageConfig,
            $this->scopeConfig,
            $this->currentEntity,
            new TextSummarizer(),
            $this->config
        );

        $this->assertSame('Roomy, rugged, cheap.', $subject->derive());
    }
}
