<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 *
 * Zero_GoogleAnalytics: GA4 e-commerce events for the Zero storefront, sent
 * with gtag.js (GA4 directly) or through a Google Tag Manager container, with
 * Google Consent Mode v2 following the visitor's choice in Zero_Consent's
 * banner. Off by default:
 * Stores > Configuration > Zero > Google Analytics.
 */
use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(ComponentRegistrar::MODULE, 'Zero_GoogleAnalytics', __DIR__);
