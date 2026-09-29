<?php
/**
 * This file is part of the MageObsidian - Checkout project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Checkout\Model\Payment;

use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\LayoutInterface;
use MageObsidian\Checkout\Api\PaymentRendererInterface;
use MageObsidian\ModernFrontend\ViewModel\ViteResolver;
use Throwable;

class RendererDescriptors
{
    public const string CONTAINER = 'checkout.payment.methods';

    public const string ARGUMENT = 'renderer';

    public function __construct(private readonly ViteResolver $viteResolver)
    {
    }

    public function collect(LayoutInterface $layout, string $container = self::CONTAINER): array
    {
        $descriptors = [];

        foreach ($layout->getChildNames($container) as $name) {
            $renderer = $this->rendererOf($layout, (string)$name);
            if ($renderer === null) {
                continue;
            }
            try {
                $codes = $renderer->getMethodCodes();
                $component = $this->viteResolver->resolveComponentPath($renderer->getComponent());
            } catch (Throwable) {
                continue;
            }
            foreach ($codes as $code) {
                if (!isset($descriptors[$code])) {
                    $descriptors[$code] = ['component' => $component];
                }
            }
        }

        return $descriptors;
    }

    private function rendererOf(LayoutInterface $layout, string $name): ?PaymentRendererInterface
    {
        $block = $layout->getBlock($name);
        if (!$block instanceof AbstractBlock) {
            return null;
        }
        $renderer = $block->getData(self::ARGUMENT);

        return $renderer instanceof PaymentRendererInterface ? $renderer : null;
    }
}
