<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\GoogleAnalytics\Model;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;

/**
 * Events that happen in a POST (Add to Cart, removing an item, sign in…)
 * while the page the visitor sees next comes from the page cache: they wait
 * in the session, and the `zero_ga_events` cookie tells analytics.js to
 * fetch them (zero/analytics/events) once.
 */
class EventQueue
{
    public const COOKIE = 'zero_ga_events';
    private const SESSION_KEY = 'zero_ga_events';
    private const LIMIT = 20;

    public function __construct(
        private readonly CustomerSession $session,
        private readonly CookieManagerInterface $cookieManager,
        private readonly CookieMetadataFactory $cookieMetadataFactory,
        private readonly Config $config
    ) {
    }

    /**
     * @param array<string, mixed> $params GA4 event parameters
     */
    public function add(string $name, array $params = []): void
    {
        if (!$this->config->isActive()) {
            return;
        }
        $events = (array) $this->session->getData(self::SESSION_KEY);
        $events[] = ['name' => $name, 'params' => $params];
        $this->session->setData(self::SESSION_KEY, array_slice($events, -self::LIMIT));
        $this->cookieManager->setPublicCookie(
            self::COOKIE,
            '1',
            $this->cookieMetadataFactory->createPublicCookieMetadata()->setPath('/')->setHttpOnly(false)->setSameSite('Lax')
        );
    }

    /**
     * The waiting events, removed from the queue.
     *
     * @return array<int, array{name: string, params: array}>
     */
    public function take(): array
    {
        $events = (array) $this->session->getData(self::SESSION_KEY, true);
        $this->cookieManager->deleteCookie(self::COOKIE, $this->cookieMetadataFactory->createCookieMetadata()->setPath('/'));

        return array_values($events);
    }
}
