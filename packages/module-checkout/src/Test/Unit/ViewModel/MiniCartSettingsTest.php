<?php
/**
 * This file is part of the MageObsidian - Checkout project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Checkout\Test\Unit\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use MageObsidian\Checkout\ViewModel\MiniCartSettings;
use PHPUnit\Framework\TestCase;

class MiniCartSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        if (!interface_exists(ScopeConfigInterface::class)) {
            $this->markTestSkipped('Magento Framework is not available in this runtime.');
        }
    }

    public function testIsEnabledFollowsTheSidebarDisplayFlag(): void
    {
        $this->assertTrue($this->settings(['checkout/sidebar/display' => true])->isEnabled());
        $this->assertFalse($this->settings(['checkout/sidebar/display' => false])->isEnabled());
    }

    public function testReadsTheMaximumNumberOfLines(): void
    {
        $this->assertSame(3, $this->settings(['checkout/sidebar/max_items_display_count' => '3'])->getMaxItems());
    }

    public function testNeverReportsLessThanOneLine(): void
    {
        $this->assertSame(1, $this->settings(['checkout/sidebar/max_items_display_count' => '0'])->getMaxItems());
        $this->assertSame(1, $this->settings(['checkout/sidebar/max_items_display_count' => null])->getMaxItems());
    }

    private function settings(array $values): MiniCartSettings
    {
        $config = $this->createMock(ScopeConfigInterface::class);
        $config->method('isSetFlag')->willReturnCallback(static fn (string $path): bool => (bool)($values[$path] ?? false));
        $config->method('getValue')->willReturnCallback(static fn (string $path) => $values[$path] ?? null);

        return new MiniCartSettings($config);
    }
}
