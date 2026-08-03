<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Showcase project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Showcase\Model;

/**
 * Reads and writes the profile a visitor carries.
 *
 * The wire form stays readable (`listing_fragments=0~checkout_layout=onepage`)
 * because it ends up in a cookie, a shared URL and a support conversation.
 * Decoding is where the allowlist is enforced: an unknown key or a value the
 * feature does not accept is dropped, never passed through.
 */
class ProfileCodec
{
    private const PAIR_SEPARATOR = '~';

    private const VALUE_SEPARATOR = '=';

    /** Bounded so a hand-written cookie cannot make the parser do real work. */
    private const MAX_PAIRS = 32;

    /**
     * @param array<string, Feature> $features
     * @return array<string, string> key => value, canonically ordered
     */
    public function decode(string $raw, array $features): array
    {
        if ($raw === '') {
            return [];
        }

        $selections = [];
        foreach (array_slice(explode(self::PAIR_SEPARATOR, $raw), 0, self::MAX_PAIRS) as $pair) {
            if (!str_contains($pair, self::VALUE_SEPARATOR)) {
                continue;
            }
            [$key, $value] = explode(self::VALUE_SEPARATOR, $pair, 2);
            $feature = $features[$key] ?? null;
            if ($feature !== null && $feature->accepts($value)) {
                $selections[$key] = $value;
            }
        }

        ksort($selections);

        return $selections;
    }

    /**
     * @param array<string, string> $selections
     */
    public function encode(array $selections): string
    {
        ksort($selections);

        $pairs = [];
        foreach ($selections as $key => $value) {
            $pairs[] = $key . self::VALUE_SEPARATOR . $value;
        }

        return implode(self::PAIR_SEPARATOR, $pairs);
    }
}
