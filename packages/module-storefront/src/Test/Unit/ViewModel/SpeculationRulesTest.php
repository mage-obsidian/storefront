<?php
declare(strict_types=1);

namespace MageObsidian\Storefront\Test\Unit\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use MageObsidian\Storefront\Model\Speculation\Eagerness;
use MageObsidian\Storefront\Model\Speculation\Mode;
use MageObsidian\Storefront\ViewModel\SpeculationRules;
use PHPUnit\Framework\TestCase;

/**
 * The speculation ruleset. We assert the enable gate, that mode and eagerness
 * fall back instead of emitting a value the browser would reject, that every
 * exclusion class lands in the ruleset, and that the logout link — the one GET
 * link that would sign a visitor out — is excluded by default.
 */
class SpeculationRulesTest extends TestCase
{
    private const array DEFAULTS = [
        SpeculationRules::CONFIG_MODE => 'prefetch',
        SpeculationRules::CONFIG_EAGERNESS => 'moderate',
        SpeculationRules::CONFIG_EXCLUDE_PATHS => "customer\ncheckout\nlogout",
        SpeculationRules::CONFIG_EXCLUDE_EXTENSIONS => 'pdf,zip',
        SpeculationRules::CONFIG_EXCLUDE_SELECTORS => '.no-prefetch',
    ];

    private function viewModel(array $overrides = [], bool $enabled = true): SpeculationRules
    {
        $values = [...self::DEFAULTS, ...$overrides];

        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturn($enabled);
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn(string $path): ?string => $values[$path] ?? null
        );

        return new SpeculationRules($scopeConfig);
    }

    private function conditions(SpeculationRules $viewModel): array
    {
        $rules = $viewModel->getRules();

        return $rules[$viewModel->getMode()->value][0]['where']['and'];
    }

    public function testGateMirrorsConfig(): void
    {
        self::assertTrue($this->viewModel()->isEnabled());
        self::assertFalse($this->viewModel(enabled: false)->isEnabled());
    }

    public function testRulesetIsKeyedByModeAndCarriesEagerness(): void
    {
        $viewModel = $this->viewModel([
            SpeculationRules::CONFIG_MODE => 'prerender',
            SpeculationRules::CONFIG_EAGERNESS => 'eager',
        ]);

        $rules = $viewModel->getRules();

        self::assertSame(Mode::Prerender, $viewModel->getMode());
        self::assertArrayHasKey('prerender', $rules);
        self::assertSame('document', $rules['prerender'][0]['source']);
        self::assertSame('eager', $rules['prerender'][0]['eagerness']);
    }

    public function testUnknownModeAndEagernessFallBack(): void
    {
        $viewModel = $this->viewModel([
            SpeculationRules::CONFIG_MODE => 'teleport',
            SpeculationRules::CONFIG_EAGERNESS => 'frantic',
        ]);

        self::assertSame(Mode::Prefetch, $viewModel->getMode());
        self::assertSame(Eagerness::Moderate, $viewModel->getEagerness());
    }

    public function testLogoutIsExcludedByDefault(): void
    {
        $conditions = $this->conditions($this->viewModel());

        self::assertContains(
            ['not' => ['href_matches' => '/*(customer|checkout|logout)/*']],
            $conditions
        );
    }

    public function testMultiSegmentPathsAreDroppedBecauseTheyCannotCompile(): void
    {
        $viewModel = $this->viewModel([
            SpeculationRules::CONFIG_EXCLUDE_PATHS => "customer\ncatalog/product_compare\n/checkout/",
        ]);

        self::assertSame(['customer', 'checkout'], $viewModel->getExcludedPaths());
    }

    public function testExtensionsAndSelectorsBecomeConditions(): void
    {
        $conditions = $this->conditions($this->viewModel());

        self::assertContains(['not' => ['href_matches' => '*.pdf']], $conditions);
        self::assertContains(['not' => ['href_matches' => '*.zip']], $conditions);
        self::assertContains(['not' => ['selector_matches' => '.no-prefetch']], $conditions);
    }

    public function testUnsafeSelectorsAreAlwaysExcluded(): void
    {
        $conditions = $this->conditions($this->viewModel([
            SpeculationRules::CONFIG_EXCLUDE_SELECTORS => '',
        ]));

        foreach (['[rel=nofollow]', '[target=_blank]', '[target=_parent]', '[target=_top]'] as $selector) {
            self::assertContains(['not' => ['selector_matches' => $selector]], $conditions);
        }
    }

    public function testEmptyExclusionsStillProduceAValidRuleset(): void
    {
        $viewModel = $this->viewModel([
            SpeculationRules::CONFIG_EXCLUDE_PATHS => '',
            SpeculationRules::CONFIG_EXCLUDE_EXTENSIONS => '',
            SpeculationRules::CONFIG_EXCLUDE_SELECTORS => '',
        ]);

        $conditions = $this->conditions($viewModel);

        self::assertSame(['href_matches' => '/*'], $conditions[0]);
        self::assertCount(5, $conditions);
    }

    public function testJsonKeepsSlashesReadable(): void
    {
        $json = $this->viewModel()->getRulesJson();

        self::assertStringContainsString('"href_matches":"/*"', $json);
        self::assertStringNotContainsString('\\/', $json);
        self::assertIsArray(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
    }
}
