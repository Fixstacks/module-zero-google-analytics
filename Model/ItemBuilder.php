<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\GoogleAnalytics\Model;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\Sales\Api\Data\OrderItemInterface;

/**
 * GA4 e-commerce `items` entries (item_id = SKU) from products, cart items
 * and order items; prices in the store's display currency.
 */
class ItemBuilder
{
    /**
     * @param array<string, mixed> $extra index, item_list_name, item_category…
     * @return array<string, mixed>
     */
    public function fromProduct(ProductInterface $product, ?float $price, int $quantity = 1, array $extra = []): array
    {
        return $this->clean([
            'item_id' => (string) $product->getSku(),
            'item_name' => (string) $product->getName(),
            'price' => $price !== null ? round($price, 2) : null,
            'quantity' => max(1, $quantity),
        ] + $extra);
    }

    /**
     * A visible cart line (the configurable parent, with the chosen child's SKU as variant).
     *
     * @return array<string, mixed>
     */
    public function fromQuoteItem(QuoteItem $item, ?int $quantity = null): array
    {
        $child = $item->getHasChildren() ? current($item->getChildren()) : null;
        $variant = $child ? (string) $child->getSku() : '';

        return $this->clean([
            'item_id' => (string) ($item->getProduct()?->getData('sku') ?: $item->getSku()),
            'item_name' => (string) $item->getName(),
            'item_variant' => $variant !== '' && $variant !== $item->getProduct()?->getData('sku') ? $variant : null,
            'price' => round((float) ($item->getCalculationPrice() ?: $item->getPrice()), 2),
            'quantity' => max(1, $quantity ?? (int) $item->getQty()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function fromOrderItem(OrderItemInterface $item, int $index): array
    {
        $children = method_exists($item, 'getChildrenItems') ? $item->getChildrenItems() : [];
        $child = $children ? current($children) : null;

        return $this->clean([
            'item_id' => (string) ($item->getProduct()?->getData('sku') ?: $item->getSku()),
            'item_name' => (string) $item->getName(),
            'item_variant' => $child ? (string) $child->getSku() : null,
            'price' => round((float) $item->getPrice(), 2),
            'quantity' => max(1, (int) $item->getQtyOrdered()),
            'discount' => (float) $item->getDiscountAmount() > 0 ? round((float) $item->getDiscountAmount() / max(1, (int) $item->getQtyOrdered()), 2) : null,
            'index' => $index,
        ]);
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function clean(array $item): array
    {
        return array_filter($item, static fn ($value) => $value !== null && $value !== '');
    }
}
