<?php
declare(strict_types=1);

namespace MageObsidian\SendFriend\Test\Unit\ViewModel;

use Magento\Catalog\Model\Product;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\SendFriend\Helper\Data as SendFriendHelper;
use MageObsidian\SendFriend\ViewModel\SendToFriend;
use PHPUnit\Framework\TestCase;

/**
 * Email-to-a-friend PDP VM. We assert the enable gate delegates to the native
 * helper and the share URL targets the form for the current product.
 */
class SendToFriendTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(SendFriendHelper::class)) {
            $this->markTestSkipped('Magento SendFriend is not available in this runtime.');
        }
    }

    public function testIsAllowedDelegatesToTheHelper(): void
    {
        $helper = $this->createMock(SendFriendHelper::class);
        $helper->method('isEnabled')->willReturn(true);

        $view = new SendToFriend($helper, $this->createMock(Registry::class), $this->createMock(UrlInterface::class));

        $this->assertTrue($view->isAllowed());
    }

    public function testSendUrlTargetsTheCurrentProduct(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn(6);

        $registry = $this->createMock(Registry::class);
        $registry->method('registry')->with('current_product')->willReturn($product);

        $url = $this->createMock(UrlInterface::class);
        $url->method('getUrl')->with('sendfriend/product/send', ['id' => 6])
            ->willReturn('https://shop.test/sendfriend/product/send/id/6/');

        $view = new SendToFriend($this->createMock(SendFriendHelper::class), $registry, $url);

        $this->assertSame('https://shop.test/sendfriend/product/send/id/6/', $view->getSendUrl());
    }

    public function testSendUrlIsEmptyOffAProductPage(): void
    {
        $registry = $this->createMock(Registry::class);
        $registry->method('registry')->willReturn(null);

        $view = new SendToFriend(
            $this->createMock(SendFriendHelper::class),
            $registry,
            $this->createMock(UrlInterface::class)
        );

        $this->assertSame('', $view->getSendUrl());
    }
}
