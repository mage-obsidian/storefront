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
        $this->assertCount(19, $whole);
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

    /**
     * The address book is customer data, so it may only travel through the
     * section — never the shell, which is shared by every visitor on this URL.
     */
    public function testPrivateDataCarriesTheCustomerAddressBook(): void
    {
        $private = $this->viewModel(
            isLoggedIn: true,
            maskedId: '',
            addresses: self::ADDRESS_BOOK,
            defaultShippingId: '20'
        )->getPrivateData();

        $this->assertCount(2, $private['addresses']);

        $austin = $private['addresses'][0];
        $this->assertSame(20, $austin['id']);
        $this->assertTrue($austin['isDefaultShipping']);
        $this->assertSame(['12 Baker Street', 'Flat 3'], $austin['street']);
        $this->assertSame(57, $austin['regionId']);
        $this->assertSame('Texas', $austin['region']);
        $this->assertSame('Ada Lovelace, 12 Baker Street, Austin, Texas 78701', $austin['label']);

        $this->assertFalse($private['addresses'][1]['isDefaultShipping']);
    }

    public function testAGuestHasNoAddressBook(): void
    {
        $private = $this->viewModel(isLoggedIn: false, maskedId: 'guestmask123')->getPrivateData();

        $this->assertSame([], $private['addresses']);
    }

    public function testTheAddressBookNeverReachesTheCachedShell(): void
    {
        $inlined = $this->viewModel(
            isLoggedIn: true,
            maskedId: '',
            shellCacheable: true,
            addresses: self::ADDRESS_BOOK,
            defaultShippingId: '20'
        )->getInlineConfig();

        $this->assertArrayNotHasKey('addresses', $inlined);
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

    /** Mirrors the two addresses seeded on the e2e customer in zento-obsidian. */
    private const ADDRESS_BOOK = [
        [
            'id' => 20, 'firstname' => 'Ada', 'lastname' => 'Lovelace',
            'company' => 'Analytical Engines', 'street' => ['12 Baker Street', 'Flat 3'],
            'city' => 'Austin', 'region' => 'Texas', 'regionId' => 57, 'postcode' => '78701',
            'countryId' => 'US', 'telephone' => '+1 512 555 0142',
        ],
        [
            'id' => 21, 'firstname' => 'Ada', 'lastname' => 'Lovelace',
            'company' => '', 'street' => ['440 Ocean Drive'],
            'city' => 'Miami', 'region' => 'Florida', 'regionId' => 18, 'postcode' => '33139',
            'countryId' => 'US', 'telephone' => '+1 305 555 0199',
        ],
    ];

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
        'addresses',
    ];

    private function viewModel(
        bool $isLoggedIn,
        string $maskedId,
        string $layoutMode = 'stepped',
        bool $agreementsEnabled = false,
        array $agreementItems = [],
        bool $shellCacheable = false,
        array $addresses = [],
        ?string $defaultShippingId = null
    ): CheckoutConfig {
        return $this->checkoutConfig(
            $isLoggedIn,
            $maskedId,
            $layoutMode,
            $agreementsEnabled,
            $agreementItems,
            $shellCacheable,
            $addresses,
            $defaultShippingId
        );
    }
}
