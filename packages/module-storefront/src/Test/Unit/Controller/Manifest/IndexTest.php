<?php
declare(strict_types=1);

namespace MageObsidian\Storefront\Test\Unit\Controller\Manifest;

use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use MageObsidian\Storefront\Controller\Manifest\Index;
use MageObsidian\Storefront\Model\Config\SeoConfig;
use MageObsidian\Storefront\Model\Seo\WebManifestBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Serves the manifest the head links to. A Raw result, not a Json one: the Json
 * result forces `Content-Type: application/json` when it renders, which would
 * overwrite the media type a manifest has to carry. Needs Magento Controller
 * types, so it runs in a Magento root.
 */
class IndexTest extends TestCase
{
    private RawFactory&MockObject $resultRawFactory;
    private Raw&MockObject $result;
    private WebManifestBuilder&MockObject $builder;
    private SeoConfig&MockObject $config;

    /**
     * @var array<string, string>
     */
    private array $headers = [];

    protected function setUp(): void
    {
        if (!class_exists(RawFactory::class)) {
            $this->markTestSkipped('Magento framework is not available in this runtime.');
        }
        $this->resultRawFactory = $this->createMock(RawFactory::class);
        $this->result = $this->createMock(Raw::class);
        $this->builder = $this->createMock(WebManifestBuilder::class);
        $this->config = $this->createMock(SeoConfig::class);

        $this->resultRawFactory->method('create')->willReturn($this->result);
        $this->result->method('setHttpResponseCode')->willReturnSelf();
        $this->result->method('setContents')->willReturnSelf();
    }

    private function subject(): Index
    {
        return new Index($this->resultRawFactory, $this->builder, $this->config);
    }

    private function captureHeaders(): void
    {
        $this->result->method('setHeader')->willReturnCallback(
            function (string $name, string $value): object {
                $this->headers[$name] = $value;

                return $this->result;
            }
        );
    }

    public function testServesTheManifestUnderItsOwnMediaType(): void
    {
        $this->config->method('isManifestEnabled')->willReturn(true);
        $this->builder->method('build')->willReturn(
            ['name' => 'MageObsidian Demo', 'start_url' => 'https://shop.test/']
        );

        $this->captureHeaders();
        $this->result->expects($this->once())
            ->method('setContents')
            ->with('{"name":"MageObsidian Demo","start_url":"https://shop.test/"}');

        $this->assertSame($this->result, $this->subject()->execute());
        $this->assertSame(Index::CONTENT_TYPE, $this->headers['Content-Type'] ?? null);
    }

    public function testLetsBrowsersAndTheCdnCacheIt(): void
    {
        $this->config->method('isManifestEnabled')->willReturn(true);
        $this->builder->method('build')->willReturn(['name' => 'MageObsidian Demo']);

        $this->captureHeaders();

        $this->subject()->execute();

        $this->assertArrayHasKey('Cache-Control', $this->headers);
        $this->assertStringContainsString('public', $this->headers['Cache-Control']);
        $this->assertStringContainsString('max-age=' . Index::CACHE_LIFETIME, $this->headers['Cache-Control']);
    }

    public function testDoesNotAdvertiseCacheabilityWhenItAnswersNotFound(): void
    {
        $this->config->method('isManifestEnabled')->willReturn(false);
        $this->result->expects($this->never())->method('setHeader');

        $this->subject()->execute();
    }

    public function testAnswersNotFoundWhenTheMerchantTurnedItOff(): void
    {
        $this->config->method('isManifestEnabled')->willReturn(false);
        $this->builder->expects($this->never())->method('build');

        $this->result->expects($this->once())->method('setHttpResponseCode')->with(404);

        $this->assertSame($this->result, $this->subject()->execute());
    }
}
