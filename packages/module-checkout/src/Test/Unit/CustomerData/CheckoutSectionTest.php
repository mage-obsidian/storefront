<?php
declare(strict_types=1);

namespace MageObsidian\Checkout\Test\Unit\CustomerData;

use Magento\Customer\CustomerData\SectionSourceInterface;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;
use MageObsidian\Checkout\CustomerData\CheckoutSection;
use MageObsidian\Checkout\Test\Unit\CheckoutConfigFixture;
use PHPUnit\Framework\TestCase;

/**
 * The private half of the checkout config, served through customer-data instead
 * of inlined into the (now cacheable) page. Built over a real CheckoutConfig so
 * that wiring the section to `getConfig()` rather than `getPrivateData()` — which
 * would put the whole payload back in localStorage — fails a test.
 */
class CheckoutSectionTest extends TestCase
{
    use CheckoutConfigFixture;

    protected function setUp(): void
    {
        if (!interface_exists(QuoteIdToMaskedQuoteIdInterface::class)) {
            $this->markTestSkipped('Magento Quote is not available in this runtime.');
        }
    }

    public function testSectionIsACustomerDataSource(): void
    {
        $section = new CheckoutSection($this->checkoutConfig(isLoggedIn: false, maskedId: 'guestmask123'));

        $this->assertInstanceOf(SectionSourceInterface::class, $section);
    }

    public function testSectionCarriesTheQuoteAndAuthMode(): void
    {
        $section = new CheckoutSection($this->checkoutConfig(isLoggedIn: false, maskedId: 'guestmask123'));

        $data = $section->getSectionData();

        $this->assertFalse($data['isLoggedIn']);
        $this->assertSame('guestmask123', $data['maskedCartId']);
        $this->assertSame(2, $data['quote']['itemCount']);
        $this->assertSame('$88.00', $data['quote']['grandTotal']);
        $this->assertSame('braintree_cc_vault', $data['vault'][0]['methodCode']);
    }

    /**
     * Guards the other direction of the split: the section must not re-ship the
     * store-scoped half that the cached page already inlines.
     */
    public function testSectionOmitsThePublicHalf(): void
    {
        $section = new CheckoutSection($this->checkoutConfig(isLoggedIn: true, maskedId: 'unused'));

        $data = $section->getSectionData();

        foreach (['storeCode', 'baseUrl', 'restBaseUrl', 'successUrl', 'layoutMode', 'agreements'] as $key) {
            $this->assertArrayNotHasKey($key, $data, sprintf('section re-ships public key "%s"', $key));
        }
    }
}
