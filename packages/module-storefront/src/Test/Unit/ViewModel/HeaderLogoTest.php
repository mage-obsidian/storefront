<?php
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Storefront\Test\Unit\ViewModel;

use Magento\Framework\UrlInterface;
use MageObsidian\Storefront\ViewModel\HeaderLogo;
use PHPUnit\Framework\TestCase;

class HeaderLogoTest extends TestCase
{
    protected function setUp(): void
    {
        if (!interface_exists(UrlInterface::class)) {
            $this->markTestSkipped('The Magento framework is not available in this runtime.');
        }
    }

    private function subject(): HeaderLogo
    {
        $url = $this->createStub(UrlInterface::class);
        $url->method('getBaseUrl')->willReturn('https://shop.test/media/');

        return new HeaderLogo($url);
    }

    public function testALogoUploadedInTheAdminIsConfigured(): void
    {
        $this->assertTrue($this->subject()->isConfigured('https://shop.test/media/logo/stores/1/mark.png'));
    }

    public function testTheLogoBundledWithThePlatformIsNotConfigured(): void
    {
        $this->assertFalse($this->subject()->isConfigured('https://shop.test/static/frontend/Vendor/theme/en_US/images/logo.svg'));
    }

    public function testAMediaFileOutsideTheLogoFolderIsNotConfigured(): void
    {
        $this->assertFalse($this->subject()->isConfigured('https://shop.test/media/catalog/product/logo.png'));
    }

    public function testNoSourceIsNotConfigured(): void
    {
        $this->assertFalse($this->subject()->isConfigured(''));
    }
}
