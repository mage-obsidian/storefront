<?php
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Storefront\Controller\Manifest;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Controller\ResultInterface;
use MageObsidian\Storefront\Model\Config\SeoConfig;
use MageObsidian\Storefront\Model\Seo\WebManifestBuilder;

class Index implements HttpGetActionInterface
{
    public const string CONTENT_TYPE = 'application/manifest+json';
    public const int CACHE_LIFETIME = 86400;

    public function __construct(
        private readonly RawFactory $resultRawFactory,
        private readonly WebManifestBuilder $builder,
        private readonly SeoConfig $config
    ) {
    }

    public function execute(): ResultInterface
    {
        /** @var Raw $result */
        $result = $this->resultRawFactory->create();

        if (!$this->config->isManifestEnabled()) {
            return $result->setHttpResponseCode(404)->setContents('');
        }

        $result->setHeader('Content-Type', self::CONTENT_TYPE, true);
        $result->setHeader('Cache-Control', 'public, max-age=' . self::CACHE_LIFETIME, true);
        $result->setHeader('Pragma', 'cache', true);
        $result->setHeader('Expires', gmdate('D, d M Y H:i:s', time() + self::CACHE_LIFETIME) . ' GMT', true);

        return $result->setContents(
            (string)json_encode(
                $this->builder->build(),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            )
        );
    }
}
