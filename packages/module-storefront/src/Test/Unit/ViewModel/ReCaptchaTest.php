<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Storefront\Test\Unit\ViewModel;

use Magento\Framework\Exception\InputException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\ReCaptchaUi\Model\IsCaptchaEnabledInterface;
use Magento\ReCaptchaUi\Model\UiConfigResolverInterface;
use MageObsidian\Storefront\ViewModel\ReCaptcha;
use PHPUnit\Framework\TestCase;

class ReCaptchaTest extends TestCase
{
    public function testSaysNothingAboutAFormTheStorePutNoChallengeOn(): void
    {
        $viewModel = $this->viewModel(enabledFor: []);

        $this->assertFalse($viewModel->isEnabledFor('customer_login'));
        $this->assertSame('', $viewModel->settingsFor('customer_login'));
    }

    public function testCarriesWhatTheBrowserNeedsToRenderTheChallenge(): void
    {
        $viewModel = $this->viewModel(
            enabledFor: ['customer_login'],
            config: [
                'rendering' => ['sitekey' => 'site-key', 'size' => 'compact', 'theme' => 'dark', 'hl' => 'es'],
                'invisible' => false,
            ]
        );

        $this->assertSame(
            [
                'formKey' => 'customer_login',
                'sitekey' => 'site-key',
                'size' => 'compact',
                'theme' => 'dark',
                'badge' => '',
                'lang' => 'es',
                'invisible' => false,
            ],
            json_decode($viewModel->settingsFor('customer_login'), true)
        );
    }

    /**
     * The version a store chose decides what the browser has to do: an invisible
     * challenge has nothing to click and its token has to be asked for.
     */
    public function testCarriesTheInvisibleVersionAsInvisible(): void
    {
        $viewModel = $this->viewModel(
            enabledFor: ['place_order'],
            config: [
                'rendering' => ['sitekey' => 'site-key', 'badge' => 'bottomleft'],
                'invisible' => true,
            ]
        );

        $settings = json_decode($viewModel->settingsFor('place_order'), true);

        $this->assertTrue($settings['invisible']);
        $this->assertSame('bottomleft', $settings['badge']);
    }

    /**
     * A challenge switched on but never given a key would render an element the
     * vendor's API then fails on, which is worse than rendering nothing.
     */
    public function testRendersNothingWhenTheStoreConfiguredNoSiteKey(): void
    {
        $viewModel = $this->viewModel(
            enabledFor: ['customer_login'],
            config: ['rendering' => ['sitekey' => ''], 'invisible' => false]
        );

        $this->assertSame('', $viewModel->settingsFor('customer_login'));
    }

    // The platform throws for a key it has no configuration for; a form is not
    // worth breaking a page over.
    public function testRendersNothingWhenThePlatformRefusesToResolveTheConfiguration(): void
    {
        $resolver = $this->createMock(UiConfigResolverInterface::class);
        $resolver->method('get')->willThrowException(new InputException(__('no such key')));

        $this->assertSame('', $this->viewModel(enabledFor: ['review'], resolver: $resolver)->settingsFor('review'));
    }

    public function testTreatsAnEmptyFormKeyAsNoChallenge(): void
    {
        $this->assertFalse($this->viewModel(enabledFor: ['customer_login'])->isEnabledFor(''));
    }

    // The id is what the markup and the browser half agree on, so it has to be
    // usable as one.
    public function testDerivesAnElementIdFromTheFormKey(): void
    {
        $viewModel = $this->viewModel(enabledFor: []);

        $this->assertSame('recaptcha-customer-forgot-password', $viewModel->idFor('customer_forgot_password'));
    }

    /**
     * @param string[] $enabledFor
     * @param array<string, mixed> $config
     */
    private function viewModel(
        array $enabledFor,
        array $config = ['rendering' => ['sitekey' => 'site-key'], 'invisible' => false],
        ?UiConfigResolverInterface $resolver = null
    ): ReCaptcha {
        $isEnabled = $this->createMock(IsCaptchaEnabledInterface::class);
        $isEnabled->method('isCaptchaEnabledFor')->willReturnCallback(
            static fn (string $key): bool => in_array($key, $enabledFor, true)
        );

        if ($resolver === null) {
            $resolver = $this->createMock(UiConfigResolverInterface::class);
            $resolver->method('get')->willReturn($config);
        }

        $json = $this->createMock(Json::class);
        $json->method('serialize')->willReturnCallback(
            static fn (mixed $value): string => json_encode($value, JSON_THROW_ON_ERROR)
        );

        return new ReCaptcha($resolver, $isEnabled, $json);
    }
}
