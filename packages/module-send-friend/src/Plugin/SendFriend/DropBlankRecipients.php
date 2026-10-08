<?php
/**
 * This file is part of the MageObsidian - SendFriend project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\SendFriend\Plugin\SendFriend;

use Magento\SendFriend\Model\SendFriend;

class DropBlankRecipients
{
    /**
     * @param SendFriend $subject
     * @param mixed $recipients
     * @return array<int, mixed>
     */
    public function beforeSetRecipients(SendFriend $subject, mixed $recipients): array
    {
        if (!is_array($recipients) || !isset($recipients['email'], $recipients['name'])
            || !is_array($recipients['email']) || !is_array($recipients['name'])
        ) {
            return [$recipients];
        }

        $emails = [];
        $names = [];
        foreach ($recipients['email'] as $key => $email) {
            if (trim((string)$email) === '') {
                continue;
            }
            $emails[] = $email;
            $names[] = $recipients['name'][$key] ?? '';
        }

        return [['email' => $emails, 'name' => $names]];
    }
}
