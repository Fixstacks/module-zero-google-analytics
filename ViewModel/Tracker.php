<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\GoogleAnalytics\ViewModel;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Block\Product\ListProduct;
use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Catalog\Model\Product;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\View\Element\BlockInterface;
use Magento\Quote\Model\Quote;
use Magento\Search\Model\QueryFactory;
use Zero\Core\Model\Pricing\CachedPrices;
use Zero\GoogleAnalytics\Model\Config;
use Zero\GoogleAnalytics\Model\ItemBuilder;

/**
 * What analytics.js gets from the page: the settings (head) and the page's
 * own GA4 events (end of body, after the content rendered its product
 * lists). Item lists and product views are the same for every visitor, so
 * cached pages carry them; cart, checkout and purchase pages aren't cached.
 */
class Tracker implements ArgumentInterface
{
    private const LIST_LIMIT = 48;

    public function __construct(
        private readonly Config $config,
        private readonly ItemBuilder $itemBuilder,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly CheckoutSession $checkoutSession,
        private readonly QueryFactory $queryFactory,
        private readonly UrlInterface $url,
        private readonly CatalogHelper $catalogHelper,
        private readonly CachedPrices $prices
    ) {
    }

    /** analytics.js runs: for Google's tag or for another tracker using the events. */
    public function isActive(): bool
    {
        return $this->config->collectsEvents();
    }

    /**
     * For the head JSON block.
     *
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        return [
            'mode' => $this->config->mode(),
            // '' when Google Analytics is off: the events run for the other trackers only.
            'id' => $this->config->isActive() ? $this->config->tagId() : '',
            'debug' => $this->config->isDebug(),
            'consentMode' => $this->config->consentMode(),
            'currency' => $this->currency(),
            'eventsUrl' => $this->url->getUrl('zero/analytics/events'),
        ];
    }

    /**
     * The page's events. `_urls` (list pages) maps product links to item
     * indexes for select_item; analytics.js removes it before sending.
     *
     * @return array<int, array{name: string, params: array}>
     */
    public function pageEvents(string $type, BlockInterface $block): array
    {
        $layout = $block->getLayout();

        return match ($type) {
            'product' => $this->productView(),
            'category' => $this->list($layout->getBlock('category.products.list'), 'category'),
            'search' => array_merge(
                [['name' => 'search', 'params' => ['search_term' => (string) $this->queryFactory->get()->getQueryText()]]],
                $this->list($layout->getBlock('search_result_list'), 'search')
            ),
            'cart' => $this->quoteEvent('view_cart'),
            'checkout' => $this->quoteEvent('begin_checkout'),
            'success' => $this->purchase(),
            default => [],
        };
    }

    /**
     * @return array<int, array{name: string, params: array}>
     */
    private function productView(): array
    {
        $product = $this->catalogHelper->getProduct();
        if (!$product instanceof ProductInterface) {
            return [];
        }
        $price = $this->finalPrice($product);

        return [['name' => 'view_item', 'params' => array_filter([
            'currency' => $this->currency(),
            'value' => $price,
            'items' => [$this->itemBuilder->fromProduct($product, $price)],
        ], static fn ($value) => $value !== null)]];
    }

    /**
     * @param BlockInterface|false $listBlock
     * @return array<int, array{name: string, params: array}>
     */
    private function list($listBlock, string $listId): array
    {
        if (!$listBlock instanceof ListProduct) {
            return [];
        }
        $category = $listBlock->getLayer()->getCurrentCategory();
        $listName = $listId === 'search' ? (string) __('Search Results') : (string) $category->getName();
        $listId = $listId === 'search' ? 'search' : 'category_' . $category->getId();
        $items = [];
        $urls = [];
        $index = 0;
        foreach ($listBlock->getLoadedProductCollection() as $product) {
            if ($index >= self::LIST_LIMIT) {
                break;
            }
            $items[] = $this->itemBuilder->fromProduct($product, $this->finalPrice($product), 1, [
                'index' => $index,
                'item_list_id' => $listId,
                'item_list_name' => $listName,
            ]);
            $urls[(string) $product->getProductUrl()] = $index;
            $index++;
        }
        if (!$items) {
            return [];
        }

        return [['name' => 'view_item_list', 'params' => [
            'item_list_id' => $listId,
            'item_list_name' => $listName,
            'items' => $items,
            '_urls' => $urls,
        ]]];
    }

    /**
     * view_cart / begin_checkout from the visitor's cart.
     *
     * @return array<int, array{name: string, params: array}>
     */
    private function quoteEvent(string $name): array
    {
        /** @var Quote $quote */
        $quote = $this->checkoutSession->getQuote();
        $items = [];
        foreach ($quote->getAllVisibleItems() as $index => $item) {
            $items[] = $this->itemBuilder->fromQuoteItem($item) + ['index' => $index];
        }
        if (!$items) {
            return [];
        }
        $params = [
            'currency' => (string) ($quote->getQuoteCurrencyCode() ?: $this->currency()),
            'value' => round((float) $quote->getSubtotalWithDiscount() ?: (float) $quote->getSubtotal(), 2),
            'items' => $items,
        ];
        if ($quote->getCouponCode()) {
            $params['coupon'] = (string) $quote->getCouponCode();
        }

        return [['name' => $name, 'params' => $params]];
    }

    /**
     * @return array<int, array{name: string, params: array}>
     */
    private function purchase(): array
    {
        $order = $this->checkoutSession->getLastRealOrder();
        if (!$order || !$order->getIncrementId()) {
            return [];
        }
        $items = [];
        foreach (array_values($order->getAllVisibleItems()) as $index => $item) {
            $items[] = $this->itemBuilder->fromOrderItem($item, $index);
        }
        $params = [
            'transaction_id' => (string) $order->getIncrementId(),
            'currency' => (string) $order->getOrderCurrencyCode(),
            'value' => round((float) $order->getGrandTotal(), 2),
            'tax' => round((float) $order->getTaxAmount(), 2),
            'shipping' => round((float) $order->getShippingAmount(), 2),
            'items' => $items,
        ];
        if ($order->getCouponCode()) {
            $params['coupon'] = (string) $order->getCouponCode();
        }

        return [['name' => 'purchase', 'params' => $params]];
    }

    /**
     * Null for a Price on Request product (Zero_QuoteRequest): its price is not shown, nor sent.
     */
    private function finalPrice(ProductInterface $product): ?float
    {
        if ($product->getData('zero_quote_only')) {
            return null;
        }

        // The price box's amount, from the block cache (CachedPrices): working it
        // out for every list item on every render cost ~100 ms a category page.
        return $product instanceof Product
            ? round($this->prices->get($product)['final'], 2)
            : round((float) $product->getPriceInfo()->getPrice('final_price')->getAmount()->getValue(), 2);
    }

    private function currency(): string
    {
        return (string) $this->priceCurrency->getCurrency()->getCode();
    }
}
