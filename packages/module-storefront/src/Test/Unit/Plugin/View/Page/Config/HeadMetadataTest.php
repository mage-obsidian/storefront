<?php
declare(strict_types=1);

namespace MageObsidian\Storefront\Test\Unit\Plugin\View\Page\Config;

use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Framework\View\Page\Config\Renderer;
use MageObsidian\Storefront\Model\Config\SeoConfig;
use MageObsidian\Storefront\Model\Seo\MetaDescription;
use MageObsidian\Storefront\Model\Seo\RobotsDirectives;
use MageObsidian\Storefront\Model\Seo\SocialMetaBuilder;
use MageObsidian\Storefront\Plugin\View\Page\Config\HeadMetadata;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Everything lands on Page\Config just before the head renders, so Magento does
 * the escaping and the rendering: `Renderer::getMetadataTemplate()` already
 * writes any `og:` key as <meta property> and everything else as <meta name>.
 * Needs Magento View types, so it runs in a Magento root.
 */
class HeadMetadataTest extends TestCase
{
    private PageConfig&MockObject $pageConfig;
    private MetaDescription&MockObject $metaDescription;
    private RobotsDirectives&MockObject $robotsDirectives;
    private SocialMetaBuilder&MockObject $socialMetaBuilder;
    private SeoConfig&MockObject $config;
    private Renderer&MockObject $renderer;

    protected function setUp(): void
    {
        if (!class_exists(PageConfig::class)) {
            $this->markTestSkipped('Magento View is not available in this runtime.');
        }
        $this->pageConfig = $this->createMock(PageConfig::class);
        $this->metaDescription = $this->createMock(MetaDescription::class);
        $this->robotsDirectives = $this->createMock(RobotsDirectives::class);
        $this->socialMetaBuilder = $this->createMock(SocialMetaBuilder::class);
        $this->config = $this->createMock(SeoConfig::class);
        $this->renderer = $this->createMock(Renderer::class);

        $this->pageConfig->method('getRobots')->willReturn('INDEX,FOLLOW');
        $this->pageConfig->method('getMetadata')->willReturn([]);
        $this->metaDescription->method('derive')->willReturn('');
        $this->robotsDirectives->method('extend')->willReturn('');
        $this->socialMetaBuilder->method('build')->willReturn([]);
        $this->config->method('isSocialMetaEnabled')->willReturn(true);
    }

    private function subject(): HeadMetadata
    {
        return new HeadMetadata(
            $this->pageConfig,
            $this->metaDescription,
            $this->robotsDirectives,
            $this->socialMetaBuilder,
            $this->config
        );
    }

    public function testSetsADerivedDescription(): void
    {
        $metaDescription = $this->createMock(MetaDescription::class);
        $metaDescription->method('derive')->willReturn('Roomy, rugged, cheap.');
        $this->pageConfig->expects($this->once())->method('setDescription')->with('Roomy, rugged, cheap.');

        (new HeadMetadata(
            $this->pageConfig,
            $metaDescription,
            $this->robotsDirectives,
            $this->socialMetaBuilder,
            $this->config
        ))->beforeRenderMetadata($this->renderer);
    }

    public function testLeavesTheDescriptionAloneWhenNothingWasDerived(): void
    {
        $this->pageConfig->expects($this->never())->method('setDescription');

        $this->subject()->beforeRenderMetadata($this->renderer);
    }

    public function testWritesTheExtendedRobotsValue(): void
    {
        $robots = $this->createMock(RobotsDirectives::class);
        $robots->method('extend')->with('INDEX,FOLLOW')->willReturn('INDEX,FOLLOW,max-image-preview:large');
        $this->pageConfig->expects($this->once())
            ->method('setRobots')
            ->with('INDEX,FOLLOW,max-image-preview:large');

        (new HeadMetadata(
            $this->pageConfig,
            $this->metaDescription,
            $robots,
            $this->socialMetaBuilder,
            $this->config
        ))->beforeRenderMetadata($this->renderer);
    }

    public function testLeavesRobotsAloneWhenThereIsNothingToAdd(): void
    {
        $this->pageConfig->expects($this->never())->method('setRobots');

        $this->subject()->beforeRenderMetadata($this->renderer);
    }

    public function testWritesEverySocialTagAsPageMetadata(): void
    {
        $builder = $this->createMock(SocialMetaBuilder::class);
        $builder->method('build')->willReturn([
            'og:title' => 'Joust Duffle Bag',
            'twitter:card' => 'summary_large_image',
        ]);

        $written = [];
        $this->pageConfig->method('setMetadata')->willReturnCallback(
            static function (string $name, string $content) use (&$written): void {
                $written[$name] = $content;
            }
        );

        (new HeadMetadata(
            $this->pageConfig,
            $this->metaDescription,
            $this->robotsDirectives,
            $builder,
            $this->config
        ))->beforeRenderMetadata($this->renderer);

        $this->assertSame(
            ['og:title' => 'Joust Duffle Bag', 'twitter:card' => 'summary_large_image'],
            $written
        );
    }

    public function testNeverOverwritesMetadataAlreadyOnThePage(): void
    {
        $pageConfig = $this->createMock(PageConfig::class);
        $pageConfig->method('getRobots')->willReturn('INDEX,FOLLOW');
        $pageConfig->method('getMetadata')->willReturn(['og:title' => 'Set by another extension']);
        $pageConfig->expects($this->never())->method('setMetadata');

        $builder = $this->createMock(SocialMetaBuilder::class);
        $builder->method('build')->willReturn(['og:title' => 'Joust Duffle Bag']);

        (new HeadMetadata(
            $pageConfig,
            $this->metaDescription,
            $this->robotsDirectives,
            $builder,
            $this->config
        ))->beforeRenderMetadata($this->renderer);
    }

    public function testSkipsTheSocialTagsWhenTheMerchantTurnedThemOff(): void
    {
        $config = $this->createMock(SeoConfig::class);
        $config->method('isSocialMetaEnabled')->willReturn(false);
        $builder = $this->createMock(SocialMetaBuilder::class);
        $builder->expects($this->never())->method('build');

        (new HeadMetadata(
            $this->pageConfig,
            $this->metaDescription,
            $this->robotsDirectives,
            $builder,
            $config
        ))->beforeRenderMetadata($this->renderer);
    }
}
