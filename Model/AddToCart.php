<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\QuickOrder\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Puts a list of SKUs in the cart, and looks a SKU up for the page. A SKU
 * that is unknown, disabled, not sold in this website, out of stock or
 * needs options (a configurable parent: order its variant's SKU) is
 * reported by name; the rest go in.
 */
class AddToCart
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CheckoutSession $checkoutSession,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly PriceCurrencyInterface $priceCurrency
    ) {
    }

    /**
     * @param array<string, float> $lines SKU => quantity
     * @return array{added: array<string, float>, failed: array<string, string>} SKU => quantity added, SKU => why not
     */
    /**
     * @param array<string, float> $lines qty by SKU
     * @param Quote|null $quote the cart to fill; the checkout session's when null (the theme)
     * @return array{added: array<string, float>, failed: array<string, string>}
     */
    public function add(array $lines, ?Quote $quote = null): array
    {
        $added = [];
        $failed = [];
        $fromSession = $quote === null;
        $quote ??= $this->checkoutSession->getQuote();
        foreach ($lines as $sku => $qty) {
            $sku = (string) $sku;
            try {
                $product = $this->product($sku);
                $result = $quote->addProduct($product, new DataObject(['qty' => $qty]));
                if (is_string($result)) {
                    $failed[$sku] = $result;
                    continue;
                }
                $added[$sku] = $qty;
            } catch (NoSuchEntityException) {
                $failed[$sku] = (string) __('not found');
            } catch (LocalizedException $e) {
                $failed[$sku] = $e->getMessage();
            }
        }
        if ($added) {
            $quote->setTotalsCollectedFlag(false)->collectTotals();
            $this->cartRepository->save($quote);
            if ($fromSession) {
                $this->checkoutSession->setQuoteId($quote->getId());
            }
        }

        return ['added' => $added, 'failed' => $failed];
    }

    /**
     * The product behind a SKU for the page: name, price, whether it can be ordered.
     *
     * @return array{name: string, price: string, salable: bool, url: string}|null null for an unknown SKU
     */
    public function lookup(string $sku): ?array
    {
        try {
            $product = $this->product($sku);
        } catch (NoSuchEntityException) {
            return null;
        }

        return [
            'sku' => (string) $product->getSku(),
            'name' => (string) $product->getName(),
            'price' => $this->priceCurrency->format((float) $product->getFinalPrice(), false),
            'price_value' => $this->priceCurrency->convertAndRound((float) $product->getFinalPrice()),
            'salable' => $product->isSalable() && !$product->getTypeInstance()->hasRequiredOptions($product),
            'url' => (string) $product->getProductUrl(),
        ];
    }

    /**
     * @throws NoSuchEntityException for a SKU the store doesn't sell
     */
    private function product(string $sku): Product
    {
        $store = $this->storeManager->getStore();
        $product = $this->productRepository->get($sku, false, (int) $store->getId());
        if ((int) $product->getStatus() !== Status::STATUS_ENABLED
            || !in_array((int) $store->getWebsiteId(), array_map('intval', (array) $product->getWebsiteIds()), true)
        ) {
            throw new NoSuchEntityException(__('The product that was requested doesn\'t exist.'));
        }

        return $product;
    }
}
