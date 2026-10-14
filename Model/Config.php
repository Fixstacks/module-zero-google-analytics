<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\GoogleAnalytics\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Stores > Configuration > Zero > Google Analytics, for the current store view.
 */
class Config
{
    private const PATH = 'zero_google_analytics/';
    /** Magento's own GA4 setting (Magento_GoogleGtag), used when ours is empty. */
    private const MAGENTO_MEASUREMENT_ID = 'google/gtag/analytics4/measurement_id';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * On, and with an ID for the chosen mode.
     */
    public function isActive(): bool
    {
        return $this->flag('general/enabled') && $this->tagId() !== '';
    }

    public function mode(): string
    {
        return $this->value('general/mode') === 'gtm' ? 'gtm' : 'gtag';
    }

    /**
     * G-… in GA4 mode, GTM-… in Tag Manager mode; '' when missing or malformed.
     */
    public function tagId(): string
    {
        if ($this->mode() === 'gtm') {
            $id = strtoupper(trim($this->value('general/container_id')));

            return preg_match('/^GTM-[A-Z0-9]{4,12}$/', $id) ? $id : '';
        }
        $id = strtoupper(trim($this->value('general/measurement_id')))
            ?: strtoupper(trim((string) $this->scopeConfig->getValue(self::MAGENTO_MEASUREMENT_ID, ScopeInterface::SCOPE_STORE)));

        return preg_match('/^G-[A-Z0-9]{4,15}$/', $id) ? $id : '';
    }

    public function isDebug(): bool
    {
        return $this->flag('general/debug');
    }

    /**
     * advanced: Google's tag loads at once (cookieless until consent); basic: only after consent.
     */
    public function consentMode(): string
    {
        return $this->value('consent/mode') === 'basic' ? 'basic' : 'advanced';
    }

    private function flag(string $path): bool
    {
        return $this->scopeConfig->isSetFlag(self::PATH . $path, ScopeInterface::SCOPE_STORE);
    }

    private function value(string $path): string
    {
        return (string) $this->scopeConfig->getValue(self::PATH . $path, ScopeInterface::SCOPE_STORE);
    }
}
