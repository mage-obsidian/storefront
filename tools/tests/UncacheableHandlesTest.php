<?php
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace MageObsidian\Monorepo\Test;

use MageObsidian\Monorepo\UncacheableHandles;
use PHPUnit\Framework\TestCase;

final class UncacheableHandlesTest extends TestCase
{
    private const string MARKED = '<block class="Magento\Framework\View\Element\Text" name="private" cacheable="false"/>';

    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/uncacheable-handles-' . bin2hex(random_bytes(4));
        mkdir($this->root, 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function testAHandleCoreMarksAndTheStorefrontShipsUnmarkedIsReported(): void
    {
        $this->storefront('module-send-friend', 'sendfriend_product_send', '<block class="Send" name="send"/>');

        self::assertSame(
            ['sendfriend_product_send is uncacheable in core, but the storefront implements it without cacheable="false"'],
            $this->violations(['sendfriend_product_send']),
        );
    }

    public function testAMarkedHandlePasses(): void
    {
        $this->storefront('module-send-friend', 'sendfriend_product_send', self::MARKED);

        self::assertSame([], $this->violations(['sendfriend_product_send']));
    }

    public function testAMarkInAnyPackageThatShipsTheHandleIsEnough(): void
    {
        $this->storefront('module-checkout', 'checkout_cart_index', '<block class="Cart" name="cart"/>');
        $this->storefront('module-vault', 'checkout_cart_index', self::MARKED);

        self::assertSame([], $this->violations(['checkout_cart_index']));
    }

    public function testAMarkReachedThroughAnUpdateHandleIsEnough(): void
    {
        $this->storefront('module-sales', 'sales_order_view', '<update handle="sales_order_info_links"/>');
        $this->storefront('module-sales', 'sales_order_info_links', '<update handle="sales_order_item_renderers"/>');
        $this->storefront('module-sales', 'sales_order_item_renderers', self::MARKED);

        self::assertSame([], $this->violations(['sales_order_view']));
    }

    public function testAnUpdateCycleEndsUnmarked(): void
    {
        $this->storefront('module-sales', 'sales_order_view', '<update handle="sales_order_print"/>');
        $this->storefront('module-sales', 'sales_order_print', '<update handle="sales_order_view"/>');

        self::assertSame(
            ['sales_order_view is uncacheable in core, but the storefront implements it without cacheable="false"'],
            $this->violations(['sales_order_view']),
        );
    }

    public function testAnUpdateToAHandleOnlyCoreDefinesCountsAsUnmarked(): void
    {
        $this->storefront('module-customer', 'customer_account_edit', '<update handle="customer_account"/>');

        self::assertSame(
            ['customer_account_edit is uncacheable in core, but the storefront implements it without cacheable="false"'],
            $this->violations(['customer_account_edit', 'customer_account']),
        );
    }

    public function testAReferenceBlockCarryingTheAttributeIsNotAMark(): void
    {
        $this->storefront('module-send-friend', 'sendfriend_product_send', '<referenceBlock name="send" cacheable="false"/>');

        self::assertSame(
            ['sendfriend_product_send is uncacheable in core, but the storefront implements it without cacheable="false"'],
            $this->violations(['sendfriend_product_send']),
        );
    }

    public function testANestedMarkedBlockCounts(): void
    {
        $this->storefront(
            'module-wishlist',
            'wishlist_index_index',
            "<referenceContainer name=\"content\">\n<block class=\"Wishlist\"\n       name=\"wishlist\">\n<block class=\"Items\" name=\"items\"\n       cacheable=\"false\"/>\n</block>\n</referenceContainer>",
        );

        self::assertSame([], $this->violations(['wishlist_index_index']));
    }

    public function testThemeBaseAndBaseAreaLayoutsCount(): void
    {
        $this->write('packages/theme-base/Magento_Customer/layout/customer_account_index.xml', $this->layout(self::MARKED));
        $this->write('packages/module-sales/src/view/base/layout/sales_order_print.xml', $this->layout(self::MARKED));

        self::assertSame([], $this->violations(['customer_account_index', 'sales_order_print']));
    }

    public function testAHandleTheStorefrontDoesNotShipIsNotRequired(): void
    {
        self::assertSame([], $this->violations(['paypal_billing_agreement_index']));
    }

    public function testADeliberateExceptionIsAccepted(): void
    {
        $this->storefront('module-checkout', 'checkout_index_index', '<block class="Shell" name="shell"/>');

        self::assertSame(
            [],
            $this->violations(['checkout_index_index'], ['checkout_index_index' => 'the shell is cacheable on purpose']),
        );
    }

    public function testAnExceptionTheStorefrontAlreadyMarksIsReported(): void
    {
        $this->storefront('module-checkout', 'checkout_index_index', self::MARKED);

        self::assertSame(
            ['checkout_index_index is listed as an exception, but the storefront already marks it'],
            $this->violations(['checkout_index_index'], ['checkout_index_index' => 'the shell is cacheable on purpose']),
        );
    }

    public function testAnExceptionTheStorefrontNoLongerShipsIsReported(): void
    {
        self::assertSame(
            ['checkout_index_index is listed as an exception, but the storefront no longer implements it'],
            $this->violations(['checkout_index_index'], ['checkout_index_index' => 'the shell is cacheable on purpose']),
        );
    }

    public function testAnExceptionCoreNoLongerMarksIsReported(): void
    {
        $this->storefront('module-checkout', 'checkout_index_index', '<block class="Shell" name="shell"/>');

        self::assertSame(
            ['checkout_index_index is listed as an exception, but core no longer marks it'],
            $this->violations([], ['checkout_index_index' => 'the shell is cacheable on purpose']),
        );
    }

    public function testARequiredPrivateHandleWithoutTheMarkIsReported(): void
    {
        $this->storefront('module-checkout', 'checkout_cart_configure', '<update handle="catalog_product_view"/>');

        self::assertSame(
            ['checkout_cart_configure must be uncacheable (it shows the owner\'s item), but the storefront does not mark it'],
            $this->violations([], [], ['checkout_cart_configure' => 'it shows the owner\'s item']),
        );
    }

    public function testARequiredPrivateHandleTheStorefrontDropsIsReported(): void
    {
        self::assertSame(
            ['checkout_cart_configure must be uncacheable (it shows the owner\'s item), but the storefront does not mark it'],
            $this->violations([], [], ['checkout_cart_configure' => 'it shows the owner\'s item']),
        );
    }

    public function testARequiredPrivateHandleWithTheMarkPasses(): void
    {
        $this->storefront('module-checkout', 'checkout_cart_configure', self::MARKED);

        self::assertSame([], $this->violations([], [], ['checkout_cart_configure' => 'it shows the owner\'s item']));
    }

    public function testCoreListsTheHandlesWithAnUncacheableBlockAtAnyDepth(): void
    {
        $this->write('vendor/magento/module-wishlist/view/frontend/layout/wishlist_index_configure_type_bundle.xml', $this->layout(
            "<referenceBlock name=\"product.info\">\n<block class=\"Bundle\" name=\"bundle\">\n<block class=\"Options\"\n       name=\"options\" cacheable=\"false\"/>\n</block>\n</referenceBlock>",
        ));
        $this->write('vendor/magento/module-sales/view/base/layout/sales_order_print.xml', $this->layout(self::MARKED));
        $this->write('vendor/magento/module-catalog/view/frontend/layout/catalog_product_view.xml', $this->layout('<block class="Product" name="product"/>'));
        $this->write('vendor/magento/module-catalog/view/frontend/layout/catalog_category_view.xml', $this->layout('<referenceBlock name="list" cacheable="false"/>'));

        self::assertSame(
            ['sales_order_print', 'wishlist_index_configure_type_bundle'],
            UncacheableHandles::core($this->root)['handles'],
        );
    }

    public function testCoreListsAHandleMarkedByTwoModulesOnce(): void
    {
        $this->write('vendor/magento/module-checkout/view/frontend/layout/checkout_cart_index.xml', $this->layout(self::MARKED));
        $this->write('vendor/magento/module-multishipping/view/frontend/layout/checkout_cart_index.xml', $this->layout(self::MARKED));
        $this->write('vendor/mage-os/module-vault/view/frontend/layout/vault_cards_listaction.xml', $this->layout(self::MARKED));

        self::assertSame(['checkout_cart_index', 'vault_cards_listaction'], UncacheableHandles::core($this->root)['handles']);
    }

    public function testCoreNamesTheMagentoReleaseItWasTakenFrom(): void
    {
        $this->write('vendor/composer/installed.json', json_encode(['packages' => [
            ['name' => 'magento/framework', 'version' => '103.0.9'],
            ['name' => 'magento/product-community-edition', 'version' => '2.4.9'],
        ]], JSON_THROW_ON_ERROR));

        self::assertSame('magento/product-community-edition 2.4.9', UncacheableHandles::core($this->root)['magento']);
    }

    public function testCoreFallsBackToTheFrameworkRelease(): void
    {
        $this->write('vendor/composer/installed.json', json_encode(['packages' => [
            ['name' => 'magento/framework', 'version' => '103.0.9'],
        ]], JSON_THROW_ON_ERROR));

        self::assertSame('magento/framework 103.0.9', UncacheableHandles::core($this->root)['magento']);
    }

    private function violations(array $core, array $exceptions = [], array $required = []): array
    {
        return UncacheableHandles::violations($this->root, $core, ['exceptions' => $exceptions, 'required' => $required]);
    }

    private function storefront(string $package, string $handle, string $body): void
    {
        $this->write(sprintf('packages/%s/src/view/frontend/layout/%s.xml', $package, $handle), $this->layout($body));
    }

    private function layout(string $body): string
    {
        return "<?xml version=\"1.0\"?>\n<page xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\">\n<body>\n" . $body . "\n</body>\n</page>\n";
    }

    private function write(string $relative, string $contents): void
    {
        $path = $this->root . '/' . $relative;
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $contents);
    }
}
