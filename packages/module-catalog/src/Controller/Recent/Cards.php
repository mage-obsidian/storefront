<?php
/**
 * This file is part of the MageObsidian - Catalog project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Catalog\Controller\Recent;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;
use MageObsidian\Catalog\Block\Recent\Cards as CardsBlock;
use MageObsidian\Catalog\Model\Recent\ProductSelection;

class Cards implements HttpGetActionInterface
{
    public const string BLOCK_NAME = 'recent.cards';

    public const int DEFAULT_COUNT = 5;

    public function __construct(
        private readonly RequestInterface $request,
        private readonly RawFactory $rawFactory,
        private readonly PageFactory $pageFactory,
        private readonly ProductSelection $selection
    ) {
    }

    public function execute(): ResultInterface
    {
        $result = $this->rawFactory->create();
        $result->setHeader('Content-Type', 'text/html; charset=UTF-8', true);
        $result->setHeader('Cache-Control', 'no-store, private', true);

        $products = $this->selection->load(
            $this->selection->idsFrom((string)$this->request->getParam('ids', '')),
            $this->count()
        );
        if ($products === []) {
            return $result->setContents('');
        }

        $block = $this->pageFactory->create()->getLayout()->getBlock(self::BLOCK_NAME);
        if (!$block instanceof CardsBlock) {
            return $result->setHttpResponseCode(500)->setContents('');
        }

        return $result->setContents($block->setData('products', $products)->toHtml());
    }

    private function count(): int
    {
        $count = (int)$this->request->getParam('count', self::DEFAULT_COUNT);

        return max(1, min(ProductSelection::MAX_IDS, $count));
    }
}
