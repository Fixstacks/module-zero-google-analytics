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
 * wishlist_add_product → add_to_wishlist.
 */
class AddToWishlist implements ObserverInterface
{
    public function __construct(
        private readonly EventQueue $queue,
        private readonly ItemBuilder $itemBuilder,
        private readonly PriceCurrencyInterface $priceCurrency
    ) {
    }

    public function execute(Observer $observer): void
    {
        $product = $observer->getEvent()->getData('product');
        if (!$product) {
            return;
        }
        $price = (float) $this->priceCurrency->convert((float) $product->getFinalPrice());
        $this->queue->add('add_to_wishlist', [
            'currency' => $this->priceCurrency->getCurrency()->getCode(),
            'value' => round($price, 2),
            'items' => [$this->itemBuilder->fromProduct($product, $price)],
        ]);
    }
}
