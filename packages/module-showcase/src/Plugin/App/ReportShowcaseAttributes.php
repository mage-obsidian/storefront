<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Showcase project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Showcase\Plugin\App;

use Magento\Framework\App\FrontControllerInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use MageObsidian\Showcase\Model\Telemetry\Attributes;
use MageObsidian\Showcase\Model\Telemetry\RecorderInterface;
use Throwable;

/**
 * Hands the request's feature set to the monitoring agent once the page is done.
 *
 * It sits on dispatch rather than on the action so it also covers the requests
 * that never reach one, and it runs last so the values it reports are the ones
 * the page was actually rendered with.
 */
class ReportShowcaseAttributes
{
    public function __construct(
        private readonly Attributes $attributes,
        private readonly RecorderInterface $recorder
    ) {
    }

    // The interface docblock promises a ResponseInterface, but FrontController
    // hands back a ResultInterface for every page a controller renders.
    public function afterDispatch(
        FrontControllerInterface $subject,
        ResponseInterface|ResultInterface $result
    ): ResponseInterface|ResultInterface {
        try {
            foreach ($this->attributes->collect() as $name => $value) {
                $this->recorder->record($name, $value);
            }
        } catch (Throwable) {
            // Measuring the demo is never worth failing a page over.
        }

        return $result;
    }
}
