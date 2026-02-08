<?php
declare(strict_types=1);

namespace MageObsidian\Downloadable\Test\Unit\ViewModel;

use Magento\Downloadable\Model\Link;
use Magento\Downloadable\Model\Link\Purchased as PurchasedEntity;
use Magento\Downloadable\Model\Sales\Order\Link\Purchased;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use MageObsidian\Downloadable\ViewModel\OrderItemLinks;
use PHPUnit\Framework\TestCase;

/**
 * Lists the purchased-link titles for a downloadable order line. We assert the
 * titles come through, that a non-downloadable line yields nothing, and the
 * section title falls back to "Links".
 */
class OrderItemLinksTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Purchased::class)) {
            $this->markTestSkipped('Magento_Downloadable is not available in this runtime.');
        }
    }

    public function testReturnsPurchasedLinkTitles(): void
    {
        $entity = $this->purchasedEntity([
            new DataObject(['link_title' => 'Chapter 1']),
            new DataObject(['link_title' => '  Chapter 2  ']),
            new DataObject(['link_title' => '']),
        ]);

        $viewModel = new OrderItemLinks($this->resolver($entity), $this->scopeConfig(null));

        $this->assertSame(['Chapter 1', 'Chapter 2'], $viewModel->getLinks(new DataObject()));
    }

    public function testReturnsNoLinksForNonDownloadableLine(): void
    {
        $viewModel = new OrderItemLinks($this->resolver($this->purchasedEntity([])), $this->scopeConfig(null));

        $this->assertSame([], $viewModel->getLinks(new DataObject()));
    }

    public function testTitleFallsBackToLinksWhenUnconfigured(): void
    {
        $viewModel = new OrderItemLinks($this->createMock(Purchased::class), $this->scopeConfig(null));

        $this->assertSame('Links', $viewModel->getTitle());
    }

    public function testTitleUsesConfiguredSectionTitle(): void
    {
        $viewModel = new OrderItemLinks($this->createMock(Purchased::class), $this->scopeConfig('Downloads'));

        $this->assertSame('Downloads', $viewModel->getTitle());
    }

    /**
     * A purchased entity whose getPurchasedItems (a magic getter) returns $items.
     *
     * @param array<int, DataObject> $items
     * @return PurchasedEntity
     */
    private function purchasedEntity(array $items): PurchasedEntity
    {
        $entity = $this->getMockBuilder(PurchasedEntity::class)
            ->disableOriginalConstructor()
            ->addMethods(['getPurchasedItems'])
            ->getMock();
        $entity->method('getPurchasedItems')->willReturn($items);

        return $entity;
    }

    /**
     * The link resolver stubbed to return $entity for any item.
     *
     * @param PurchasedEntity $entity
     * @return Purchased
     */
    private function resolver(PurchasedEntity $entity): Purchased
    {
        $resolver = $this->createMock(Purchased::class);
        $resolver->method('getLink')->willReturn($entity);

        return $resolver;
    }

    /**
     * Scope config stubbed to return $value for the links-title path.
     *
     * @param string|null $value
     * @return ScopeConfigInterface
     */
    private function scopeConfig(?string $value): ScopeConfigInterface
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->with(Link::XML_PATH_LINKS_TITLE, 'store')->willReturn($value);

        return $scopeConfig;
    }
}
