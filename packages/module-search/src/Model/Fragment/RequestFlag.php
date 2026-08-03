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

/**
 * Reads the fragment flag off the request and takes it back out.
 *
 * Removing it is not housekeeping. Layered-navigation items, the toolbar and the
 * pager all build their links with `_current => true`, which copies the live
 * query string into every URL they emit — leaving the flag in place would stamp
 * it onto every filter, sort and pager link inside the fragment, and `uenc`
 * (built from the raw request URI) would send add-to-cart back to a data URL.
 * So the query container and the request URI are both rewritten before the page
 * renders anything.
 */
class RequestFlag
{
    private const string QUERY_SEPARATOR = '?';
    private const string PAIR_SEPARATOR = '&';
    private const string VALUE_SEPARATOR = '=';

    private ?bool $requested = null;

    public function __construct(private readonly HttpRequest $request)
    {
    }

    public function consume(): bool
    {
        if ($this->requested !== null) {
            return $this->requested;
        }

        $name = RequestParameter::Fragment->value;
        $query = $this->request->getQuery();
        $this->requested = ((string)($query[$name] ?? '')) === RequestParameter::VALUE_ON;

        if ($this->requested) {
            unset($query[$name]);
            $this->request->setRequestUri($this->stripFromUri((string)$this->request->getRequestUri()));
        }

        return $this->requested;
    }

    /**
     * Drops the flag from a raw URI without decoding the rest of it: a
     * parse_str/http_build_query round trip would rewrite every other
     * parameter's encoding, and `uenc` is a byte-for-byte copy of this string.
     */
    private function stripFromUri(string $uri): string
    {
        $at = strpos($uri, self::QUERY_SEPARATOR);
        if ($at === false) {
            return $uri;
        }

        $path = substr($uri, 0, $at);
        $name = RequestParameter::Fragment->value;
        $kept = array_filter(
            explode(self::PAIR_SEPARATOR, substr($uri, $at + 1)),
            static fn (string $pair): bool => $pair !== ''
                && $pair !== $name
                && !str_starts_with($pair, $name . self::VALUE_SEPARATOR)
        );

        return $kept === []
            ? $path
            : $path . self::QUERY_SEPARATOR . implode(self::PAIR_SEPARATOR, $kept);
    }
}
