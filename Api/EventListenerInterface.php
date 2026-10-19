<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\GoogleAnalytics\Api;

/**
 * Another tracker that uses the storefront's GA4 e-commerce events (Meta,
 * TikTok, Pinterest pixels, …). Registered with Model\Config in di.xml:
 * while one is active, the events are collected and analytics.js runs even
 * without a Google tag, and in the browser every event is also dispatched
 * as `zero:analytics` ({ name, params }) and kept in window.zeroAnalytics.
 */
interface EventListenerInterface
{
    /**
     * On in the current store view.
     */
    public function isActive(): bool;
}
