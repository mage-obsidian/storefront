<?php
/**
 * This file is part of the MageObsidian - Downloadable project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Downloadable\Test\Unit\Block\Customer\Products;

use MageObsidian\Downloadable\Block\Customer\Products\ListProducts;
use PHPUnit\Framework\TestCase;

/**
 * The subclass exists for one reason: the pager core creates in PHP carries Luma's
 * template, which this theme cannot render — and an unrendered pager silently caps
 * the purchased-links list at the page size it also applies.
 */
class ListProductsTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(\Magento\Downloadable\Block\Customer\Products\ListProducts::class)) {
            $this->markTestSkipped('Magento framework is not available in this runtime.');
        }
    }

    public function testItReTemplatesTheCoreBlockRatherThanReplacingIt(): void
    {
        $this->assertTrue(
            is_subclass_of(
                ListProducts::class,
                \Magento\Downloadable\Block\Customer\Products\ListProducts::class
            ),
            'The pager must stay the one core builds, so its page size is applied before the load.'
        );
    }

    public function testThePagerTemplateIsTheThemeOne(): void
    {
        $this->assertSame('Magento_Theme::html/pager.twig', ListProducts::PAGER_TEMPLATE);
    }
}
