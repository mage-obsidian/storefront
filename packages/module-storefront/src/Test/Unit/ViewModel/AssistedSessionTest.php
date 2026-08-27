<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Storefront\Test\Unit\ViewModel;

use Magento\LoginAsCustomerApi\Api\ConfigInterface;
use MageObsidian\Storefront\ViewModel\AssistedSession;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class AssistedSessionTest extends TestCase
{
    public function testFollowsTheStoresOwnFlag(): void
    {
        $this->assertTrue($this->viewModel(true)->isEnabled());
        $this->assertFalse($this->viewModel(false)->isEnabled());
    }

    /**
     * A store that has never switched the feature on should not be answering
     * questions about it, and a module that has been removed should not take the
     * page with it.
     */
    public function testAnswersNoWhenThePlatformCannotSay(): void
    {
        $config = $this->createMock(ConfigInterface::class);
        $config->method('isEnabled')->willThrowException(new RuntimeException('no such module'));

        $this->assertFalse((new AssistedSession($config))->isEnabled());
    }

    private function viewModel(bool $enabled): AssistedSession
    {
        $config = $this->createMock(ConfigInterface::class);
        $config->method('isEnabled')->willReturn($enabled);

        return new AssistedSession($config);
    }
}
