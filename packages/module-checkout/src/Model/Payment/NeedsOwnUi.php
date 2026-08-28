<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Checkout project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Checkout\Model\Payment;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\View\DesignInterface;
use Magento\Framework\View\File\CollectorInterface;
use Magento\Framework\App\Cache\Type\Layout as LayoutCache;
use Throwable;

class NeedsOwnUi
{
    public const string CACHE_KEY = 'mage_obsidian_payment_needs_own_ui';

    private const string HANDLE = 'checkout_index_index.xml';

    private const string RENDERED_METHODS = '//item[@name="renders"]//item[@name="methods"]/item/@name';

    private ?array $codes = null;

    public function __construct(
        private readonly CollectorInterface $layoutFiles,
        private readonly DesignInterface $design,
        private readonly CacheInterface $cache,
        private readonly SerializerInterface $serializer,
        private readonly array $completableGenerically = []
    ) {
    }

    public function codes(): array
    {
        if ($this->codes !== null) {
            return $this->codes;
        }

        try {
            return $this->codes = $this->exempt($this->cached());
        } catch (Throwable) {
            return $this->codes = [];
        }
    }

    public function declares(string $code): bool
    {
        return in_array($code, $this->codes(), true);
    }

    private function cached(): array
    {
        $theme = $this->design->getDesignTheme();
        $key = self::CACHE_KEY . '_' . (string)$theme->getId();
        $stored = $this->cache->load($key);
        if (is_string($stored) && $stored !== '') {
            $decoded = $this->serializer->unserialize($stored);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $declared = $this->readFromLayout($theme);
        $this->cache->save($this->serializer->serialize($declared), $key, [LayoutCache::CACHE_TAG]);

        return $declared;
    }

    private function readFromLayout(mixed $theme): array
    {
        $codes = [];

        foreach ($this->layoutFiles->getFiles($theme, self::HANDLE) as $file) {
            $xml = @simplexml_load_file($file->getFilename());
            if ($xml === false) {
                continue;
            }
            foreach ($xml->xpath(self::RENDERED_METHODS) ?: [] as $name) {
                $code = trim((string)$name);
                if ($code !== '') {
                    $codes[$code] = true;
                }
            }
        }

        return array_keys($codes);
    }

    private function exempt(array $codes): array
    {
        $generic = array_map('strval', array_values($this->completableGenerically));

        return array_values(array_diff($codes, $generic));
    }
}
