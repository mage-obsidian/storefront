<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Showcase project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Showcase\Plugin\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use MageObsidian\Showcase\Model\Profile;

/**
 * One plugin covers every switchable feature, because every MageObsidian flag is
 * already read through scope config — including the ones a layout gates with
 * `ifconfig`, which resolves through `isSetFlag` and so through here too.
 *
 * This sits on one of the hottest methods in the application (thousands of calls
 * per request), so the body is an array lookup against a map the profile builds
 * once.
 */
class ApplyShowcaseOverrides
{
    public function __construct(private readonly Profile $profile)
    {
    }

    /**
     * @param mixed $result
     * @param string|null $path
     * @param string $scope
     * @param string|int|null $scopeCode
     * @return mixed
     */
    public function afterGetValue(
        ScopeConfigInterface $subject,
        $result,
        $path = null,
        $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
        $scopeCode = null
    ) {
        if (!is_string($path)) {
            return $result;
        }

        $override = $this->profile->overrides()[$path] ?? null;
        if ($override === null) {
            return $result;
        }
        $this->profile->rememberOriginal($path, $result);

        return $override;
    }
}
