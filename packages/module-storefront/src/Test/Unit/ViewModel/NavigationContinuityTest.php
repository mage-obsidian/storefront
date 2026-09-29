<?php
declare(strict_types=1);

namespace MageObsidian\Storefront\Test\Unit\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use MageObsidian\Storefront\ViewModel\NavigationContinuity;
use PHPUnit\Framework\TestCase;

class NavigationContinuityTest extends TestCase
{
    private function viewModel(bool $retain): NavigationContinuity
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects(self::once())
            ->method('isSetFlag')
            ->with(NavigationContinuity::CONFIG_RETAIN, ScopeInterface::SCOPE_STORE)
            ->willReturn($retain);

        return new NavigationContinuity($scopeConfig);
    }

    public function testRetentionFollowsTheStoreFlag(): void
    {
        self::assertTrue($this->viewModel(true)->isEnabled());
        self::assertFalse($this->viewModel(false)->isEnabled());
    }

    public function testRetentionReadsItsOwnPath(): void
    {
        self::assertSame('mage_obsidian/navigation/retain', NavigationContinuity::CONFIG_RETAIN);
    }

    public function testRetentionShipsEnabled(): void
    {
        $config = simplexml_load_file(__DIR__ . '/../../../etc/config.xml');

        self::assertSame('1', (string)$config->default->mage_obsidian->navigation->retain);
    }

    public function testMarkerIdIsAValidFragmentTarget(): void
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);

        self::assertMatchesRegularExpression(
            '/^[a-z][a-z0-9-]*$/',
            (new NavigationContinuity($scopeConfig))->getMarkerId()
        );
    }
}
