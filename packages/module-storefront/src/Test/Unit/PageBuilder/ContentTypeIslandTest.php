<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Storefront\Test\Unit\PageBuilder;

use MageObsidian\ModernFrontend\ViewModel\ViteResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use SimpleXMLElement;

class ContentTypeIslandTest extends TestCase
{
    private const string GENERATED = '/generated/';

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function islandTypes(): array
    {
        return [
            'map' => ['obsidian_map', 'MageObsidian_Storefront::pagebuilder/ObsidianMap'],
            'banner' => ['obsidian_banner', 'MageObsidian_Storefront::pagebuilder/ObsidianBanner'],
        ];
    }

    /**
     * The saved marker names a component by the path the build emits it at, so a
     * renamed component has to be renamed in both places or this fails.
     */
    #[DataProvider('islandTypes')]
    public function testTheMarkerNamesExactlyWhatTheBuildEmits(string $type, string $component): void
    {
        $resolver = (new ReflectionClass(ViteResolver::class))->newInstanceWithoutConstructor();

        $this->assertSame(
            self::GENERATED . $resolver->getComponentFile($component),
            $this->componentField($type)
        );
    }

    #[DataProvider('islandTypes')]
    public function testTheAuthorIsNeverOfferedAFieldToChooseTheComponent(string $type, string $component): void
    {
        $fields = $this->form($type)->xpath('//field[@name="component"]');

        $this->assertCount(1, $fields);
        $this->assertNotSame('', $component);
        $this->assertSame('hidden', (string)$fields[0]['formElement']);
        $this->assertSame('false', (string)$fields[0]->settings->visible);
    }

    #[DataProvider('islandTypes')]
    public function testTheComponentIsOneTheModuleDeclaresPlaceable(string $type, string $component): void
    {
        $di = file_get_contents(__DIR__ . '/../../../etc/di.xml');

        $this->assertStringContainsString(
            '<item name="component" xsi:type="string">' . $component . '</item>',
            (string)$di,
            sprintf('%s names a component that IslandRegistry does not declare', $type)
        );
    }

    #[DataProvider('islandTypes')]
    public function testTheContentTypeDeclaresTheMarkerAttributesTheStorefrontLooksFor(string $type, string $component): void
    {
        $declared = [];
        foreach ($this->contentType($type)->xpath('//element[@name="main"]/attribute') as $attribute) {
            $declared[] = (string)$attribute['source'];
        }

        foreach (['data-mage-island', 'data-component', 'data-props', 'data-strategy'] as $source) {
            $this->assertContains($source, $declared);
        }
    }

    private function componentField(string $type): string
    {
        $fields = $this->form($type)->xpath('//field[@name="component"]//item[@name="default"]');

        return (string)$fields[0];
    }

    private function form(string $type): SimpleXMLElement
    {
        return $this->read(
            sprintf('%s/../../../view/adminhtml/ui_component/pagebuilder_%s_form.xml', __DIR__, $type)
        );
    }

    private function contentType(string $type): SimpleXMLElement
    {
        return $this->read(
            sprintf('%s/../../../view/adminhtml/pagebuilder/content_type/%s.xml', __DIR__, $type)
        );
    }

    private function read(string $path): SimpleXMLElement
    {
        $this->assertFileExists($path);
        $xml = simplexml_load_file($path);
        $this->assertInstanceOf(SimpleXMLElement::class, $xml);

        return $xml;
    }
}
