<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Showcase project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Showcase\Test\Unit\Plugin;

use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Http\Context as HttpContext;
use MageObsidian\Showcase\Model\Profile;
use MageObsidian\Showcase\Plugin\App\AddShowcaseVary;
use MageObsidian\Showcase\Plugin\Config\ApplyShowcaseOverrides;
use PHPUnit\Framework\TestCase;

class ApplyShowcaseOverridesTest extends TestCase
{
    private function plugin(array $overrides): ApplyShowcaseOverrides
    {
        $profile = $this->createMock(Profile::class);
        $profile->method('overrides')->willReturn($overrides);

        return new ApplyShowcaseOverrides($profile);
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

        (new ApplyShowcaseOverrides($profile))
            ->afterGetValue($this->createMock(ScopeConfigInterface::class), '1', 'a/b/c');
    }

    public function testRemembersNothingForAPathItDidNotTouch(): void
    {
        $profile = $this->createMock(Profile::class);
        $profile->method('overrides')->willReturn(['a/b/c' => '0']);
        $profile->expects($this->never())->method('rememberOriginal');

        (new ApplyShowcaseOverrides($profile))
            ->afterGetValue($this->createMock(ScopeConfigInterface::class), 'live', 'payment/checkmo/active');
    }
}

class AddShowcaseVaryTest extends TestCase
{
    public function testPutsTheProfileInTheVary(): void
    {
        $profile = $this->createMock(Profile::class);
        $profile->method('signature')->willReturn('listing_fragments=0');

        $context = $this->createMock(HttpContext::class);
        $context->expects($this->once())
            ->method('setValue')
            ->with(AddShowcaseVary::CONTEXT_KEY, 'listing_fragments=0', AddShowcaseVary::DEFAULT_SIGNATURE);

        (new AddShowcaseVary($context, $profile))->beforeExecute($this->createMock(ActionInterface::class));
    }

    // The default has to match what an untouched visitor produces, or every page
    // gains a vary dimension nobody asked for.
    public function testAnUntouchedVisitorMatchesTheDefault(): void
    {
        $profile = $this->createMock(Profile::class);
        $profile->method('signature')->willReturn('');

        $this->assertSame(AddShowcaseVary::DEFAULT_SIGNATURE, $profile->signature());
    }
}
