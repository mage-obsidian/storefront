<?php
declare(strict_types=1);

namespace MageObsidian\Storefront\Test\Unit\Model\Seo;

use DOMDocument;
use Magento\Framework\Escaper;
use MageObsidian\Storefront\Model\Seo\TextSummarizer;
use PHPUnit\Framework\TestCase;

/**
 * A summarised description reaches the head through
 * `Page\Config::setMetadata()`, which escapes with `Escaper::escapeHtml()`, and
 * `Renderer::renderMetadata()` then interpolates that value into
 * `content="%content"` with no escaping of its own. This walks the same two
 * steps with the real Escaper and parses the result, so a description full of
 * quotes and angle brackets is proven not to break out of the attribute. Needs
 * the Magento Escaper, so it runs in a Magento root.
 */
class MetaTagIntegrityTest extends TestCase
{
    private const string HOSTILE = '<p>Bag " onmouseover="alert(1)" x=" <script>alert(2)</script>'
        . ' &amp; &lt;img src=x onerror=alert(3)&gt; \'q\'</p>';

    private Escaper $escaper;
    private TextSummarizer $summarizer;

    protected function setUp(): void
    {
        if (!class_exists(Escaper::class)) {
            $this->markTestSkipped('Magento framework is not available in this runtime.');
        }
        $this->escaper = new Escaper();
        $this->summarizer = new TextSummarizer();
    }

    private function renderMetaTag(string $name, string $rawContent): string
    {
        $stored = $this->escaper->escapeHtml($this->summarizer->summarize($rawContent, 0));

        return str_replace(['%name', '%content'], [$name, $stored], '<meta name="%name" content="%content"/>');
    }

    public function testAHostileDescriptionStaysInsideTheContentAttribute(): void
    {
        $tag = $this->renderMetaTag('description', self::HOSTILE);

        $document = new DOMDocument();
        $this->assertTrue($document->loadHTML('<html><head>' . $tag . '</head><body></body></html>'));

        $metas = $document->getElementsByTagName('meta');
        $this->assertCount(1, $metas);
        $meta = $metas->item(0);
        $this->assertNotNull($meta);
        $this->assertSame('description', $meta->getAttribute('name'));
        $this->assertSame(
            'Bag " onmouseover="alert(1)" x=" & \'q\'',
            $meta->getAttribute('content')
        );
        $this->assertSame(0, $document->getElementsByTagName('script')->length);
        $this->assertSame(0, $document->getElementsByTagName('img')->length);
    }

    public function testTheOpenGraphAndTwitterTagsTakeTheSamePath(): void
    {
        foreach (['og:description', 'twitter:description', 'og:title', 'twitter:title'] as $name) {
            $tag = $this->renderMetaTag($name, self::HOSTILE);

            $this->assertSame(1, substr_count($tag, ' content="'), $name);
            $this->assertStringNotContainsString('onmouseover="alert', $tag, $name);
            $this->assertStringNotContainsString('<script', $tag, $name);
        }
    }

    public function testTheSummaryItselfCarriesNoMarkup(): void
    {
        $summary = $this->summarizer->summarize(self::HOSTILE, 0);

        $this->assertStringNotContainsString('<script', $summary);
        $this->assertStringNotContainsString('alert(2)', $summary);
    }
}
