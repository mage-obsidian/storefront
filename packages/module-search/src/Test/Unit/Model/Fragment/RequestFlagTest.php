<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Search project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Search\Test\Unit\Model\Fragment;

use ArrayObject;
use Magento\Framework\App\Request\Http as HttpRequest;
use MageObsidian\Search\Model\Fragment\RequestFlag;
use MageObsidian\Search\Model\Fragment\RequestParameter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RequestFlagTest extends TestCase
{
    private const string PARAM = 'obsidian_fragment';

    public function testItIsNotRequestedWithoutTheParameter(): void
    {
        $request = $this->request(['color' => '59'], '/men/tops-men.html?color=59');
        $request->expects($this->never())->method('setRequestUri');

        $this->assertFalse((new RequestFlag($request))->consume());
    }

    public function testItIsNotRequestedForAnyOtherValue(): void
    {
        $request = $this->request([self::PARAM => '0'], '/men/tops-men.html?obsidian_fragment=0');
        $request->expects($this->never())->method('setRequestUri');

        $this->assertFalse((new RequestFlag($request))->consume());
    }

    public function testItStripsTheParameterFromTheQueryContainer(): void
    {
        $query = new ArrayObject([self::PARAM => '1', 'color' => '59']);
        $request = $this->request($query, '/men/tops-men.html?obsidian_fragment=1&color=59');

        $this->assertTrue((new RequestFlag($request))->consume());
        $this->assertSame(['color' => '59'], $query->getArrayCopy());
    }

    #[DataProvider('uriProvider')]
    public function testItStripsTheParameterFromTheRequestUri(string $uri, string $expected): void
    {
        $request = $this->request([self::PARAM => '1'], $uri);
        $request->expects($this->once())->method('setRequestUri')->with($expected);

        (new RequestFlag($request))->consume();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function uriProvider(): array
    {
        return [
            'only parameter' => ['/men.html?obsidian_fragment=1', '/men.html'],
            'leading' => ['/men.html?obsidian_fragment=1&color=59', '/men.html?color=59'],
            'trailing' => ['/men.html?color=59&obsidian_fragment=1', '/men.html?color=59'],
            'in the middle' => ['/men.html?p=2&obsidian_fragment=1&color=59', '/men.html?p=2&color=59'],
            // The encoding of everything else must survive untouched: uenc is a
            // byte-for-byte copy of this string.
            'encoded neighbours' => [
                '/men.html?price=30%2C40&obsidian_fragment=1',
                '/men.html?price=30%2C40',
            ],
            'no query at all' => ['/men.html', '/men.html'],
        ];
    }

    public function testItOnlyReadsTheRequestOnce(): void
    {
        $query = new ArrayObject([self::PARAM => '1']);
        $request = $this->request($query, '/men.html?obsidian_fragment=1');
        $request->expects($this->once())->method('setRequestUri');

        $flag = new RequestFlag($request);

        $this->assertTrue($flag->consume());
        $this->assertTrue($flag->consume());
    }

    public function testTheParameterNameIsTheOneTheEnumDeclares(): void
    {
        $this->assertSame(self::PARAM, RequestParameter::Fragment->value);
    }

    /**
     * @param array<string, string>|ArrayObject<string, string> $query
     */
    private function request(array|ArrayObject $query, string $uri): HttpRequest
    {
        $request = $this->getMockBuilder(HttpRequest::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getQuery', 'getRequestUri', 'setRequestUri'])
            ->getMock();
        $request->method('getQuery')->willReturn(is_array($query) ? new ArrayObject($query) : $query);
        $request->method('getRequestUri')->willReturn($uri);

        return $request;
    }
}
