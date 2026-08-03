<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Search project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Search\Model\Fragment;

use MageObsidian\Search\Exception\FragmentUnavailableException;

/**
 * Which layout blocks make up the servable fragments of a listing page, keyed by
 * full action name. Declared in di.xml so a module that adds a region to a
 * listing adds it here too, instead of this shipping a hardcoded pair.
 */
class SectionPool
{
    /**
     * @param array<string, array<string, string>> $sections action name => section key => block name
     */
    public function __construct(private readonly array $sections = [])
    {
    }

    public function isServable(string $actionName): bool
    {
        return $this->configured($actionName) !== [];
    }

    /**
     * @return array<string, string> section key => block name
     * @throws FragmentUnavailableException
     */
    public function get(string $actionName): array
    {
        $sections = $this->configured($actionName);
        if ($sections === []) {
            throw new FragmentUnavailableException(
                __('No listing fragment sections are configured for "%1".', $actionName)
            );
        }

        return $sections;
    }

    /**
     * @return array<string, string>
     */
    private function configured(string $actionName): array
    {
        // A section is switched off by overriding its block name with an empty
        // string, the only way di.xml array merging lets you unset an item.
        return array_filter($this->sections[$actionName] ?? []);
    }
}
