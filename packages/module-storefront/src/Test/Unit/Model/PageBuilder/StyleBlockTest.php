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
use MageObsidian\Storefront\Model\PageBuilder\StyleBlock;
use PHPUnit\Framework\TestCase;

class StyleBlockTest extends TestCase
{
    private const PAGE_BUILDER = '<style>#html-body [data-pb-style="ABC"]{text-align:center}</style>'
        . '<div data-content-type="row" data-pb-style="ABC">hello</div>';

    private const BACKGROUND = '<style type="text/css">.background-image-6a9638acef592{'
        . 'background-image:url(media/hero.jpg)}</style>'
        . '<div class="row-full-width-inner background-image-6a9638acef592" data-content-type="row">hello</div>';

    private const BACKGROUND_MOBILE = '<style type="text/css">'
        . '@media only screen and (max-width: 768px){.background-image-6a9638acef592{'
        . 'background-image:url(media/hero-mobile.jpg)}}</style>';

    /** The same content Page Builder would render a second time: only the generated id differs. */
    private const BACKGROUND_RERENDERED = '<style type="text/css">.background-image-6a963943b6546{'
        . 'background-image:url(media/hero.jpg)}</style>'
        . '<div class="row-full-width-inner background-image-6a963943b6546" data-content-type="row">hello</div>';

    private const BACKGROUND_OTHER_IMAGE = '<style type="text/css">.background-image-6a9638acef592{'
        . 'background-image:url(media/other.jpg)}</style>'
        . '<div class="row-full-width-inner background-image-6a9638acef592" data-content-type="row">hello</div>';

    /**
     * The whole point: an author's styling is dropped by a policy that
     * disallows inline styles, and the page renders as unstyled markup with
     * nothing in the console to say the content was ever styled.
     */
    public function testHandsPageBuilderStylingToThePlatformSoItIsHashed(): void
    {
        $rewritten = $this->styleBlock()->rewrite(self::PAGE_BUILDER);

        $this->assertStringContainsString('<style nonce="rendered">', $rewritten);
        $this->assertStringContainsString('[data-pb-style="ABC"]{text-align:center}', $rewritten);
        $this->assertStringContainsString('data-content-type="row"', $rewritten);
    }

    /**
     * A `<style>` an admin typed into a plain HTML block is somebody's decision,
     * not Page Builder's output. Hashing it would quietly widen the policy.
     */
    public function testLeavesAStylesheetSomebodyPastedExactlyWhereItWas(): void
    {
        $pasted = '<style>.promo{color:red}</style><p class="promo">Sale</p>';

        $this->assertSame($pasted, $this->styleBlock()->rewrite($pasted));
    }

    /**
     * The sheet Page Builder generates for a background image carries a
     * generated class, not the scope it writes everything else against. Losing
     * it means every row, banner and slide the author put an image behind
     * renders with no image at all, and nothing says so.
     */
    public function testHandsGeneratedBackgroundStylingToThePlatformToo(): void
    {
        $rewritten = $this->styleBlock()->rewrite(self::BACKGROUND);

        $this->assertStringContainsString('<style nonce="rendered">', $rewritten);
        $this->assertStringContainsString('background-image:url(media/hero.jpg)', $rewritten);
    }

    /**
     * The regression this guards: content whose only generated sheet is a
     * background image never reached the rewrite at all, because the guard
     * asked for the author's scope before looking at anything else.
     */
    public function testRewritesBackgroundStylingWithNoAuthoredStylesheetPresent(): void
    {
        $rewritten = $this->styleBlock()->rewrite(self::BACKGROUND);

        $this->assertStringNotContainsString('<style type="text/css">', $rewritten);
        $this->assertSame(1, substr_count($rewritten, '<style nonce="rendered">'));
    }

    // The mobile variant is the same rules wrapped in a media query.
    public function testHandsBackgroundStylingInsideAMediaQueryToThePlatform(): void
    {
        $rewritten = $this->styleBlock()->rewrite(self::BACKGROUND_MOBILE);

        $this->assertStringContainsString('<style nonce="rendered">', $rewritten);
        $this->assertStringContainsString('max-width: 768px', $rewritten);
    }

