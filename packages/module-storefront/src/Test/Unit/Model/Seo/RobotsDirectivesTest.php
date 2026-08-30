<?php
declare(strict_types=1);

namespace MageObsidian\Storefront\Test\Unit\Model\Seo;

use MageObsidian\Storefront\Model\Config\SeoConfig;
use MageObsidian\Storefront\Model\Seo\RobotsDirectives;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Appends the preview directives Google reads to the store's robots value.
 * Returns an empty string whenever nothing should change, so the caller never
 * rewrites a value it did not improve.
 */
class RobotsDirectivesTest extends TestCase
{
    private const array TRIAD = ['max-image-preview:large', 'max-snippet:-1', 'max-video-preview:-1'];

    private SeoConfig&MockObject $config;

    protected function setUp(): void
    {
        $this->config = $this->createMock(SeoConfig::class);
    }

    /**
     * @param array<int, string> $directives
     */
    private function subject(array $directives = self::TRIAD): RobotsDirectives
    {
        $this->config->method('getRobotsDirectives')->willReturn($directives);

        return new RobotsDirectives($this->config);
    }

    public function testAppendsTheTriadToAnIndexableValue(): void
    {
        $this->assertSame(
            'INDEX,FOLLOW,max-image-preview:large,max-snippet:-1,max-video-preview:-1',
            $this->subject()->extend('INDEX,FOLLOW')
        );
    }

    public function testLeavesNoindexPagesAlone(): void
    {
        $this->assertSame('', $this->subject()->extend('NOINDEX,NOFOLLOW'));
        $this->assertSame('', $this->subject()->extend('noindex, follow'));
    }

    public function testKeepsTheOriginalDirectivesAndTheirOrder(): void
    {
        $result = $this->subject()->extend('NOODP, INDEX, FOLLOW');

        $this->assertStringStartsWith('NOODP,INDEX,FOLLOW,', $result);
    }

    public function testDoesNotRepeatADirectiveTheStoreAlreadySet(): void
    {
        $result = $this->subject()->extend('INDEX,FOLLOW,MAX-IMAGE-PREVIEW:LARGE');

        $this->assertSame(1, substr_count(strtolower($result), 'max-image-preview'));
    }

    public function testReturnsEmptyWhenEverythingIsAlreadyThere(): void
    {
        $this->assertSame(
            '',
            $this->subject()->extend('INDEX,FOLLOW,max-image-preview:large,max-snippet:-1,max-video-preview:-1')
        );
    }

    public function testReturnsEmptyWhenTheMerchantClearedTheDirectives(): void
    {
        $this->assertSame('', $this->subject([])->extend('INDEX,FOLLOW'));
    }

    public function testReturnsEmptyWhenThereIsNoRobotsValueToExtend(): void
    {
        $this->assertSame('', $this->subject()->extend('   '));
    }
}
