<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Showcase project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Showcase\Test\Unit\Model;

use MageObsidian\Showcase\Model\Feature;
use MageObsidian\Showcase\Model\FeatureType;
use MageObsidian\Showcase\Model\ProfileCodec;
use PHPUnit\Framework\TestCase;

class ProfileCodecTest extends TestCase
{
    private ProfileCodec $codec;

    /** @var array<string, Feature> */
    private array $features;

    protected function setUp(): void
    {
        $this->codec = new ProfileCodec();
        $this->features = [
            'listing_fragments' => new Feature(
                key: 'listing_fragments',
                path: 'mage_obsidian/listing/fragments_enabled',
                label: 'Fragments',
                type: FeatureType::Flag
            ),
            'checkout_layout' => new Feature(
                key: 'checkout_layout',
                path: 'mage_obsidian/checkout/layout_mode',
                label: 'Checkout',
                type: FeatureType::Choice,
                options: ['stepped' => 'Stepped', 'onepage' => 'One page']
            ),
        ];
    }

    public function testDecodesDeclaredPairs(): void
    {
        $decoded = $this->codec->decode('listing_fragments=0~checkout_layout=onepage', $this->features);

        $this->assertSame(['checkout_layout' => 'onepage', 'listing_fragments' => '0'], $decoded);
    }

    public function testOrdersKeysSoOneProfileIsOneCacheEntry(): void
    {
        $one = $this->codec->decode('listing_fragments=0~checkout_layout=onepage', $this->features);
        $other = $this->codec->decode('checkout_layout=onepage~listing_fragments=0', $this->features);

        $this->assertSame($this->codec->encode($one), $this->codec->encode($other));
    }

    public function testDropsAKeyNobodyDeclared(): void
    {
        $decoded = $this->codec->decode('payment_active=1~listing_fragments=0', $this->features);

        $this->assertSame(['listing_fragments' => '0'], $decoded);
    }

    public function testDropsAValueTheFeatureDoesNotAccept(): void
    {
        $decoded = $this->codec->decode('listing_fragments=maybe~checkout_layout=elsewhere', $this->features);

        $this->assertSame([], $decoded);
    }

    public function testIgnoresMalformedPairs(): void
    {
        $decoded = $this->codec->decode('~~listing_fragments~=1~listing_fragments=1', $this->features);

        $this->assertSame(['listing_fragments' => '1'], $decoded);
    }

    public function testCapsHowMuchAHandWrittenCookieCanAskFor(): void
    {
        $raw = implode('~', array_fill(0, 200, 'unknown=1')) . '~listing_fragments=1';

        $this->assertSame([], $this->codec->decode($raw, $this->features));
    }

    public function testEmptyProfileRoundTripsToNothing(): void
    {
        $this->assertSame([], $this->codec->decode('', $this->features));
        $this->assertSame('', $this->codec->encode([]));
    }

    public function testEncodesCanonically(): void
    {
        $encoded = $this->codec->encode(['listing_fragments' => '0', 'checkout_layout' => 'onepage']);

        $this->assertSame('checkout_layout=onepage~listing_fragments=0', $encoded);
    }
}
