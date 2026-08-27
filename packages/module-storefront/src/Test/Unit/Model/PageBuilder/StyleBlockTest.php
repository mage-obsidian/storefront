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
