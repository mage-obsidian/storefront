<?php
declare(strict_types=1);

namespace MageObsidian\Persistent\Test\Unit\ViewModel;

use Magento\Persistent\Helper\Data as PersistentHelper;
use MageObsidian\Persistent\ViewModel\Remember;
use PHPUnit\Framework\TestCase;

/**
 * Remember-me VM for the sign-in / registration forms. We assert the enable gate
 * requires both persistent and remember-me to be on, and the default-checked
 * state mirrors the helper.
 */
class RememberTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(PersistentHelper::class)) {
            $this->markTestSkipped('Magento Persistent is not available in this runtime.');
        }
    }

    private function helper(bool $enabled, bool $rememberEnabled, bool $checkedDefault): PersistentHelper
    {
        $helper = $this->createMock(PersistentHelper::class);
        $helper->method('isEnabled')->willReturn($enabled);
        $helper->method('isRememberMeEnabled')->willReturn($rememberEnabled);
        $helper->method('isRememberMeCheckedDefault')->willReturn($checkedDefault);

        return $helper;
    }

    public function testEnabledRequiresBothFlags(): void
    {
        $this->assertTrue((new Remember($this->helper(true, true, false)))->isEnabled());
        $this->assertFalse((new Remember($this->helper(true, false, false)))->isEnabled());
        $this->assertFalse((new Remember($this->helper(false, true, false)))->isEnabled());
    }

    public function testCheckedByDefaultMirrorsTheHelper(): void
    {
        $this->assertTrue((new Remember($this->helper(true, true, true)))->isCheckedByDefault());
        $this->assertFalse((new Remember($this->helper(true, true, false)))->isCheckedByDefault());
    }
}
