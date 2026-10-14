<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\GoogleAnalytics\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Zero\GoogleAnalytics\Model\EventQueue;

/**
 * customer_login → login, customer_register_success → sign_up (method: email).
 * One class, the GA4 event name comes from events.xml (di.xml virtual types).
 */
class Account implements ObserverInterface
{
    public function __construct(
        private readonly EventQueue $queue,
        private readonly string $eventName = 'login'
    ) {
    }

    public function execute(Observer $observer): void
    {
        $this->queue->add($this->eventName, ['method' => 'email']);
    }
}
