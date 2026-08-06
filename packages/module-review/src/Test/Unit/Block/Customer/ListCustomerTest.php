<?php
declare(strict_types=1);

namespace MageObsidian\Review\Test\Unit\Block\Customer;

use MageObsidian\Review\Block\Customer\ListCustomer;
use PHPUnit\Framework\TestCase;

/**
 * The subclass exists to undo the ten-review cap core applies through a pager it
 * never renders — see the block for why rendering that pager is not an option.
 */
class ListCustomerTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(\Magento\Review\Block\Customer\ListCustomer::class)) {
            $this->markTestSkipped('Magento framework is not available in this runtime.');
        }
    }

    public function testItExtendsTheCoreBlockRatherThanReplacingIt(): void
    {
        $this->assertTrue(
            is_subclass_of(ListCustomer::class, \Magento\Review\Block\Customer\ListCustomer::class),
            'The list stays core\'s, so its data source and URLs keep working.'
        );
    }

    public function testItOnlyOverridesTheLayoutHook(): void
    {
        $declared = (new \ReflectionClass(ListCustomer::class))->getMethods();
        $own = array_map(
            static fn (\ReflectionMethod $method): string => $method->getName(),
            array_filter(
                $declared,
                static fn (\ReflectionMethod $method): bool
                    => $method->getDeclaringClass()->getName() === ListCustomer::class
            )
        );

        $this->assertSame(['_prepareLayout'], $own);
    }
}
