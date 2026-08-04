<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Showcase project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Showcase\Test\Unit\Plugin;

use Magento\Framework\App\FrontControllerInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use MageObsidian\Showcase\Model\Telemetry\Attributes;
use MageObsidian\Showcase\Model\Telemetry\RecorderInterface;
use MageObsidian\Showcase\Plugin\App\ReportShowcaseAttributes;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ReportShowcaseAttributesTest extends TestCase
{
    public function testRecordsEveryAttributeItCollected(): void
    {
        $recorder = $this->recorder();
        $plugin = $this->plugin(['obsidian.store' => 'classic', 'obsidian.fragment' => '1'], $recorder);

        $plugin->afterDispatch($this->frontController(), $this->response());

        $this->assertSame(
            ['obsidian.store' => 'classic', 'obsidian.fragment' => '1'],
            $recorder->recorded
        );
    }

    public function testHandsTheResponseBackUntouched(): void
    {
        $response = $this->response();
        $plugin = $this->plugin(['obsidian.store' => 'default'], $this->recorder());

        $this->assertSame($response, $plugin->afterDispatch($this->frontController(), $response));
    }

    /**
     * FrontController::dispatch is documented as returning ResponseInterface but
     * hands back a ResultInterface for every page a controller renders, which is
     * almost all of them. A signature that takes only the former is a TypeError,
     * and on a plugin over the front controller that is a 500 on the storefront.
     */
    public function testHandsBackAResultTheSameWayAsAResponse(): void
    {
        $result = $this->createMock(ResultInterface::class);
        $plugin = $this->plugin(['obsidian.store' => 'default'], $this->recorder());

        $this->assertSame($result, $plugin->afterDispatch($this->frontController(), $result));
    }

    /**
     * A measurement has to arrive as a number. New Relic types an attribute from
     * the value it receives, and on a string one `average()` returns null unless
     * every single query remembers to wrap it in `numeric()`.
     */
    public function testKeepsNumbersAsNumbers(): void
    {
        $recorder = $this->recorder();
        $plugin = $this->plugin(['obsidian.autoload_ms' => 12.5, 'obsidian.classes' => 1840], $recorder);

        $plugin->afterDispatch($this->frontController(), $this->response());

        $this->assertSame(
            ['obsidian.autoload_ms' => 12.5, 'obsidian.classes' => 1840],
            $recorder->recorded
        );
    }

    public function testRecordsNothingWhenTheShowcaseCollectedNothing(): void
    {
        $recorder = $this->recorder();
        $plugin = $this->plugin([], $recorder);

        $plugin->afterDispatch($this->frontController(), $this->response());

        $this->assertSame([], $recorder->recorded);
    }

    /**
     * Reporting runs on every dispatched request and reaches the store and the
     * config; measuring the demo must never be what takes it down.
     */
    public function testServesThePageEvenWhenCollectingBlowsUp(): void
    {
        $attributes = $this->createMock(Attributes::class);
        $attributes->method('collect')->willThrowException(new RuntimeException('no store'));
        $response = $this->response();

        $plugin = new ReportShowcaseAttributes($attributes, $this->recorder());

        $this->assertSame($response, $plugin->afterDispatch($this->frontController(), $response));
    }

    /**
     * @param array<string, string|int|float> $collected
     */
    private function plugin(array $collected, RecorderInterface $recorder): ReportShowcaseAttributes
    {
        $attributes = $this->createMock(Attributes::class);
        $attributes->method('collect')->willReturn($collected);

        return new ReportShowcaseAttributes($attributes, $recorder);
    }

    private function recorder(): RecorderInterface
    {
        return new class implements RecorderInterface {
            /** @var array<string, string|int|float> */
            public array $recorded = [];

            public function record(string $name, string|int|float $value): void
            {
                $this->recorded[$name] = $value;
            }
        };
    }

    private function frontController(): FrontControllerInterface
    {
        return $this->createMock(FrontControllerInterface::class);
    }

    private function response(): ResponseInterface
    {
        return $this->createMock(ResponseInterface::class);
    }
}
