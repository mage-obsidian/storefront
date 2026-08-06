<?php
declare(strict_types=1);

namespace MageObsidian\Sales\Test\Unit\ViewModel;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\Item\CollectionFactory as OrderItemCollectionFactory;
use MageObsidian\Sales\ViewModel\OrderPresentation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What the account templates need to show an order. The chip tone must key off
 * the STATE (merchants add statuses, states are fixed) and the track must place
 * the order on the right step.
 */
class OrderPresentationTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Order::class)) {
            $this->markTestSkipped('Magento framework is not available in this runtime.');
        }
    }

    private function buildViewModel(): OrderPresentation
    {
        return new OrderPresentation(
            $this->createMock(OrderItemCollectionFactory::class),
            $this->createMock(ProductCollectionFactory::class),
            $this->createMock(ImageHelper::class)
        );
    }

    private function order(string $state, bool $hasShipments = false): Order
    {
        $order = $this->createMock(Order::class);
        $order->method('getState')->willReturn($state);
        $order->method('hasShipments')->willReturn($hasShipments);

        return $order;
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function toneProvider(): array
    {
        return [
            'new is neutral' => [Order::STATE_NEW, 'neutral'],
            'processing is progress' => [Order::STATE_PROCESSING, 'progress'],
            'complete is good' => [Order::STATE_COMPLETE, 'good'],
            'holded warns' => [Order::STATE_HOLDED, 'warn'],
            'canceled is dead' => [Order::STATE_CANCELED, 'dead'],
        ];
    }

    #[DataProvider('toneProvider')]
    public function testMapsTheStateToAChipTone(string $state, string $expected): void
    {
        $this->assertSame($expected, $this->buildViewModel()->getStateTone($this->order($state)));
    }

    public function testAnUnknownStateReadsAsNeutralRatherThanBreaking(): void
    {
        $this->assertSame('neutral', $this->buildViewModel()->getStateTone($this->order('invented_state')));
    }

    public function testTheTrackStopsAtReceivedForANewOrder(): void
    {
        $track = $this->buildViewModel()->getTrack($this->order(Order::STATE_NEW));

        $this->assertSame(['received', 'processing', 'shipped', 'delivered'], array_column($track, 'key'));
        $this->assertSame(['current', 'pending', 'pending', 'pending'], array_column($track, 'state'));
    }

    public function testTheTrackReachesProcessing(): void
    {
        $track = $this->buildViewModel()->getTrack($this->order(Order::STATE_PROCESSING));

        $this->assertSame(['done', 'current', 'pending', 'pending'], array_column($track, 'state'));
    }

    public function testAShipmentMovesTheTrackPastProcessing(): void
    {
        $track = $this->buildViewModel()->getTrack($this->order(Order::STATE_PROCESSING, hasShipments: true));

        $this->assertSame(['done', 'done', 'current', 'pending'], array_column($track, 'state'));
    }

    public function testACompleteOrderFillsTheTrack(): void
    {
        $track = $this->buildViewModel()->getTrack($this->order(Order::STATE_COMPLETE));

        $this->assertSame(['done', 'done', 'done', 'current'], array_column($track, 'state'));
    }

    public function testCanceledAndClosedOrdersAreNotTrackable(): void
    {
        $presentation = $this->buildViewModel();

        $this->assertFalse($presentation->isTrackable($this->order(Order::STATE_CANCELED)));
        $this->assertFalse($presentation->isTrackable($this->order(Order::STATE_CLOSED)));
        $this->assertTrue($presentation->isTrackable($this->order(Order::STATE_PROCESSING)));
    }

    public function testNoOrdersMeansNoQueries(): void
    {
        $itemFactory = $this->createMock(OrderItemCollectionFactory::class);
        $itemFactory->expects($this->never())->method('create');

        $presentation = new OrderPresentation(
            $itemFactory,
            $this->createMock(ProductCollectionFactory::class),
            $this->createMock(ImageHelper::class)
        );

        $this->assertSame([], $presentation->getThumbnails([]));
    }
}
