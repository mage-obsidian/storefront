<?php
declare(strict_types=1);

namespace MageObsidian\Checkout\Test\Unit\Model\Shell;

use Magento\Framework\App\Request\Http as HttpRequest;
use MageObsidian\Checkout\Model\Shell\Gate;
use MageObsidian\ModernFrontend\Model\Config\ConfigProvider;
use PHPUnit\Framework\TestCase;

/**
 * The single answer to "is this checkout being served as a cacheable shell?".
 * Everything downstream — what the page inlines, whether the layout keeps its
 * cacheable="false" — reads it, so the two kill switches have to meet here.
 */
class GateTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(ConfigProvider::class)) {
            $this->markTestSkipped('MageObsidian ModernFrontend is not available in this runtime.');
        }
    }

    public function testShellIsCacheableWhenTheFlagIsOnAndNoBypassIsAsked(): void
    {
        $this->assertTrue($this->gate(flagOn: true, bypass: null)->isCacheable());
    }

    public function testTheAdminFlagTurnsTheShellOff(): void
    {
        $this->assertFalse($this->gate(flagOn: false, bypass: null)->isCacheable());
    }

    public function testTheQueryBypassTurnsTheShellOffEvenWithTheFlagOn(): void
    {
        $this->assertFalse($this->gate(flagOn: true, bypass: '0')->isCacheable());
    }

    /**
     * Only the explicit off value bypasses. Anything else — a crawler appending
     * junk, a mistyped value — must not silently drop every visitor onto the
     * uncached path.
     */
    public function testAnyOtherBypassValueIsIgnored(): void
    {
        $this->assertTrue($this->gate(flagOn: true, bypass: '1')->isCacheable());
        $this->assertTrue($this->gate(flagOn: true, bypass: 'yes')->isCacheable());
    }

    private function gate(bool $flagOn, ?string $bypass): Gate
    {
        $config = $this->createMock(ConfigProvider::class);
        $config->method('isCheckoutShellCacheable')->willReturn($flagOn);

        $request = $this->createMock(HttpRequest::class);
        $request->method('getParam')->willReturnCallback(
            static fn (string $name) => $name === Gate::BYPASS_PARAM ? $bypass : null
        );

        return new Gate($config, $request);
    }
}
