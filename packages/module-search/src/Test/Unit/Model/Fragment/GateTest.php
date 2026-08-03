<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Search project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Search\Test\Unit\Model\Fragment;

use Magento\Framework\App\Request\Http as HttpRequest;
use MageObsidian\Search\Model\Config;
use MageObsidian\Search\Model\Fragment\Gate;
use MageObsidian\Search\Model\Fragment\RequestFlag;
use MageObsidian\Search\Model\Fragment\SectionPool;
use PHPUnit\Framework\TestCase;

class GateTest extends TestCase
{
    private const string ACTION = 'catalog_category_view';
    private const array SECTIONS = [self::ACTION => ['listing' => 'category.products.list']];

    public function testItOpensForAFlaggedServableListing(): void
    {
        $this->assertTrue($this->gate(true, true, self::ACTION)->isRequested());
    }

    public function testItStaysShutWithoutTheFlag(): void
    {
        $this->assertFalse($this->gate(false, true, self::ACTION)->isRequested());
    }

    public function testItStaysShutWhenTheFeatureIsDisabled(): void
    {
        $this->assertFalse($this->gate(true, false, self::ACTION)->isRequested());
    }

    public function testItStaysShutOnAPageWithNoConfiguredSections(): void
    {
        $this->assertFalse($this->gate(true, true, 'cms_index_index')->isRequested());
    }

    /**
     * The parameter has to come off the request even on pages that will never be
     * served as fragments, or it leaks into every link they generate.
     */
    public function testTheFlagIsConsumedEvenWhenTheGateStaysShut(): void
    {
        $flag = $this->createMock(RequestFlag::class);
        $flag->expects($this->once())->method('consume')->willReturn(true);
        $config = $this->createMock(Config::class);
        $config->method('areFragmentsEnabled')->willReturn(false);

        (new Gate($flag, $config, new SectionPool(self::SECTIONS), $this->request(self::ACTION)))->isRequested();
    }

    private function gate(bool $flagged, bool $enabled, string $actionName): Gate
    {
        $flag = $this->createMock(RequestFlag::class);
        $flag->method('consume')->willReturn($flagged);
        $config = $this->createMock(Config::class);
        $config->method('areFragmentsEnabled')->willReturn($enabled);

        return new Gate($flag, $config, new SectionPool(self::SECTIONS), $this->request($actionName));
    }

    private function request(string $actionName): HttpRequest
    {
        $request = $this->getMockBuilder(HttpRequest::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getFullActionName'])
            ->getMock();
        $request->method('getFullActionName')->willReturn($actionName);

        return $request;
    }
}
