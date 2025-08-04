<?php
declare(strict_types=1);

namespace MageObsidian\Storefront\Test\Unit\Model\Template\Extension;

use Magento\Framework\Pricing\PriceCurrencyInterface;
use MageObsidian\Storefront\Model\Template\Extension\PriceExtension;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * The commerce Twig extension, registered on the engine via the storefront's
 * di.xml (dogfooding the new extensibility). PriceCurrency::format() returns
 * markup (<span class="price">…</span>) when the container is included, so the
 * filter MUST be flagged safe — the mirror of render_vue's non-escaping test.
 * Needs Twig + Magento Pricing types → runs in a Magento root (see phpunit.ci.xml).
 */
class PriceExtensionTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Environment::class)) {
            $this->markTestSkipped('Twig is not installed in this runtime.');
        }
    }

    private function render(string $template, PriceCurrencyInterface $priceCurrency): string
    {
        $environment = new Environment(new ArrayLoader(['t' => $template]), ['cache' => false, 'autoescape' => 'html']);
        $environment->addExtension(new PriceExtension($priceCurrency));

        return $environment->render('t');
    }

    public function testPriceFilterOutputIsNotEscaped(): void
    {
        $priceCurrency = $this->createMock(PriceCurrencyInterface::class);
        $priceCurrency->method('format')->willReturn('<span class="price">$1,234.00</span>');

        $output = $this->render('{{ 1234|price }}', $priceCurrency);

        $this->assertStringContainsString('<span class="price">$1,234.00</span>', $output);
        $this->assertStringNotContainsString('&lt;span', $output);
    }

    public function testPriceWithoutContainerPassesFlag(): void
    {
        $priceCurrency = $this->createMock(PriceCurrencyInterface::class);
        $priceCurrency->expects($this->once())->method('format')
            ->with(1234, false)
            ->willReturn('$1,234.00');

        $this->assertStringContainsString('$1,234.00', $this->render('{{ 1234|price(false) }}', $priceCurrency));
    }

    public function testCurrencyFilterForwardsTheCurrencyCode(): void
    {
        $priceCurrency = $this->createMock(PriceCurrencyInterface::class);
        $priceCurrency->method('format')->willReturn('&euro;1.234,00');

        $output = $this->render("{{ 1234|currency('EUR') }}", $priceCurrency);

        $this->assertStringContainsString('&euro;1.234,00', $output);
    }
}
