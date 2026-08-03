<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Showcase project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Showcase\Model;

use Magento\Framework\Stdlib\Cookie\CookieReaderInterface;

/**
 * What this visitor asked to see, resolved once per request.
 */
class Profile
{
    public const string COOKIE_NAME = 'obsidian_showcase';

    public const string REQUEST_PARAMETER = 'showcase';

    /** @var array<string, string>|null */
    private ?array $selections = null;

    /** @var array<string, string> */
    private array $originals = [];

    private bool $resolving = false;

    public function __construct(
        private readonly Config $config,
        private readonly FeaturePool $pool,
        private readonly ProfileCodec $codec,
        private readonly CookieReaderInterface $cookies
    ) {
    }

    /**
     * @return array<string, string> feature key => value
     */
    public function selections(): array
    {
        if ($this->selections !== null) {
            return $this->selections;
        }
        // Resolving runs underneath a plugin on scope config; anything down this
        // path that reads config would re-enter here mid-flight.
        if ($this->resolving || !$this->config->isEnabled()) {
            return [];
        }

        $this->resolving = true;
        try {
            return $this->selections = $this->codec->decode($this->carried(), $this->pool->available());
        } finally {
            $this->resolving = false;
        }
    }

    /**
     * @return array<string, string> config path => value
     */
    public function overrides(): array
    {
        $overrides = [];
        foreach ($this->selections() as $key => $value) {
            $feature = $this->pool->get($key);
            if ($feature !== null) {
                $overrides[$feature->path] = $value;
            }
        }

        return $overrides;
    }

    /** Canonical form, so two ways of clicking into the same profile cache as one. */
    public function signature(): string
    {
        return $this->codec->encode($this->selections());
    }

    /**
     * What the store view itself says, kept as the override is applied. The
     * panel needs it to tell a visitor's choice apart from the store's own
     * setting, and to leave out of the profile anything that merely repeats it.
     */
    public function rememberOriginal(string $path, mixed $value): void
    {
        $this->originals[$path] ??= (string)$value;
    }

    public function originalFor(string $path): ?string
    {
        return $this->originals[$path] ?? null;
    }

    /**
     * A shared link wins over the cookie so the page renders the shared profile
     * on its first paint; the panel persists it and drops the parameter after.
     *
     * `$_GET` rather than the request object: this is reached from a plugin on
     * scope config, and building the request pulls in services that read config.
     */
    private function carried(): string
    {
        $shared = $_GET[self::REQUEST_PARAMETER] ?? null;
        if (is_string($shared) && $shared !== '') {
            return $shared;
        }

        return (string)$this->cookies->getCookie(self::COOKIE_NAME, '');
    }
}
