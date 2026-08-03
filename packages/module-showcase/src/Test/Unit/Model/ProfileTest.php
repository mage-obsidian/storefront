<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Showcase project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Showcase\Test\Unit\Model;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Stdlib\Cookie\CookieReaderInterface;
use MageObsidian\Showcase\Model\Config;
use MageObsidian\Showcase\Model\FeaturePool;
use MageObsidian\Showcase\Model\Profile;
use MageObsidian\Showcase\Model\ProfileCodec;
use PHPUnit\Framework\TestCase;

class ProfileTest extends TestCase
{
    private const FEATURES = [
        'listing_fragments' => [
            'path' => 'mage_obsidian/listing/fragments_enabled',
            'label' => 'Fragments',
        ],
        'checkout_layout' => [
            'path' => 'mage_obsidian/checkout/layout_mode',
            'type' => 'choice',
            'options' => ['stepped' => 'Stepped', 'onepage' => 'One page'],
        ],
    ];

    protected function tearDown(): void
    {
        unset($_GET[Profile::REQUEST_PARAMETER]);
    }

    private function profile(string $cookie, bool $enabled = true): Profile
    {
        $deployment = $this->createMock(DeploymentConfig::class);
        $deployment->method('get')->willReturn($enabled);

        $moduleList = $this->createMock(ModuleListInterface::class);
        $moduleList->method('getOne')->willReturn(['name' => 'anything']);

        $cookies = $this->createMock(CookieReaderInterface::class);
        $cookies->method('getCookie')->willReturn($cookie);

        return new Profile(
            new Config($deployment),
            new FeaturePool($moduleList, self::FEATURES),
            new ProfileCodec(),
            $cookies
        );
    }

    public function testReadsTheProfileOffTheCookie(): void
    {
        $profile = $this->profile('listing_fragments=0');

        $this->assertSame(['listing_fragments' => '0'], $profile->selections());
    }

    public function testMapsSelectionsOntoConfigPaths(): void
    {
        $profile = $this->profile('listing_fragments=0~checkout_layout=onepage');

        $this->assertSame([
            'mage_obsidian/checkout/layout_mode' => 'onepage',
            'mage_obsidian/listing/fragments_enabled' => '0',
        ], $profile->overrides());
    }

    // The whole module has to be inert unless the deployment allows it.
    public function testStaysEmptyWhileTheDeploymentHasNotOptedIn(): void
    {
        $profile = $this->profile('listing_fragments=0', enabled: false);

        $this->assertSame([], $profile->selections());
        $this->assertSame([], $profile->overrides());
        $this->assertSame('', $profile->signature());
    }

    public function testASharedLinkWinsOverTheCookie(): void
    {
        $_GET[Profile::REQUEST_PARAMETER] = 'checkout_layout=onepage';
        $profile = $this->profile('listing_fragments=0');

        $this->assertSame(['checkout_layout' => 'onepage'], $profile->selections());
    }

    public function testAnEmptySharedLinkFallsBackToTheCookie(): void
    {
        $_GET[Profile::REQUEST_PARAMETER] = '';
        $profile = $this->profile('listing_fragments=0');

        $this->assertSame(['listing_fragments' => '0'], $profile->selections());
    }

    public function testSignatureIsCanonical(): void
    {
        $one = $this->profile('checkout_layout=onepage~listing_fragments=1')->signature();
        $other = $this->profile('listing_fragments=1~checkout_layout=onepage')->signature();

        $this->assertSame($one, $other);
        $this->assertSame('checkout_layout=onepage~listing_fragments=1', $one);
    }

    public function testKeepsTheFirstStoreValueItWasHanded(): void
    {
        $profile = $this->profile('listing_fragments=0');

        $profile->rememberOriginal('mage_obsidian/listing/fragments_enabled', '1');
        // A later read sees the overridden value; the store's own must survive it.
        $profile->rememberOriginal('mage_obsidian/listing/fragments_enabled', '0');

        $this->assertSame('1', $profile->originalFor('mage_obsidian/listing/fragments_enabled'));
        $this->assertNull($profile->originalFor('never/read/path'));
    }

    public function testAPathNobodyDeclaredNeverReachesTheOverrides(): void
    {
        $profile = $this->profile('payment_free/active=1~listing_fragments=0');

        $this->assertSame(['mage_obsidian/listing/fragments_enabled' => '0'], $profile->overrides());
    }
}
