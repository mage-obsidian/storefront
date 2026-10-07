<?php
/**
 * This file is part of the MageObsidian - Customer project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Customer\Test\Unit\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use MageObsidian\Customer\ViewModel\PasswordPolicy;
use PHPUnit\Framework\TestCase;

class PasswordPolicyTest extends TestCase
{
    protected function setUp(): void
    {
        if (!interface_exists(ScopeConfigInterface::class)) {
            $this->markTestSkipped('Magento framework is not available in this runtime.');
        }
    }

    private function policy(array $values): PasswordPolicy
    {
        $config = $this->createMock(ScopeConfigInterface::class);
        $config->method('getValue')->willReturnCallback(static fn (string $path) => $values[$path] ?? null);

        return new PasswordPolicy($config);
    }

    public function testItReadsTheConfiguredMinimumLengthAndClasses(): void
    {
        $policy = $this->policy([
            'customer/password/minimum_password_length' => '12',
            'customer/password/required_character_classes_number' => '3',
        ]);

        $this->assertSame(12, $policy->minLength());
        $this->assertSame(3, $policy->requiredClasses());
    }

    public function testItFallsBackToTheCoreDefaultsWhenNothingIsConfigured(): void
    {
        $policy = $this->policy([]);

        $this->assertSame(8, $policy->minLength());
        $this->assertSame(3, $policy->requiredClasses());
    }

    public function testTheClassesAreClampedToTheFourTheCoreKnows(): void
    {
        $this->assertSame(4, $this->policy(['customer/password/required_character_classes_number' => '9'])->requiredClasses());
        $this->assertSame(1, $this->policy(['customer/password/required_character_classes_number' => '0'])->requiredClasses());
    }

    public function testAMinimumBelowOneFallsBackToTheDefault(): void
    {
        $this->assertSame(8, $this->policy(['customer/password/minimum_password_length' => '0'])->minLength());
    }
}
