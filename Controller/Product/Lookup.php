<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\QuickOrder\Controller\Product;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Result\Layout;
use Magento\Framework\View\Result\LayoutFactory;
use Zero\Core\Controller\PrivateFragmentActionInterface;

/**
 * HTMX: what a typed SKU is (name, price, whether it can be ordered), next
 * to the row (templates/lookup.phtml).
 */
class Lookup implements PrivateFragmentActionInterface
{
    private const BLOCK_NAME = 'zero.quickorder.lookup';

    public function __construct(
        private readonly LayoutFactory $resultLayoutFactory,
        private readonly RequestInterface $request
    ) {
    }

    /**
     * @return Layout
     */
    public function execute()
    {
        $result = $this->resultLayoutFactory->create();
        $result->addDefaultHandle();
        $result->getLayout()->getBlock(self::BLOCK_NAME)?->setData('sku', $this->sku());
        $result->getLayout()->addOutputElement(self::BLOCK_NAME);

        return $result;
    }

    /**
     * The SKU: `sku`, or the one row the input sent as items[n][sku] (hx-get on the input).
     */
    private function sku(): string
    {
        $sku = $this->request->getParam('sku');
        foreach ((array) $this->request->getParam('items', []) as $row) {
            $sku ??= $row['sku'] ?? null;
        }

        return is_scalar($sku) ? trim((string) $sku) : '';
    }
}
