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
 * sales_quote_product_add_after → add_to_cart, for the quantity just added.
 * The first item is the cart line (a configurable's parent).
 */
class AddToCart implements ObserverInterface
{
    public function __construct(
        private readonly EventQueue $queue,
        private readonly ItemBuilder $itemBuilder,
        private readonly PriceCurrencyInterface $priceCurrency
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var \Magento\Quote\Model\Quote\Item[] $items */
        $items = (array) $observer->getEvent()->getData('items');
        $line = $items[0] ?? null;
        if (!$line) {
            return;
        }
        $quantity = max(1, (int) ($line->getQtyToAdd() ?: $line->getQty()));
        $price = (float) $this->priceCurrency->convert((float) $line->getProduct()->getFinalPrice($quantity));
        // The cart line's own price isn't calculated yet at this point: the product's final price is.
        $item = ['price' => round($price, 2)] + $this->itemBuilder->fromQuoteItem($line, $quantity);

        $this->queue->add('add_to_cart', [
            'currency' => $this->priceCurrency->getCurrency()->getCode(),
            'value' => round($price * $quantity, 2),
            'items' => [$item],
        ]);
    }
}
