<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\GoogleAnalytics\Model;

use Zero\Consent\Api\TrackerInterface;

/**
 * Google Analytics in Zero_Consent's banner (etc/di.xml).
 */
class ConsentTracker implements TrackerInterface
{
    public function __construct(
        private readonly Config $config
    ) {
    }

    public function isActive(): bool
    {
        return $this->config->isActive();
    }

    public function purposes(): array
    {
        return [
            self::ANALYTICS => __('which pages and products are visited (Google Analytics).'),
            self::MARKETING => __('ads based on your visits (Google Ads).'),
        ];
    }
}
