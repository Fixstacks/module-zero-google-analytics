<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\GoogleAnalytics\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Zero\GoogleAnalytics\Model\EventQueue;
use Zero\GoogleAnalytics\Model\ItemBuilder;

/**
 * sales_quote_remove_item → remove_from_cart (cart lines only, not their children).
 */
class RemoveFromCart implements ObserverInterface
{
    public function __construct(
        private readonly EventQueue $queue,
        private readonly ItemBuilder $itemBuilder,
        private readonly PriceCurrencyInterface $priceCurrency
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var \Magento\Quote\Model\Quote\Item $line */
        $line = $observer->getEvent()->getData('quote_item');
        if (!$line || $line->getParentItem() || !$line->getId()) {
            return;
        }
        $item = $this->itemBuilder->fromQuoteItem($line);
        $this->queue->add('remove_from_cart', [
            'currency' => $this->priceCurrency->getCurrency()->getCode(),
            'value' => round((float) $item['price'] * (int) $item['quantity'], 2),
            'items' => [$item],
        ]);
    }
}
