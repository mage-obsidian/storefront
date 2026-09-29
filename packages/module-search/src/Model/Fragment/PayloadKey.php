<?php
/**
 * This file is part of the MageObsidian - Search project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Search\Model\Fragment;

/**
 * The wire format between the fragment endpoint and the listing navigator.
 */
enum PayloadKey: string
{
    case Sections = 'sections';
    case Error = 'error';
}
