<?php
declare(strict_types=1);

namespace MageObsidian\Checkout\Test\Unit\Model\Payment;

use MageObsidian\Checkout\Api\MethodDataProviderInterface;
use MageObsidian\Checkout\Model\Payment\MethodData;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class MethodDataTest extends TestCase
{
    private function provider(array $data): MethodDataProviderInterface
    {
        $provider = $this->createStub(MethodDataProviderInterface::class);
        $provider->method('getData')->willReturn($data);

        return $provider;
    }

    public function testItKeysWhatEachProviderContributedByMethodCode(): void
    {
        $pool = new MethodData([
            'vault' => $this->provider(['braintree_cc_vault' => ['tokens' => [['publicHash' => 'h1']]]]),
            'probe' => $this->provider(['verification_probe' => ['reference' => 'REF-1']]),
        ]);

        $this->assertSame(
            [
                'braintree_cc_vault' => ['tokens' => [['publicHash' => 'h1']]],
                'verification_probe' => ['reference' => 'REF-1'],
            ],
            $pool->getData()
        );
    }

    public function testTwoProvidersCanFeedTheSameMethod(): void
    {
        $pool = new MethodData([
            'one' => $this->provider(['checkmo' => ['a' => 1]]),
            'two' => $this->provider(['checkmo' => ['b' => 2]]),
        ]);

        $this->assertSame(['checkmo' => ['a' => 1, 'b' => 2]], $pool->getData());
    }

    public function testAProviderThatThrowsDoesNotTakeTheRestWithIt(): void
    {
        $broken = $this->createStub(MethodDataProviderInterface::class);
        $broken->method('getData')->willThrowException(new RuntimeException('gateway is down'));

        $pool = new MethodData(['broken' => $broken, 'ok' => $this->provider(['checkmo' => ['a' => 1]])]);

        $this->assertSame(['checkmo' => ['a' => 1]], $pool->getData());
    }

    public function testAnEntryWithoutAMethodCodeIsDropped(): void
    {
        $pool = new MethodData(['one' => $this->provider(['' => ['a' => 1], 'checkmo' => 'nonsense'])]);

        $this->assertSame([], $pool->getData());
    }
}
