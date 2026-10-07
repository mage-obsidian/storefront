<?php
/**
 * This file is part of the MageObsidian - Persistent project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Persistent\Test\Unit\ViewModel;

use Magento\Persistent\Helper\Data as PersistentHelper;
use MageObsidian\Persistent\ViewModel\Greeting;
use PHPUnit\Framework\TestCase;

class GreetingTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(PersistentHelper::class)) {
            $this->markTestSkipped('Magento Persistent is not available in this runtime.');
        }
    }

    private function helper(bool $enabled): PersistentHelper
    {
        $helper = $this->createStub(PersistentHelper::class);
        $helper->method('isEnabled')->willReturn($enabled);

        return $helper;
    }

    public function testItIsOfferedOnlyWhenPersistenceIsOn(): void
    {
        $this->assertTrue((new Greeting($this->helper(true)))->isEnabled());
        $this->assertFalse((new Greeting($this->helper(false)))->isEnabled());
    }
}
