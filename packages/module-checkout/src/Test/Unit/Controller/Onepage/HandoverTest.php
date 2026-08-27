<?php
declare(strict_types=1);

namespace MageObsidian\Checkout\Test\Unit\Controller\Onepage;

use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use MageObsidian\Checkout\Controller\Onepage\Handover;
use MageObsidian\Checkout\Model\PlaceRedirect;
use PHPUnit\Framework\TestCase;

class HandoverTest extends TestCase
{
    private function handoverTaking(string $url, Redirect $result): Handover
    {
        $factory = $this->createMock(RedirectFactory::class);
        $factory->method('create')->willReturn($result);

        $redirect = $this->createMock(PlaceRedirect::class);
        $redirect->method('take')->willReturn($url);

        return new Handover($factory, $redirect);
    }

    public function testAShopperWhoseMethodWantsThemElsewhereGoesThere(): void
    {
        $result = $this->createMock(Redirect::class);
        $result->expects($this->once())->method('setUrl')->with('https://gateway.test/pay/abc')->willReturnSelf();
        $result->expects($this->never())->method('setPath');

        $this->handoverTaking('https://gateway.test/pay/abc', $result)->execute();
    }

    public function testAShopperWhosePaymentIsDoneGoesToTheSuccessPage(): void
    {
        $result = $this->createMock(Redirect::class);
        $result->expects($this->once())->method('setPath')->with('checkout/onepage/success')->willReturnSelf();
        $result->expects($this->never())->method('setUrl');

        $this->handoverTaking('', $result)->execute();
    }
}
