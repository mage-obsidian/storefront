<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Search project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Search\Test\Unit\Plugin\Controller;

use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\App\ViewInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Framework\View\LayoutInterface;
use Magento\Framework\View\Result\Page;
use MageObsidian\Search\Exception\FragmentUnavailableException;
use MageObsidian\Search\Model\Fragment\CacheTags;
use MageObsidian\Search\Model\Fragment\Gate;
use MageObsidian\Search\Model\Fragment\PayloadKey;
use MageObsidian\Search\Model\Fragment\Renderer;
use MageObsidian\Search\Plugin\Controller\RenderListingFragment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class RenderListingFragmentTest extends TestCase
{
    private const string ACTION = 'catalog_category_view';
    private const string HANDLE = 'mageobsidian_listing_fragment';
    private const array SECTIONS = ['listing' => '<ol></ol>', 'filters' => '<nav></nav>'];

    private Gate&MockObject $gate;
    private Renderer&MockObject $renderer;
    private CacheTags&MockObject $cacheTags;
    private HttpResponse&MockObject $response;
    private Json&MockObject $json;
    private LoggerInterface&MockObject $logger;
    private ActionInterface&MockObject $controller;

    protected function setUp(): void
    {
        $this->gate = $this->createMock(Gate::class);
        $this->renderer = $this->createMock(Renderer::class);
        $this->cacheTags = $this->createMock(CacheTags::class);
        $this->response = $this->createMock(HttpResponse::class);
        $this->json = $this->createMock(Json::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->controller = $this->createMock(ActionInterface::class);
    }

    public function testARequestTheGateRejectsIsLeftAlone(): void
    {
        $this->gate->method('isRequested')->willReturn(false);
        $this->renderer->expects($this->never())->method('render');
        $page = $this->createMock(Page::class);

        $this->assertSame($page, $this->plugin()->aroundExecute($this->controller, static fn () => $page));
    }

    public function testANonPageResultPassesThroughUntouched(): void
    {
        $this->gate->method('isRequested')->willReturn(true);
        $this->renderer->expects($this->never())->method('render');
        $redirect = $this->createMock(Redirect::class);

        $this->assertSame($redirect, $this->plugin()->aroundExecute($this->controller, static fn () => $redirect));
    }

    public function testItAnswersWithTheRenderedSections(): void
    {
        $layout = $this->createMock(LayoutInterface::class);
        $page = $this->createMock(Page::class);
        $page->method('getLayout')->willReturn($layout);
        $page->expects($this->once())->method('addHandle')->with(self::HANDLE);

        $this->gate->method('isRequested')->willReturn(true);
        $this->renderer->expects($this->once())
            ->method('render')
            ->with($layout, self::ACTION)
            ->willReturn(self::SECTIONS);
        $this->cacheTags->expects($this->once())->method('apply')->with($layout, $this->response);
        $this->json->expects($this->once())
            ->method('setData')
            ->with([PayloadKey::Sections->value => self::SECTIONS]);

        $this->assertSame($this->json, $this->plugin()->aroundExecute($this->controller, fn () => $page));
    }

    public function testABrokenFragmentFailsClosedAndUncached(): void
    {
        $page = $this->createMock(Page::class);
        $page->method('getLayout')->willReturn($this->createMock(LayoutInterface::class));

        $this->gate->method('isRequested')->willReturn(true);
        $this->renderer->method('render')->willThrowException(new FragmentUnavailableException(__('nope')));
        $this->logger->expects($this->once())->method('critical');
        $this->response->expects($this->once())->method('setNoCacheHeaders');
        $this->json->expects($this->once())->method('setHttpResponseCode')->with(500);
        $this->json->expects($this->once())->method('setData')->with([PayloadKey::Error->value => true]);

        $this->assertSame($this->json, $this->plugin()->aroundExecute($this->controller, fn () => $page));
    }

    public function testTheOldStyleControllerGetsItsLayoutReplacedToo(): void
    {
        $layout = $this->createMock(LayoutInterface::class);
        $view = $this->createMock(ViewInterface::class);
        $view->method('getLayout')->willReturn($layout);

        $this->gate->method('isRequested')->willReturn(true);
        $this->renderer->method('render')->with($layout, self::ACTION)->willReturn(self::SECTIONS);
        $this->cacheTags->expects($this->once())->method('apply')->with($layout, $this->response);
        $this->response->expects($this->once())
            ->method('representJson')
            ->with(json_encode([PayloadKey::Sections->value => self::SECTIONS]));

        $plugin = $this->plugin();
        $this->assertSame(
            $view,
            $plugin->aroundRenderLayout($view, static fn () => throw new \LogicException('the page must not render'))
        );
    }

    public function testTheOldStylePathIsLeftAloneWhenTheGateRejects(): void
    {
        $this->gate->method('isRequested')->willReturn(false);
        $view = $this->createMock(ViewInterface::class);
        $this->response->expects($this->never())->method('representJson');

        $this->assertSame($view, $this->plugin()->aroundRenderLayout($view, static fn () => $view));
    }

    public function testTheOldStylePathAlsoFailsClosed(): void
    {
        $view = $this->createMock(ViewInterface::class);
        $view->method('getLayout')->willReturn($this->createMock(LayoutInterface::class));

        $this->gate->method('isRequested')->willReturn(true);
        $this->renderer->method('render')->willThrowException(new FragmentUnavailableException(__('nope')));
        $this->logger->expects($this->once())->method('critical');
        $this->response->expects($this->once())->method('setNoCacheHeaders');
        $this->response->expects($this->once())->method('setHttpResponseCode')->with(500);
        $this->response->expects($this->once())
            ->method('representJson')
            ->with(json_encode([PayloadKey::Error->value => true]));

        $this->plugin()->aroundRenderLayout($view, static fn () => $view);
    }

    public function testLoadLayoutOnlyGetsTheHandleWhenFragmentsAreRequested(): void
    {
        $update = $this->getMockBuilder(\Magento\Framework\View\Layout\ProcessorInterface::class)->getMock();
        $update->expects($this->once())->method('addHandle')->with(self::HANDLE);
        $layout = $this->createMock(LayoutInterface::class);
        $layout->method('getUpdate')->willReturn($update);
        $view = $this->createMock(ViewInterface::class);
        $view->method('getLayout')->willReturn($layout);

        $this->gate->method('isRequested')->willReturn(true);

        $this->assertSame([null, true, true, true], $this->plugin()->beforeLoadLayout($view));
    }

    private function plugin(): RenderListingFragment
    {
        $request = $this->createMock(HttpRequest::class);
        $request->method('getFullActionName')->willReturn(self::ACTION);

        $jsonFactory = $this->createMock(JsonFactory::class);
        $jsonFactory->method('create')->willReturn($this->json);

        $serializer = $this->createMock(JsonSerializer::class);
        $serializer->method('serialize')->willReturnCallback(
            static fn (mixed $value): string => json_encode($value)
        );

        return new RenderListingFragment(
            $this->gate,
            $this->renderer,
            $this->cacheTags,
            $request,
            $this->response,
            $jsonFactory,
            $serializer,
            $this->logger
        );
    }
}
