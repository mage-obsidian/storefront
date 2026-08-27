<?php
declare(strict_types=1);

namespace MageObsidian\Checkout\Test\Unit\Observer;

use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use MageObsidian\Checkout\Model\PlaceRedirect;
use MageObsidian\Checkout\Observer\RememberPlaceRedirect;
use PHPUnit\Framework\TestCase;

class RememberPlaceRedirectTest extends TestCase
{
    private function observerFor(?object $quote): Observer
    {
        $event = $this->createMock(Event::class);
        $event->method('getData')->willReturnCallback(
            static fn (string $key) => $key === 'quote' ? $quote : null
        );

        $observer = $this->createMock(Observer::class);
        $observer->method('getEvent')->willReturn($event);

        return $observer;
    }

    private function quotePaying(?string $redirectUrl): DataObject
    {
        $payment = new DataObject(['order_place_redirect_url' => $redirectUrl]);

        return new DataObject(['payment' => $payment]);
    }

    public function testTheGatewaysUrlIsRemembered(): void
    {
        $redirect = $this->createMock(PlaceRedirect::class);
        $redirect->expects($this->once())->method('remember')->with('https://gateway.test/pay/abc');

        (new RememberPlaceRedirect($redirect))->execute(
            $this->observerFor($this->quotePaying('https://gateway.test/pay/abc'))
        );
    }

    public function testAMethodWithoutOneClearsWhateverWasThere(): void
    {
        $redirect = $this->createMock(PlaceRedirect::class);
        $redirect->expects($this->once())->method('remember')->with('');

        (new RememberPlaceRedirect($redirect))->execute($this->observerFor($this->quotePaying(null)));
    }

    public function testAnEventWithoutAQuoteClearsRatherThanCarryingOneOrderIntoTheNext(): void
    {
        $redirect = $this->createMock(PlaceRedirect::class);
        $redirect->expects($this->once())->method('remember')->with('');

        (new RememberPlaceRedirect($redirect))->execute($this->observerFor(null));
    }
}
