<?php
declare(strict_types=1);

namespace MageObsidian\Checkout\Test\Unit\Model;

use Magento\Checkout\Model\Session as CheckoutSession;
use MageObsidian\Checkout\Model\PlaceRedirect;
use PHPUnit\Framework\TestCase;

/**
 * The session is not a DataObject: writes go through its __call magic and reads
 * through getData($key, $clear). The double answers both the way the platform
 * does, so a change of accessor shows up here.
 */
class PlaceRedirectTest extends TestCase
{
    /**
     * @param array<string, mixed> $store
     */
    private function sessionOver(array &$store): CheckoutSession
    {
        $session = $this->createMock(CheckoutSession::class);
        $session->method('__call')->willReturnCallback(
            static function (string $method, array $args) use (&$store) {
                if ($method === 'setMageObsidianPlaceRedirect') {
                    $store[PlaceRedirect::SESSION_KEY] = $args[0];
                }
                return null;
            }
        );
        $session->method('getData')->willReturnCallback(
            static function (string $key = '', bool $clear = false) use (&$store) {
                $value = $store[$key] ?? null;
                if ($clear) {
                    unset($store[$key]);
                }
                return $value;
            }
        );

        return $session;
    }

    public function testAUrlSurvivesUntilItIsTaken(): void
    {
        $store = [];
        $redirect = new PlaceRedirect($this->sessionOver($store));
        $redirect->remember('https://gateway.test/pay/abc');

        $this->assertSame('https://gateway.test/pay/abc', $redirect->take());
    }

    public function testTakingItClearsIt(): void
    {
        $store = [];
        $redirect = new PlaceRedirect($this->sessionOver($store));
        $redirect->remember('https://gateway.test/pay/abc');
        $redirect->take();

        $this->assertSame('', $redirect->take());
    }

    public function testAMethodThatWantsNoRedirectLeavesNothingBehind(): void
    {
        $store = [PlaceRedirect::SESSION_KEY => 'https://gateway.test/stale'];
        $redirect = new PlaceRedirect($this->sessionOver($store));
        $redirect->remember('   ');

        $this->assertSame('', $redirect->take());
    }
}
