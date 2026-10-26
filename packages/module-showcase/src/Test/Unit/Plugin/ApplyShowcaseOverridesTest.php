<?php
/**
 * This file is part of the MageObsidian - Showcase project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Showcase\Test\Unit\Plugin;

use Magento\Framework\App\Config\ScopeConfigInterface;
use MageObsidian\ModernFrontend\Service\Theme\ObsidianThemeDetector;
use MageObsidian\Showcase\Model\Profile;
use MageObsidian\Showcase\Plugin\Config\ApplyShowcaseOverrides;
use PHPUnit\Framework\TestCase;

class ApplyShowcaseOverridesTest extends TestCase
{
    private function plugin(array $overrides, bool $obsidianTheme = true): ApplyShowcaseOverrides
    {
        $profile = $this->createMock(Profile::class);
        $profile->method('overrides')->willReturn($overrides);

        return new ApplyShowcaseOverrides($profile, $this->detector($obsidianTheme));
    }

    private function detector(bool $active): ObsidianThemeDetector
    {
        $detector = $this->createStub(ObsidianThemeDetector::class);
        $detector->method('isActive')->willReturn($active);

        return $detector;
    }

    public function testReplacesTheValueOfASwitchedPath(): void
    {
        $plugin = $this->plugin(['mage_obsidian/listing/fragments_enabled' => '0']);

        $result = $plugin->afterGetValue(
            $this->createMock(ScopeConfigInterface::class),
            '1',
            'mage_obsidian/listing/fragments_enabled'
        );

        $this->assertSame('0', $result);
    }

    public function testLeavesEveryOtherPathAlone(): void
    {
        $plugin = $this->plugin(['mage_obsidian/listing/fragments_enabled' => '0']);

        $result = $plugin->afterGetValue(
            $this->createMock(ScopeConfigInterface::class),
            'live',
            'payment/checkmo/active'
        );

        $this->assertSame('live', $result);
    }

    // getValue() with no path returns the whole tree; there is nothing to match.
    public function testPassesThroughWhenThereIsNoPath(): void
    {
        $plugin = $this->plugin(['a/b/c' => '0']);

        $this->assertSame(
            ['everything'],
            $plugin->afterGetValue($this->createMock(ScopeConfigInterface::class), ['everything'], null)
        );
    }

    public function testPassesThroughWithNoProfile(): void
    {
        $plugin = $this->plugin([]);

        $this->assertSame(
            '1',
            $plugin->afterGetValue($this->createMock(ScopeConfigInterface::class), '1', 'a/b/c')
        );
    }

    // The panel needs the store's own value to tell it apart from the visitor's.
    public function testHandsTheStoreValueBackToTheProfile(): void
    {
        $profile = $this->createMock(Profile::class);
        $profile->method('overrides')->willReturn(['a/b/c' => '0']);
        $profile->expects($this->once())->method('rememberOriginal')->with('a/b/c', '1');

        (new ApplyShowcaseOverrides($profile, $this->detector(true)))
            ->afterGetValue($this->createMock(ScopeConfigInterface::class), '1', 'a/b/c');
    }

    public function testRemembersNothingForAPathItDidNotTouch(): void
    {
        $profile = $this->createMock(Profile::class);
        $profile->method('overrides')->willReturn(['a/b/c' => '0']);
        $profile->expects($this->never())->method('rememberOriginal');

        (new ApplyShowcaseOverrides($profile, $this->detector(true)))
            ->afterGetValue($this->createMock(ScopeConfigInterface::class), 'live', 'payment/checkmo/active');
    }

    public function testOutsideAnObsidianThemeTheStoreValueStands(): void
    {
        $profile = $this->createMock(Profile::class);
        $profile->method('overrides')->willReturn(['mage_obsidian/listing/fragments_enabled' => '0']);
        $profile->expects($this->never())->method('rememberOriginal');

        $result = (new ApplyShowcaseOverrides($profile, $this->detector(false)))->afterGetValue(
            $this->createStub(ScopeConfigInterface::class),
            '1',
            'mage_obsidian/listing/fragments_enabled'
        );

        $this->assertSame('1', $result);
    }

    public function testAPathWithoutAnOverrideNeverAsksForTheTheme(): void
    {
        $detector = $this->createMock(ObsidianThemeDetector::class);
        $detector->expects($this->never())->method('isActive');
        $profile = $this->createStub(Profile::class);
        $profile->method('overrides')->willReturn(['mage_obsidian/listing/fragments_enabled' => '0']);

        $result = (new ApplyShowcaseOverrides($profile, $detector))->afterGetValue(
            $this->createStub(ScopeConfigInterface::class),
            '3',
            'design/theme/theme_id'
        );

        $this->assertSame('3', $result);
    }
}
