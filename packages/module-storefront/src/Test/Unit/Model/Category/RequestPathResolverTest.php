<?php
declare(strict_types=1);

namespace MageObsidian\Storefront\Test\Unit\Model\Category;

use Magento\Catalog\Model\Category;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use MageObsidian\Storefront\Model\Category\RequestPathResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The regression this guards: asking each category for its URL made
 * Category::getUrl() fall through to a per-category UrlFinder::findOneByData(),
 * 150 of the 207 queries on a real category page. Whatever the caller collected
 * must resolve through a single findAllByData(). Needs Magento Catalog and
 * UrlRewrite types, so it runs in a Magento root (see phpunit.ci.xml).
 */
class RequestPathResolverTest extends TestCase
{
    private UrlFinderInterface&MockObject $urlFinder;

    protected function setUp(): void
    {
        if (!class_exists(Category::class)) {
            $this->markTestSkipped('Magento Catalog is not available in this runtime.');
        }
        $this->urlFinder = $this->createMock(UrlFinderInterface::class);
    }

    private function category(): Category&MockObject
    {
        return $this->createMock(Category::class);
    }

    private function rewrite(int $entityId, string $requestPath): UrlRewrite&MockObject
    {
        $rewrite = $this->createMock(UrlRewrite::class);
        $rewrite->method('getEntityId')->willReturn($entityId);
        $rewrite->method('getRequestPath')->willReturn($requestPath);

        return $rewrite;
    }

    private function subject(): RequestPathResolver
    {
        return new RequestPathResolver($this->urlFinder);
    }

    public function testResolvesEveryCategoryInOneLookup(): void
    {
        $this->urlFinder->expects($this->once())
            ->method('findAllByData')
            ->with($this->callback(static function (array $data): bool {
                return $data[UrlRewrite::ENTITY_TYPE] === 'category'
                    && $data[UrlRewrite::STORE_ID] === 1
                    && $data[UrlRewrite::REDIRECT_TYPE] === 0
                    && $data[UrlRewrite::ENTITY_ID] === [10, 11, 20, 21];
            }))
            ->willReturn([]);

        $this->subject()->seed(
            [
                10 => $this->category(),
                11 => $this->category(),
                20 => $this->category(),
                21 => $this->category(),
            ],
            1
        );
    }

    public function testSeedsRequestPathSoGetUrlSkipsItsOwnLookup(): void
    {
        $category = $this->category();
        $category->expects($this->once())
            ->method('setData')
            ->with('request_path', 'outerwear.html');

        $this->urlFinder->method('findAllByData')->willReturn([$this->rewrite(10, 'outerwear.html')]);

        $this->subject()->seed([10 => $category], 1);
    }

    /**
     * getUrl()'s own lookup ends in a fetchRow() with no ORDER BY, so it takes the
     * first row. Seeding from the last row instead would pick a different path than
     * core whenever a category has more than one non-redirect rewrite.
     */
    public function testKeepsTheFirstRewriteWhenACategoryHasSeveral(): void
    {
        $category = $this->category();
        $category->expects($this->once())
            ->method('setData')
            ->with('request_path', 'primero.html');

        $this->urlFinder->method('findAllByData')->willReturn([
            $this->rewrite(10, 'primero.html'),
            $this->rewrite(10, 'segundo.html'),
        ]);

        $this->subject()->seed([10 => $category], 1);
    }

    /**
     * A category with no rewrite is left alone on purpose: getUrl() then takes its
     * own fallback and still returns a working id-based URL.
     */
    public function testLeavesCategoriesWithoutARewriteUntouched(): void
    {
        $withRewrite = $this->category();
        $withRewrite->expects($this->once())->method('setData');

        $withoutRewrite = $this->category();
        $withoutRewrite->expects($this->never())->method('setData');

        $this->urlFinder->method('findAllByData')->willReturn([$this->rewrite(10, 'outerwear.html')]);

        $this->subject()->seed([10 => $withRewrite, 11 => $withoutRewrite], 1);
    }

    public function testSkipsRewritesForCategoriesOutsideTheSet(): void
    {
        $category = $this->category();
        $category->expects($this->never())->method('setData');

        $this->urlFinder->method('findAllByData')->willReturn([$this->rewrite(99, 'otra.html')]);

        $this->subject()->seed([10 => $category], 1);
    }

    public function testDoesNotQueryWithoutCategories(): void
    {
        $this->urlFinder->expects($this->never())->method('findAllByData');

        $this->subject()->seed([], 1);
    }
}
