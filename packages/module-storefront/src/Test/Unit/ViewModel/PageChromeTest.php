<?php
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Storefront\Test\Unit\ViewModel;

use Magento\Framework\App\Config\Initial;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use MageObsidian\Storefront\ViewModel\PageChrome;
use PHPUnit\Framework\TestCase;

class PageChromeTest extends TestCase
{
    private const SHIPPED_WELCOME = 'Default welcome msg!';
    private const SHIPPED_COPYRIGHT = 'Copyright © 2013-present Magento, Inc. All rights reserved.';

    protected function setUp(): void
    {
        if (!interface_exists(ScopeConfigInterface::class) || !class_exists(Initial::class)) {
            $this->markTestSkipped('The Magento framework is not available in this runtime.');
        }
    }

    private function subject(?string $welcome, ?string $copyright): PageChrome
    {
        $values = ['design/header/welcome' => $welcome, 'design/footer/copyright' => $copyright];
        $config = $this->createStub(ScopeConfigInterface::class);
        $config->method('getValue')->willReturnCallback(
            static fn (string $path, string $scope): ?string => $scope === ScopeInterface::SCOPE_STORE ? $values[$path] : null
        );
        $initial = $this->createStub(Initial::class);
        $initial->method('getData')->willReturn([
            'design' => [
                'header' => ['welcome' => self::SHIPPED_WELCOME],
                'footer' => ['copyright' => self::SHIPPED_COPYRIGHT],
            ],
        ]);

        return new PageChrome($config, $initial);
    }

    public function testReturnsTheWelcomeMessageTheMerchantConfigured(): void
    {
        $this->assertSame('Free shipping over 50', $this->subject('Free shipping over 50', null)->getWelcome());
    }

    public function testTrimsSurroundingWhitespaceFromTheWelcomeMessage(): void
    {
        $this->assertSame('Hello', $this->subject("  Hello \n", null)->getWelcome());
    }

    public function testTheMessageTheCoreShipsWithIsNotAWelcomeMessage(): void
    {
        $this->assertSame('', $this->subject(self::SHIPPED_WELCOME, null)->getWelcome());
    }

    public function testAnEmptyWelcomeMessageMeansNoMessage(): void
    {
        $this->assertSame('', $this->subject('', null)->getWelcome());
        $this->assertSame('', $this->subject(null, null)->getWelcome());
        $this->assertSame('', $this->subject('   ', null)->getWelcome());
    }

    public function testACopyrightTheMerchantWroteIsConfigured(): void
    {
        $this->assertTrue($this->subject(null, '© Atelier 2026')->hasCopyright());
    }

    public function testTheCopyrightTheCoreShipsWithIsNotConfigured(): void
    {
        $this->assertFalse($this->subject(null, self::SHIPPED_COPYRIGHT)->hasCopyright());
    }

    public function testAnEmptyCopyrightIsNotConfigured(): void
    {
        $this->assertFalse($this->subject(null, '')->hasCopyright());
        $this->assertFalse($this->subject(null, null)->hasCopyright());
    }
}
