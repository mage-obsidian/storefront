<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

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
