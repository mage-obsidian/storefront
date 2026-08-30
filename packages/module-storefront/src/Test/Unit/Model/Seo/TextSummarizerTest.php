<?php
declare(strict_types=1);

namespace MageObsidian\Storefront\Test\Unit\Model\Seo;

use MageObsidian\Storefront\Model\Seo\TextSummarizer;
use PHPUnit\Framework\TestCase;

/**
 * HTML to a meta-description-sized plain sentence. Page Builder content is the
 * hard case: it carries <style> blocks and {{...}} directives whose text would
 * otherwise end up in the summary. Pure logic, no Magento types.
 */
class TextSummarizerTest extends TestCase
{
    private TextSummarizer $summarizer;

    protected function setUp(): void
    {
        $this->summarizer = new TextSummarizer();
    }

    public function testStripsMarkupAndCollapsesWhitespace(): void
    {
        $html = "<p>A   <strong>modern</strong>\n\nfrontend</p>";

        $this->assertSame('A modern frontend', $this->summarizer->summarize($html));
    }

    public function testDropsStyleAndScriptContentsNotJustTheirTags(): void
    {
        $html = '<style>#html-body [data-pb-style="A"]{color:red}</style><p>Duffle bag</p>'
            . '<script>var a = 1;</script>';

        $this->assertSame('Duffle bag', $this->summarizer->summarize($html));
    }

    public function testDropsTemplateDirectives(): void
    {
        $html = '<p>{{widget type="Magento\Cms\Block\Widget\Block"}} Gear for the trail</p>';

        $this->assertSame('Gear for the trail', $this->summarizer->summarize($html));
    }

    public function testDecodesEntities(): void
    {
        $this->assertSame('Bags & packs', $this->summarizer->summarize('<p>Bags &amp; packs</p>'));
    }

    public function testTruncatesOnAWordBoundaryAndAppendsAnEllipsis(): void
    {
        $text = str_repeat('word ', 60);

        $summary = $this->summarizer->summarize($text, 20);

        $this->assertSame('word word word word…', $summary);
        $this->assertLessThanOrEqual(21, mb_strlen($summary));
    }

    public function testDoesNotTruncateWhenLengthIsZero(): void
    {
        $text = str_repeat('word ', 60);

        $this->assertSame(trim($text), $this->summarizer->summarize($text, 0));
    }

    public function testDecodedEntitiesNeverBecomeMarkupAgain(): void
    {
        $summary = $this->summarizer->summarize('<p>&lt;script&gt;alert(1)&lt;/script&gt; Duffle bag</p>');

        $this->assertStringNotContainsString('<', $summary);
        $this->assertStringNotContainsString('>', $summary);
        $this->assertStringNotContainsString('alert(1)', $summary);
        $this->assertSame('Duffle bag', $summary);
    }

    public function testDecodesEntitiesExactlyOnce(): void
    {
        $this->assertSame('&lt;b&gt;', $this->summarizer->summarize('&amp;lt;b&amp;gt;'));
    }

    public function testReturnsEmptyStringForMarkupWithNoText(): void
    {
        $this->assertSame('', $this->summarizer->summarize('<div><span></span></div>'));
        $this->assertSame('', $this->summarizer->summarize(''));
    }
}