    /**
     * Page Builder builds that class with `uniqid()`, so the sheet is a
     * different string on every render and no hash the platform computes can
     * survive to the next one. Two renders of the same content have to arrive
     * at the same class or the correction above buys nothing.
     */
    public function testGivesTheSameContentTheSameClassOnEveryRender(): void
    {
        $first = $this->styleBlock()->rewrite(self::BACKGROUND);
        $second = $this->styleBlock()->rewrite(self::BACKGROUND_RERENDERED);

        $this->assertSame($first, $second);
        $this->assertStringNotContainsString('6a9638acef592', $first);
        $this->assertStringNotContainsString('6a963943b6546', $second);
    }

    public function testGivesDifferentRulesDifferentClasses(): void
    {
        $hero = $this->classIn($this->styleBlock()->rewrite(self::BACKGROUND));
        $other = $this->classIn($this->styleBlock()->rewrite(self::BACKGROUND_OTHER_IMAGE));

        $this->assertNotSame($hero, $other);
    }

    // The rule is worth nothing if the element it was written for stops carrying it.
    public function testMovesTheElementOntoTheClassItRewroteTheRuleTo(): void
    {
        $rewritten = $this->styleBlock()->rewrite(self::BACKGROUND);
        $class = $this->classIn($rewritten);

        $this->assertNotSame('', $class);
        $this->assertStringContainsString('class="row-full-width-inner ' . $class . '"', $rewritten);
        $this->assertStringContainsString('.' . $class . '{', $rewritten);
    }

    public function testLeavesContentWithNoStylesheetAlone(): void
    {
        $plain = '<div data-content-type="text"><p>Just words</p></div>';

        $this->assertSame($plain, $this->styleBlock()->rewrite($plain));
    }

    // A page can carry more than one: Page Builder writes a block per saved
    // field, and a page made of several CMS blocks concatenates them.
    public function testRewritesEveryPageBuilderStylesheetOnThePage(): void
    {
        $rewritten = $this->styleBlock()->rewrite(self::PAGE_BUILDER . self::PAGE_BUILDER);

        $this->assertSame(2, substr_count($rewritten, '<style nonce="rendered">'));
    }

    // One authored sheet next to one Page Builder sheet: only the second moves.
    public function testTellsThemApartWhenBothAreOnThePage(): void
    {
        $rewritten = $this->styleBlock()->rewrite('<style>.promo{color:red}</style>' . self::PAGE_BUILDER);

        $this->assertStringContainsString('<style>.promo{color:red}</style>', $rewritten);
        $this->assertSame(1, substr_count($rewritten, '<style nonce="rendered">'));
    }

    // A row that was both styled by the author and given a background is the
    // ordinary case, and it is two separate sheets.
    public function testRewritesBothKindsWhenThePageCarriesThem(): void
    {
        $rewritten = $this->styleBlock()->rewrite(self::PAGE_BUILDER . self::BACKGROUND);

        $this->assertSame(2, substr_count($rewritten, '<style nonce="rendered">'));
    }

    // The pasted sheet still has to survive next to a generated background one.
    public function testLeavesAPastedStylesheetAloneBesideAGeneratedBackground(): void
    {
        $rewritten = $this->styleBlock()->rewrite('<style>.promo{color:red}</style>' . self::BACKGROUND);

        $this->assertStringContainsString('<style>.promo{color:red}</style>', $rewritten);
        $this->assertSame(1, substr_count($rewritten, '<style nonce="rendered">'));
    }

    private function classIn(string $html): string
    {
        return preg_match('/background-image-[0-9a-f]{13,}/', $html, $match) ? $match[0] : '';
    }

    private function styleBlock(): StyleBlock
    {
        $renderer = $this->createMock(SecureHtmlRenderer::class);
        $renderer->method('renderTag')->willReturnCallback(
            static fn (string $tag, array $attributes, string $content): string
                => '<style nonce="rendered">' . $content . '</style>'
        );

        return new StyleBlock($renderer);
    }
}
