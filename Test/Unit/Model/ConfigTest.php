<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\GoogleAnalytics\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;
use Zero\GoogleAnalytics\Model\Config;

class ConfigTest extends TestCase
{
    private function config(array $values): Config
    {
        $scope = $this->createStub(ScopeConfigInterface::class);
        $scope->method('getValue')->willReturnCallback(fn ($path) => $values[$path] ?? null);
        $scope->method('isSetFlag')->willReturnCallback(fn ($path) => !empty($values[$path]));

        return new Config($scope);
    }

    public function testGa4IdFallsBackToMagentosOwnSetting(): void
    {
        $config = $this->config([
            'zero_google_analytics/general/enabled' => '1',
            'google/gtag/analytics4/measurement_id' => ' g-abc123xyz ',
        ]);

        $this->assertSame('gtag', $config->mode());
        $this->assertSame('G-ABC123XYZ', $config->tagId());
        $this->assertTrue($config->isActive());
    }

    public function testMalformedIdsKeepItOff(): void
    {
        $this->assertFalse($this->config([
            'zero_google_analytics/general/enabled' => '1',
            'zero_google_analytics/general/measurement_id' => 'UA-12345-1',
        ])->isActive(), 'Universal Analytics IDs are gone');
        $this->assertFalse($this->config([
            'zero_google_analytics/general/enabled' => '1',
            'zero_google_analytics/general/mode' => 'gtm',
            'zero_google_analytics/general/container_id' => 'GTM-"><script>',
        ])->isActive());
    }

    public function testTagManagerAndConsentSettings(): void
    {
        $config = $this->config([
            'zero_google_analytics/general/enabled' => '1',
            'zero_google_analytics/general/mode' => 'gtm',
            'zero_google_analytics/general/container_id' => 'gtm-k7x9q2',
            'zero_google_analytics/consent/mode' => 'basic',
        ]);

        $this->assertSame('GTM-K7X9Q2', $config->tagId());
        $this->assertSame('basic', $config->consentMode());
    }
}
