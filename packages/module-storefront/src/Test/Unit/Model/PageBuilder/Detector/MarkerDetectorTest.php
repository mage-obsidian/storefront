<?php
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Storefront\Test\Unit\Model\PageBuilder\Detector;

use MageObsidian\Storefront\Model\PageBuilder\Detector\MarkerDetector;
use PHPUnit\Framework\TestCase;

class MarkerDetectorTest extends TestCase
{
    public function testFindsTheContentItWasDeclaredFor(): void
    {
        $detector = new MarkerDetector('data-content-type="tabs"', 'Vendor_Module::js/tabs');

        $this->assertTrue($detector->matches('<div data-content-type="tabs"></div>'));
        $this->assertSame('Vendor_Module::js/tabs', $detector->getModule());
    }

    public function testDoesNotFindContentOfAnotherKind(): void
    {
        $detector = new MarkerDetector('data-content-type="tabs"', 'Vendor_Module::js/tabs');

        $this->assertFalse($detector->matches('<div data-content-type="text"><p>Words</p></div>'));
    }

    public function testMatchesOnTheLiteralMarkerAndNotOnAPrefixOfIt(): void
    {
        $detector = new MarkerDetector('data-content-type="tabs"', 'Vendor_Module::js/tabs');

        $this->assertFalse($detector->matches('<div data-content-type="tab-item"></div>'));
    }

    public function testAnEmptyMarkerMatchesNothing(): void
    {
        $this->assertFalse((new MarkerDetector('', 'Vendor_Module::js/tabs'))->matches('<div></div>'));
    }
}
