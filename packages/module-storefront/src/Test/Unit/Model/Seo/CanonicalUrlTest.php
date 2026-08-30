<?php
declare(strict_types=1);

namespace MageObsidian\Storefront\Test\Unit\Model\Seo;

use MageObsidian\Storefront\Model\Config\SeoConfig;
use MageObsidian\Storefront\Model\Seo\CanonicalUrl;
use MageObsidian\Storefront\Model\Seo\CanonicalUrlBuilder;
use MageObsidian\Storefront\Model\Seo\EmittedCanonical;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Who owns the canonical. `getOwn()` is what this module is allowed to print —
 * empty whenever the core or another extension already put one on the page —
 * while `resolve()` is the URL the page actually canonicalises to, whoever set
 * it, which is what og:url has to agree with.
 */
class CanonicalUrlTest extends TestCase
{
    private EmittedCanonical&MockObject $emitted;
    private CanonicalUrlBuilder&MockObject $builder;
    private SeoConfig&MockObject $config;

    protected function setUp(): void
    {
        $this->emitted = $this->createMock(EmittedCanonical::class);
        $this->builder = $this->createMock(CanonicalUrlBuilder::class);
        $this->config = $this->createMock(SeoConfig::class);
    }

    private function subject(): CanonicalUrl
    {
        return new CanonicalUrl($this->emitted, $this->builder, $this->config);
    }

    public function testEmitsItsOwnCanonicalWhenNobodyElseDid(): void
    {
        $this->config->method('isCanonicalEnabled')->willReturn(true);
        $this->emitted->method('find')->willReturn('');
        $this->builder->method('build')->willReturn('https://shop.test/about-us');

        $this->assertSame('https://shop.test/about-us', $this->subject()->getOwn());
    }

    public function testNeverOverridesACanonicalAlreadyOnThePage(): void
    {
        $this->config->method('isCanonicalEnabled')->willReturn(true);
        $this->emitted->method('find')->willReturn('https://shop.test/joust-duffle-bag.html');
        $this->builder->expects($this->never())->method('build');

        $this->assertSame('', $this->subject()->getOwn());
    }

    public function testEmitsNothingWhenTheMerchantTurnedItOff(): void
    {
        $this->config->method('isCanonicalEnabled')->willReturn(false);
        $this->emitted->method('find')->willReturn('');
        $this->builder->expects($this->never())->method('build');

        $this->assertSame('', $this->subject()->getOwn());
    }

    public function testResolvePrefersTheCanonicalAlreadyOnThePage(): void
    {
        $this->emitted->method('find')->willReturn('https://shop.test/joust-duffle-bag.html');
        $this->builder->method('build')->willReturn('https://shop.test/wrong');

        $this->assertSame('https://shop.test/joust-duffle-bag.html', $this->subject()->resolve());
    }

    public function testResolveFallsBackToTheBuiltUrl(): void
    {
        $this->emitted->method('find')->willReturn('');
        $this->builder->method('build')->willReturn('https://shop.test/about-us');

        $this->assertSame('https://shop.test/about-us', $this->subject()->resolve());
    }

    public function testResolvesTheAssetCollectionAndTheBuilderOnlyOncePerRequest(): void
    {
        $this->config->method('isCanonicalEnabled')->willReturn(true);
        $this->emitted->expects($this->once())->method('find')->willReturn('');
        $this->builder->expects($this->once())->method('build')->willReturn('https://shop.test/about-us');

        $subject = $this->subject();

        $this->assertSame('https://shop.test/about-us', $subject->getOwn());
        $this->assertSame('https://shop.test/about-us', $subject->resolve());
    }

    public function testReusesACanonicalFoundOnThePageWithoutSearchingItTwice(): void
    {
        $this->config->method('isCanonicalEnabled')->willReturn(true);
        $this->emitted->expects($this->once())->method('find')
            ->willReturn('https://shop.test/joust-duffle-bag.html');
        $this->builder->expects($this->never())->method('build');

        $subject = $this->subject();

        $this->assertSame('', $subject->getOwn());
        $this->assertSame('https://shop.test/joust-duffle-bag.html', $subject->resolve());
    }
}
