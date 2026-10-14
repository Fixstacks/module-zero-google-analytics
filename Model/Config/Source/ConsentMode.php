<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\GoogleAnalytics\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ConsentMode implements OptionSourceInterface
{
    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'advanced', 'label' => __('Advanced: load Google, cookieless until consent')],
            ['value' => 'basic', 'label' => __('Basic: load nothing from Google until consent')],
        ];
    }
}
