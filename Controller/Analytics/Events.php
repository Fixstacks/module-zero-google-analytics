<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\GoogleAnalytics\Controller\Analytics;

use Magento\Framework\Controller\ResultFactory;
use Zero\Core\Controller\PrivateFragmentActionInterface;
use Zero\GoogleAnalytics\Model\EventQueue;

/**
 * zero/analytics/events: the visitor's waiting events (Model\EventQueue) as
 * JSON, once. analytics.js asks only when the zero_ga_events cookie is set.
 * Private: never cached (PrivateFragmentActionInterface).
 */
class Events implements PrivateFragmentActionInterface
{
    public function __construct(
        private readonly ResultFactory $resultFactory,
        private readonly EventQueue $queue
    ) {
    }

    /**
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        return $this->resultFactory->create(ResultFactory::TYPE_JSON)->setData(['events' => $this->queue->take()]);
    }
}
