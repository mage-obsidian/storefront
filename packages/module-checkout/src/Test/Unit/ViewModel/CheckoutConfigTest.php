<?php
declare(strict_types=1);

namespace MageObsidian\Checkout\Test\Unit\ViewModel;

use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;
use MageObsidian\Checkout\Test\Unit\CheckoutConfigFixture;
use MageObsidian\Checkout\ViewModel\CheckoutConfig;
use PHPUnit\Framework\TestCase;

/**
 * Server-primed checkout config. We assert the auth mode (logged-in → mine,
 * guest → masked id), the REST base URL and the quote snapshot; needs Magento
 * Quote/Store types, so it runs in a Magento root. The mask-creation path and
 * REST authentication are validated end-to-end in the browser.
 */
class CheckoutConfigTest extends TestCase
{
    use CheckoutConfigFixture;

    protected function setUp(): void
    {
        if (!interface_exists(QuoteIdToMaskedQuoteIdInterface::class)) {
            $this->markTestSkipped('Magento Quote is not available in this runtime.');
        }
    }

    public function testGuestConfigCarriesTheMaskedCartId(): void
    {
        $config = $this->viewModel(isLoggedIn: false, maskedId: 'guestmask123')->getConfig();

        $this->assertFalse($config['isLoggedIn']);
        $this->assertSame('guestmask123', $config['maskedCartId']);
        $this->assertSame('', $config['customerEmail']);
        $this->assertSame('https://shop.test/rest/default/V1/', $config['restBaseUrl']);
        $this->assertSame(2, $config['quote']['itemCount']);
        $this->assertSame('$80.00', $config['quote']['subtotal']);
        $this->assertSame('$88.00', $config['quote']['grandTotal']);
        $this->assertSame('$%s', $config['currencyFormat']);
        $this->assertSame('braintree_cc_vault', $config['vault'][0]['methodCode']);
        $this->assertSame('stepped', $config['layoutMode']);
    }

    public function testConfigCarriesTheConfiguredCheckoutLayout(): void
    {
        $config = $this->viewModel(isLoggedIn: false, maskedId: 'guestmask123', layoutMode: 'onepage')->getConfig();

        $this->assertSame('onepage', $config['layoutMode']);
    }

    public function testLoggedInConfigOmitsTheMaskAndCarriesEmail(): void
    {
        $config = $this->viewModel(isLoggedIn: true, maskedId: 'unused')->getConfig();

        $this->assertTrue($config['isLoggedIn']);
        $this->assertSame('', $config['maskedCartId']);
        $this->assertSame('ada@shop.test', $config['customerEmail']);
    }

    public function testConfigCarriesNativeCheckoutOptionsAndAgreements(): void
    {
        $config = $this->viewModel(
            isLoggedIn: false,
            maskedId: 'guestmask123',
            agreementsEnabled: true,
            agreementItems: [['agreementId' => 7, 'mode' => 1, 'content' => 'Terms', 'checkboxText' => 'I agree', 'contentHeight' => '']]
        )->getConfig();

        $this->assertTrue($config['guestCheckout']);
        $this->assertFalse($config['guestCheckoutLogin']);
        $this->assertTrue($config['displayBillingOnPayment']);
        $this->assertSame(25, $config['maxSummaryItems']);
        $this->assertTrue($config['agreements']['enabled']);
        $this->assertSame(7, $config['agreements']['items'][0]['agreementId']);
    }

    /**
     * The whole cacheable-checkout design rests on this: whatever is inlined into
     * the HTML is served from the page cache to every visitor, so a single private
     * key here is a cart leaking across customers.
     */
    public function testPublicConfigCarriesNoPrivateKeys(): void
    {
        $public = $this->viewModel(isLoggedIn: true, maskedId: 'guestmask123')->getPublicConfig();

        foreach (self::PRIVATE_KEYS as $key) {
            $this->assertArrayNotHasKey($key, $public, sprintf('public config leaks "%s"', $key));
        }
    }

