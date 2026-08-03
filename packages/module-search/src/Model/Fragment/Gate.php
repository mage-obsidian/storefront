<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Search project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Search\Model\Fragment;

use Magento\Framework\App\Request\Http as HttpRequest;
use MageObsidian\Search\Model\Config;

/**
 * The single answer to "is this request being served as fragments?".
 *
 * Reading the flag is deliberately the first thing evaluated: it also strips the
 * parameter, and that has to happen whether or not the fragment path is taken.
 */
class Gate
{
    public function __construct(
        private readonly RequestFlag $requestFlag,
        private readonly Config $config,
        private readonly SectionPool $sectionPool,
        private readonly HttpRequest $request
    ) {
    }

    public function isRequested(): bool
    {
        return $this->requestFlag->consume()
            && $this->config->areFragmentsEnabled()
            && $this->sectionPool->isServable($this->request->getFullActionName());
    }
}
