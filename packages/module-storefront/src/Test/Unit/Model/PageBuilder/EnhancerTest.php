<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Storefront\Test\Unit\Model\PageBuilder;

use Magento\Framework\View\Helper\SecureHtmlRenderer;
use MageObsidian\ModernFrontend\ViewModel\ViteResolver;
use MageObsidian\Storefront\Model\PageBuilder\Detector\MarkerDetector;
use MageObsidian\Storefront\Model\PageBuilder\Enhancer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class EnhancerTest extends TestCase
{
    public function testPullsTheBehaviourInBesideATabSet(): void
    {
        $enhanced = $this->enhancer()->enhance('<div data-content-type="tabs"></div>');

        $this->assertStringContainsString('src="/js/MageObsidian_Storefront::js/page-builder.js"', $enhanced);
        $this->assertStringContainsString('data-content-type="tabs"', $enhanced);
    }

    public function testPullsItInBesideASlider(): void
    {
        $this->assertStringContainsString(
            '<script',
            $this->enhancer()->enhance('<div data-content-type="slider"></div>')
        );
    }

    /**
     * Everything else Page Builder renders is finished markup, so a store whose
     * authors never used a tab set never pays for one.
     */
    public function testLeavesContentThatNeedsNoBehaviourUntouched(): void
    {
        $plain = '<div data-content-type="row"><div data-content-type="text"><p>Words</p></div></div>';

        $this->assertSame($plain, $this->enhancer()->enhance($plain));
    }

    public function testLeavesContentThatIsNotPageBuilderAtAllUntouched(): void
    {
        $this->assertSame('<p>Just words</p>', $this->enhancer()->enhance('<p>Just words</p>'));
    }

    // An unbuilt theme is a broken deployment, not a reason to lose the page.
    public function testKeepsTheContentWhenTheBuildHasNotRun(): void
    {
        $resolver = $this->createMock(ViteResolver::class);
        $resolver->method('getViteFileUrl')->willThrowException(new RuntimeException('no manifest'));

        $content = '<div data-content-type="tabs"></div>';

        $this->assertSame($content, $this->enhancer($resolver)->enhance($content));
    }

    // The map is declarative, so a module contributing one is a first-class
    // case and not an afterthought: the same class, one more entry.
    public function testLoadsWhatAnotherModuleDeclaredForItsOwnContent(): void
    {
        $enhancer = $this->enhancer(null, [
            new MarkerDetector('data-content-type="tabs"', 'MageObsidian_Storefront::js/page-builder'),
            new MarkerDetector('data-content-type="map"', 'Vendor_Module::js/map'),
        ]);

        $enhanced = $enhancer->enhance('<div data-content-type="map"></div>');

        $this->assertStringContainsString('src="/js/Vendor_Module::js/map.js"', $enhanced);
        $this->assertStringNotContainsString('page-builder', $enhanced);
    }

    public function testLoadsOneBehaviourPerModuleAndNotOnePerMatch(): void
    {
        $enhancer = $this->enhancer(null, [
            new MarkerDetector('data-content-type="tabs"', 'MageObsidian_Storefront::js/page-builder'),
            new MarkerDetector('data-content-type="slider"', 'MageObsidian_Storefront::js/page-builder'),
        ]);

        $enhanced = $enhancer->enhance('<div data-content-type="tabs"></div><div data-content-type="slider"></div>');

        $this->assertSame(1, substr_count($enhanced, '<script'));
    }

    public function testLoadsEachBehaviourWhenSeveralKindsAreOnThePage(): void
    {
        $enhancer = $this->enhancer(null, [
            new MarkerDetector('data-content-type="tabs"', 'MageObsidian_Storefront::js/page-builder'),
            new MarkerDetector('data-content-type="map"', 'Vendor_Module::js/map'),
        ]);

        $enhanced = $enhancer->enhance('<div data-content-type="tabs"></div><div data-content-type="map"></div>');

        $this->assertSame(2, substr_count($enhanced, '<script'));
    }

    // Nothing declared is the state a bad compile leaves the argument in, and
    // it has to read as "no page needs behaviour", never as "every page does".
    public function testLoadsNothingWhenNoDetectorIsDeclared(): void
    {
        $content = '<div data-content-type="tabs"></div>';

        $this->assertSame($content, $this->enhancer(null, [])->enhance($content));
    }

    /**
     * @param ViteResolver|null $resolver
     * @param array|null $detectors
     *
     * @return Enhancer
     */
    private function enhancer(?ViteResolver $resolver = null, ?array $detectors = null): Enhancer
    {
        if ($resolver === null) {
            $resolver = $this->createMock(ViteResolver::class);
            $resolver->method('getViteFileUrl')->willReturnCallback(
                static fn (string $module): string => '/js/' . $module . '.js'
            );
        }

        $renderer = $this->createMock(SecureHtmlRenderer::class);
        $renderer->method('renderTag')->willReturnCallback(
            static fn (string $tag, array $attributes): string
                => '<script type="module" src="' . $attributes['src'] . '"></script>'
        );

        return new Enhancer($resolver, $renderer, $detectors ?? [
            new MarkerDetector('data-content-type="tabs"', 'MageObsidian_Storefront::js/page-builder'),
            new MarkerDetector('data-content-type="slider"', 'MageObsidian_Storefront::js/page-builder'),
        ]);
    }
}
