<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Checkout project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Checkout\Block;

use Magento\Framework\View\Element\Template\Context;
use MageObsidian\Checkout\Model\Payment\RendererDescriptors;
use MageObsidian\Checkout\Model\Payment\Withdrawn;
use MageObsidian\ModernFrontend\Block\Template;
use MageObsidian\ModernFrontend\Service\Vue\IslandMarkup;
use MageObsidian\ModernFrontend\ViewModel\Image;
use MageObsidian\ModernFrontend\ViewModel\SchemaOrg;
use MageObsidian\ModernFrontend\ViewModel\ViteResolver;
use Throwable;

class Checkout extends Template
{
    private ?array $paymentRenderers = null;

    private ?array $withdrawnMethods = null;

    public function __construct(
        Context $context,
        ViteResolver $viteResolver,
        SchemaOrg $schemaOrg,
        Image $image,
        IslandMarkup $islandMarkup,
        private readonly RendererDescriptors $rendererDescriptors,
        private readonly Withdrawn $withdrawn,
        array $data = []
    ) {
        parent::__construct($context, $viteResolver, $schemaOrg, $image, $islandMarkup, $data);
    }

    public function getWithdrawnMethods(): array
    {
        if ($this->withdrawnMethods !== null) {
            return $this->withdrawnMethods;
        }

        try {
            return $this->withdrawnMethods = $this->withdrawn->codes(array_keys($this->getPaymentRenderers()));
        } catch (Throwable) {
            return $this->withdrawnMethods = [];
        }
    }

    public function getPaymentRenderers(): array
    {
        if ($this->paymentRenderers !== null) {
            return $this->paymentRenderers;
        }

        try {
            return $this->paymentRenderers = $this->rendererDescriptors->collect($this->getLayout());
        } catch (Throwable) {
            return $this->paymentRenderers = [];
        }
    }
}
