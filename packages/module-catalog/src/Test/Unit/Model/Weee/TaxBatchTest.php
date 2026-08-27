<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Catalog project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Catalog\Test\Unit\Model\Weee;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use MageObsidian\Catalog\Model\Weee\TaxBatch;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class TaxBatchTest extends TestCase
{
    private int $queries = 0;

    /**
     * The whole point: a listing asks once per product for rows that differ only
     * by the product, and one statement answers for all of them.
     */
    public function testAnswersAWholePageFromOneStatement(): void
    {
        $batch = $this->batch([
            ['entity_id' => 1, 'attribute_code' => 'fpt', 'weee_value' => '5.0000'],
            ['entity_id' => 3, 'attribute_code' => 'fpt', 'weee_value' => '7.0000'],
        ]);
        $batch->expect([1, 2, 3]);

        $this->assertSame([['attribute_code' => 'fpt', 'weee_value' => '5.0000']], $batch->rowsFor('US', '0', 1, 1, 1));
        $this->assertSame([], $batch->rowsFor('US', '0', 1, 1, 2));
        $this->assertSame([['attribute_code' => 'fpt', 'weee_value' => '7.0000']], $batch->rowsFor('US', '0', 1, 1, 3));
        $this->assertSame(1, $this->queries);
    }

    /**
     * A wrong answer here is a wrong price, so anything this has nothing to say
     * about has to reach the platform's own method rather than be reported as
     * carrying no tax.
     */
    public function testSaysNothingAboutAProductThePageNeverLoaded(): void
    {
        $batch = $this->batch([]);
        $batch->expect([1]);
        $batch->rowsFor('US', '0', 1, 1, 1);

        $this->assertNull($batch->rowsFor('US', '0', 1, 1, 99));
    }

    public function testSaysNothingWhenNoPageWasLoadedAtAll(): void
    {
        $this->assertNull($this->batch([])->rowsFor('US', '0', 1, 1, 1));
        $this->assertSame(0, $this->queries);
    }

    // The rows depend on where the shopper is; another destination is another
    // question and must not be answered from this one's result.
    public function testDoesNotAnswerOneDestinationWithAnothersRows(): void
    {
        $batch = $this->batch([['entity_id' => 1, 'attribute_code' => 'fpt', 'weee_value' => '5.0000']]);
        $batch->expect([1]);
        $batch->rowsFor('US', '0', 1, 1, 1);

        $this->assertNull($batch->rowsFor('DE', '0', 1, 1, 1));
    }

    /**
     * A warm pass that failed must not be retried once per product: that would
     * cost more than the queries it set out to save.
     */
    public function testFallsBackQuietlyAndOnlyOnceWhenTheQueryFails(): void
    {
        $batch = $this->failingBatch();
        $batch->expect([1, 2]);

        $this->assertNull($batch->rowsFor('US', '0', 1, 1, 1));
        $this->assertNull($batch->rowsFor('US', '0', 1, 1, 2));
        $this->assertSame(1, $this->queries);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function batch(array $rows): TaxBatch
    {
        return new TaxBatch($this->resource(static fn (): array => $rows));
    }

    private function failingBatch(): TaxBatch
    {
        return new TaxBatch($this->resource(static function (): array {
            throw new RuntimeException('table is gone');
        }));
    }

    private function resource(callable $answer): ResourceConnection
    {
        $select = $this->createMock(Select::class);
        foreach (['from', 'joinLeft', 'joinInner', 'where', 'order'] as $method) {
            $select->method($method)->willReturnSelf();
        }

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchAll')->willReturnCallback(function () use ($answer): array {
            $this->queries++;
            return $answer();
        });

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        return $resource;
    }
}
