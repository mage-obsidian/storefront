<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Storefront\Test\Unit\Model\PageBuilder;

use Magento\Framework\App\Config\ScopeConfigInterface;
use MageObsidian\Storefront\Model\PageBuilder\MapProvider;
use PHPUnit\Framework\TestCase;

class MapProviderTest extends TestCase
{
    private const string MAP = '<div data-content-type="map" data-locations="[]" data-element="main"></div>';

    public function testHandsTheConfiguredKeyToTheMapTheAuthorPlaced(): void
    {
        $injected = $this->provider('SECRET')->inject(self::MAP);

        $this->assertStringContainsString('data-map-api-key="SECRET"', $injected);
        $this->assertStringContainsString('data-content-type="map"', $injected);
    }

    public function testLeavesTheContentAloneWhenNoProviderIsConfigured(): void
    {
        $this->assertSame(self::MAP, $this->provider('')->inject(self::MAP));
        $this->assertSame(self::MAP, $this->provider('   ')->inject(self::MAP));
        $this->assertSame(self::MAP, $this->provider(null)->inject(self::MAP));
    }

    public function testLeavesContentWithNoMapUntouchedWithoutReadingTheConfig(): void
    {
        $config = $this->createMock(ScopeConfigInterface::class);
        $config->expects($this->never())->method('getValue');

        $html = '<div data-content-type="row"><p>Nothing to see</p></div>';

        $this->assertSame($html, (new MapProvider($config))->inject($html));
    }

    public function testGivesEveryMapOnThePageTheKey(): void
    {
        $injected = $this->provider('SECRET')->inject(self::MAP . '<p>between</p>' . self::MAP);

        $this->assertSame(2, substr_count($injected, 'data-map-api-key="SECRET"'));
    }

    public function testDoesNotOverwriteAKeyTheMarkupAlreadyCarries(): void
    {
        $html = '<div data-content-type="map" data-map-api-key="OWN"></div>';

        $this->assertSame($html, $this->provider('SECRET')->inject($html));
    }

    public function testEscapesTheKeySoItCannotBreakOutOfTheAttribute(): void
    {
        $injected = $this->provider('a"onload="alert(1)')->inject(self::MAP);

        $this->assertStringContainsString('data-map-api-key="a&quot;onload=&quot;alert(1)"', $injected);
        $this->assertStringNotContainsString('onload="alert', $injected);
    }

    private function provider(?string $key): MapProvider
    {
        $config = $this->createMock(ScopeConfigInterface::class);
        $config->method('getValue')->willReturn($key);

        return new MapProvider($config);
    }
}