    public function testPrivateDataCarriesTheQuoteAndAuthMode(): void
    {
        $private = $this->viewModel(isLoggedIn: false, maskedId: 'guestmask123')->getPrivateData();

        $keys = array_keys($private);
        $expected = self::PRIVATE_KEYS;
        sort($keys);
        sort($expected);
        $this->assertSame($expected, $keys);
        $this->assertFalse($private['isLoggedIn']);
        $this->assertSame('guestmask123', $private['maskedCartId']);
        $this->assertSame(2, $private['quote']['itemCount']);
        $this->assertSame('$88.00', $private['quote']['grandTotal']);
        $this->assertSame('$%s', $private['currencyFormat']);
        $this->assertSame('braintree_cc_vault', $private['vault'][0]['methodCode']);
    }

    /**
     * The split must be total as well as disjoint: a key that falls out of both
     * halves disappears from the island's props without any test failing.
     */
    public function testTheTwoHalvesReconstituteTheWholeConfig(): void
    {
        $viewModel = $this->viewModel(isLoggedIn: false, maskedId: 'guestmask123');

        $whole = array_keys($viewModel->getConfig());
        $halves = array_merge(array_keys($viewModel->getPublicConfig()), self::PRIVATE_KEYS);

        sort($whole);
        sort($halves);
        $this->assertSame($halves, $whole);
        $this->assertCount(18, $whole);
    }

    /**
     * The two stamps that let the client tell a section computed for another
     * currency or store from a fresh one. Magento only rotates
     * `private_content_version` on POST, so a currency switch — a GET — leaves a
     * stale section in localStorage that nothing else would catch.
     */
    public function testPublicConfigCarriesTheCurrencyItWasRenderedIn(): void
    {
        $public = $this->viewModel(isLoggedIn: false, maskedId: 'guestmask123')->getPublicConfig();

        $this->assertSame('USD', $public['currencyCode']);
        $this->assertSame('default', $public['storeCode']);
    }

    public function testPrivateDataStampsTheContextItWasComputedIn(): void
    {
        $private = $this->viewModel(isLoggedIn: false, maskedId: 'guestmask123')->getPrivateData();

        $this->assertSame(['storeCode' => 'default', 'currencyCode' => 'USD'], $private['context']);
    }

    /**
     * What the page actually writes into its HTML. On the cacheable path this is
     * the only thing standing between the design and a cart in the page cache.
     */
    public function testTheCachedShellInlinesOnlyThePublicHalf(): void
    {
        $inlined = $this->viewModel(isLoggedIn: true, maskedId: 'guestmask123', shellCacheable: true)
            ->getInlineConfig();

        foreach (self::PRIVATE_KEYS as $key) {
            $this->assertArrayNotHasKey($key, $inlined, sprintf('cached shell inlines "%s"', $key));
        }
        $this->assertSame('stepped', $inlined['layoutMode']);
    }

    public function testTheUncachedPageStillInlinesEverything(): void
    {
        $viewModel = $this->viewModel(isLoggedIn: false, maskedId: 'guestmask123', shellCacheable: false);

        $this->assertSame($viewModel->getConfig(), $viewModel->getInlineConfig());
        $this->assertSame('guestmask123', $viewModel->getInlineConfig()['maskedCartId']);
    }

    private const PRIVATE_KEYS = [
        'isLoggedIn',
        'customerEmail',
        'maskedCartId',
        'quote',
        'vault',
        // Depends on the request currency, so it varies; keeping it out of the
        // cached shell removes a vary dimension instead of trusting one.
        'currencyFormat',
        'context',
    ];

    private function viewModel(
        bool $isLoggedIn,
        string $maskedId,
        string $layoutMode = 'stepped',
        bool $agreementsEnabled = false,
        array $agreementItems = [],
        bool $shellCacheable = false
    ): CheckoutConfig {
        return $this->checkoutConfig($isLoggedIn, $maskedId, $layoutMode, $agreementsEnabled, $agreementItems, $shellCacheable);
    }
}
