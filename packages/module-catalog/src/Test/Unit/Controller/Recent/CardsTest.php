<?php
/**
 * This file is part of the MageObsidian - Catalog project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Catalog\Test\Unit\Controller\Recent;

use Magento\Catalog\Model\Product;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\View\LayoutInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use MageObsidian\Catalog\Block\Recent\Cards as CardsBlock;
use MageObsidian\Catalog\Controller\Recent\Cards;
use MageObsidian\Catalog\Model\Recent\ProductSelection;
use PHPUnit\Framework\TestCase;

class CardsTest extends TestCase
{
    private ?string $contents = null;

    private ?int $code = null;

    private array $headers = [];

    protected function setUp(): void
    {
        if (!class_exists(Raw::class)) {
            $this->markTestSkipped('Magento Framework is not available in this runtime.');
        }
    }

    private function raw(): Raw
    {
        $raw = $this->createStub(Raw::class);
        $raw->method('setHeader')->willReturnCallback(function (string $name, string $value) use ($raw): Raw {
            $this->headers[$name] = $value;

            return $raw;
        });
        $raw->method('setHttpResponseCode')->willReturnCallback(function (int $code) use ($raw): Raw {
            $this->code = $code;

            return $raw;
        });
        $raw->method('setContents')->willReturnCallback(function (string $contents) use ($raw): Raw {
            $this->contents = $contents;

            return $raw;
        });

        return $raw;
    }

    private ProductSelection $selection;

    private function controller(array $params, array $found, mixed $block = null, bool $watch = false): Cards
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(static fn(string $name, mixed $default = null): mixed => $params[$name] ?? $default);

        $rawFactory = $this->createStub(RawFactory::class);
        $rawFactory->method('create')->willReturn($this->raw());

        $layout = $this->createStub(LayoutInterface::class);
        $layout->method('getBlock')->willReturn($block);
        $page = $this->createStub(Page::class);
        $page->method('getLayout')->willReturn($layout);
        $pageFactory = $this->createStub(PageFactory::class);
        $pageFactory->method('create')->willReturn($page);

        $this->selection = $watch ? $this->createMock(ProductSelection::class) : $this->createStub(ProductSelection::class);
        $this->selection->method('idsFrom')->willReturnCallback(static fn(string $raw): array => array_map('intval', array_filter(explode(',', $raw))));
        if (!$watch) {
            $this->selection->method('load')->willReturn($found);
        }

        return new Cards($request, $rawFactory, $pageFactory, $this->selection);
    }

    public function testNothingIsRenderedWhenNoProductSurvives(): void
    {
        $this->controller(['ids' => '1,2'], [])->execute();

        $this->assertSame('', $this->contents);
    }

    public function testTheCardsBlockIsRenderedWithTheProductsFound(): void
    {
        $products = [$this->createStub(Product::class)];
        $block = $this->createMock(CardsBlock::class);
        $block->expects($this->once())->method('setData')->with('products', $products)->willReturnSelf();
        $block->method('toHtml')->willReturn('<ol>cards</ol>');

        $this->controller(['ids' => '1'], $products, $block)->execute();

        $this->assertSame('<ol>cards</ol>', $this->contents);
    }

    public function testTheResponseIsNeverStored(): void
    {
        $this->controller(['ids' => '1'], [])->execute();

        $this->assertStringContainsString('no-store', $this->headers['Cache-Control']);
    }

    public function testALayoutWithoutTheCardsBlockIsAServerError(): void
    {
        $this->controller(['ids' => '1'], [$this->createStub(Product::class)], null)->execute();

        $this->assertSame(500, $this->code);
    }

    public function testTheCountIsClampedToWhatTheEndpointServes(): void
    {
        $controller = $this->controller(['ids' => '1', 'count' => '500'], [], null, true);
        $this->selection->expects($this->once())->method('load')->with([1], ProductSelection::MAX_IDS)->willReturn([]);

        $controller->execute();
    }

    public function testTheCountDefaultsWhenNoneIsGiven(): void
    {
        $controller = $this->controller(['ids' => '1'], [], null, true);
        $this->selection->expects($this->once())->method('load')->with([1], Cards::DEFAULT_COUNT)->willReturn([]);

        $controller->execute();
    }
}
