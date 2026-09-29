<?php
/**
 * This file is part of the MageObsidian - Search project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Search\Test\Unit\Model\Fragment;

use MageObsidian\Search\Exception\FragmentUnavailableException;
use MageObsidian\Search\Model\Fragment\SectionPool;
use PHPUnit\Framework\TestCase;

class SectionPoolTest extends TestCase
{
    private const string ACTION = 'catalog_category_view';

    public function testItReturnsTheConfiguredSections(): void
    {
        $sections = ['listing' => 'category.products.list', 'filters' => 'catalog.leftnav'];

        $this->assertSame($sections, (new SectionPool([self::ACTION => $sections]))->get(self::ACTION));
    }

    public function testAnUnknownActionIsNotServable(): void
    {
        $pool = new SectionPool([self::ACTION => ['listing' => 'category.products.list']]);

        $this->assertFalse($pool->isServable('cms_index_index'));
    }

    public function testAnUnknownActionThrows(): void
    {
        $this->expectException(FragmentUnavailableException::class);

        (new SectionPool())->get(self::ACTION);
    }

    public function testASectionBlankedOutInDiIsDropped(): void
    {
        $pool = new SectionPool([
            self::ACTION => ['listing' => 'category.products.list', 'filters' => ''],
        ]);

        $this->assertSame(['listing' => 'category.products.list'], $pool->get(self::ACTION));
    }

    public function testAnActionWithEverySectionBlankedOutIsNotServable(): void
    {
        $pool = new SectionPool([self::ACTION => ['listing' => '']]);

        $this->assertFalse($pool->isServable(self::ACTION));
    }
}
