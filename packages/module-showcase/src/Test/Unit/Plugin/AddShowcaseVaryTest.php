<?php
/**
 * This file is part of the MageObsidian - Showcase project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Showcase\Test\Unit\Plugin;

use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Http\Context as HttpContext;
use MageObsidian\ModernFrontend\Service\Theme\ObsidianThemeDetector;
use MageObsidian\Showcase\Model\Profile;
use MageObsidian\Showcase\Plugin\App\AddShowcaseVary;
use PHPUnit\Framework\TestCase;

class AddShowcaseVaryTest extends TestCase
{
    public function testPutsTheProfileInTheVaryUnderAnObsidianTheme(): void
    {
        $context = $this->createMock(HttpContext::class);
        $context->expects($this->once())
            ->method('setValue')
            ->with(AddShowcaseVary::CONTEXT_KEY, 'listing_fragments=0', AddShowcaseVary::DEFAULT_SIGNATURE);

        $this->plugin($context, true)->beforeExecute($this->createStub(ActionInterface::class));
    }

    public function testLeavesTheVaryAloneOutsideAnObsidianTheme(): void
    {
        $context = $this->createMock(HttpContext::class);
        $context->expects($this->never())->method('setValue');

        $this->plugin($context, false)->beforeExecute($this->createStub(ActionInterface::class));
    }

    private function plugin(HttpContext $context, bool $obsidianTheme): AddShowcaseVary
    {
        $profile = $this->createStub(Profile::class);
        $profile->method('signature')->willReturn('listing_fragments=0');
        $detector = $this->createStub(ObsidianThemeDetector::class);
        $detector->method('isActive')->willReturn($obsidianTheme);

        return new AddShowcaseVary($context, $profile, $detector);
    }
}
