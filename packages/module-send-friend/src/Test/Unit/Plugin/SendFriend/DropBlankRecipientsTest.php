<?php
/**
 * This file is part of the MageObsidian - SendFriend project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\SendFriend\Test\Unit\Plugin\SendFriend;

use Magento\SendFriend\Model\SendFriend;
use MageObsidian\SendFriend\Plugin\SendFriend\DropBlankRecipients;
use PHPUnit\Framework\TestCase;

class DropBlankRecipientsTest extends TestCase
{
    private function subject(): SendFriend
    {
        return $this->createStub(SendFriend::class);
    }

    protected function setUp(): void
    {
        if (!class_exists(SendFriend::class)) {
            $this->markTestSkipped('Magento SendFriend is not available in this runtime.');
        }
    }

    public function testDropsTheRowsTheShopperLeftBlank(): void
    {
        $plugin = new DropBlankRecipients();

        [$recipients] = $plugin->beforeSetRecipients($this->subject(), [
            'name' => ['Ada', '', 'Grace', ''],
            'email' => ['ada@example.test', '', 'grace@example.test', '  '],
        ]);

        $this->assertSame(['Ada', 'Grace'], $recipients['name']);
        $this->assertSame(['ada@example.test', 'grace@example.test'], $recipients['email']);
    }

    public function testKeepsARowThatHasAnEmailButNoName(): void
    {
        $plugin = new DropBlankRecipients();

        [$recipients] = $plugin->beforeSetRecipients($this->subject(), [
            'name' => [''],
            'email' => ['ada@example.test'],
        ]);

        $this->assertSame([''], $recipients['name']);
        $this->assertSame(['ada@example.test'], $recipients['email']);
    }

    public function testLeavesAnythingThatIsNotARecipientListUntouched(): void
    {
        $plugin = new DropBlankRecipients();

        $this->assertSame([null], $plugin->beforeSetRecipients($this->subject(), null));
        $this->assertSame([['name' => 'Ada']], $plugin->beforeSetRecipients($this->subject(), ['name' => 'Ada']));
    }

    public function testReturnsAnEmptyListWhenEveryRowIsBlank(): void
    {
        $plugin = new DropBlankRecipients();

        [$recipients] = $plugin->beforeSetRecipients($this->subject(), [
            'name' => ['', ''],
            'email' => ['', ''],
        ]);

        $this->assertSame([], $recipients['email']);
        $this->assertSame([], $recipients['name']);
    }
}
