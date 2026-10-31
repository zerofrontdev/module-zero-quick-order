<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\QuickOrder\Controller\Cart;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\Http as Request;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Zero\QuickOrder\Model\AddToCart;
use Zero\QuickOrder\Model\Config;
use Zero\QuickOrder\Model\Parser;

/**
 * The quick order form's POST: the rows, the pasted list and the CSV file
 * go in the cart; what went in and what didn't are messages on the cart
 * page (or back on the form when nothing went in). The form key is checked
 * by Magento's CSRF validator.
 */
class Add implements HttpPostActionInterface
{
    private const MAX_CSV_BYTES = 1048576;

    public function __construct(
        private readonly Request $request,
        private readonly RedirectFactory $redirectFactory,
        private readonly ManagerInterface $messageManager,
        private readonly AddToCart $addToCart,
        private readonly Config $config
    ) {
    }

    public function execute()
    {
        $redirect = $this->redirectFactory->create();
        if (!$this->config->isEnabled()) {
            return $redirect->setPath('checkout/cart');
        }
        $list = $this->request->getParam('list', '');
        $lines = Parser::merge(
            Parser::rows((array) $this->request->getParam('items', [])),
            Parser::text(is_scalar($list) ? (string) $list : ''),
            Parser::csv($this->csv())
        );
        if (!$lines) {
            $this->messageManager->addErrorMessage(__('Enter at least one SKU.'));

            return $redirect->setPath('quickorder');
        }
        $result = $this->addToCart->add($lines);
        foreach ($result['failed'] as $sku => $reason) {
            $this->messageManager->addErrorMessage(__('%1: %2', $sku, $reason));
        }
        if (!$result['added']) {
            return $redirect->setPath('quickorder');
        }
        $this->messageManager->addSuccessMessage(
            __('Added to your cart: %1.', implode(', ', array_map(
                static fn (string $sku, float $qty) => $qty == 1 ? $sku : sprintf('%s × %s', $sku, $qty + 0),
                array_keys($result['added']),
                $result['added']
            )))
        );

        return $redirect->setPath('checkout/cart');
    }

    /**
     * The uploaded CSV file's contents, or '' without one.
     */
    private function csv(): string
    {
        $file = $this->request->getFiles('csv');
        $file = is_array($file) ? $file : [];
        // A nested field name (csv[a]) gives arrays, not one file: treated as no file.
        if (!is_string($file['tmp_name'] ?? null) || $file['tmp_name'] === '' || !empty($file['error']) || (int) ($file['size'] ?? 0) > self::MAX_CSV_BYTES) {
            return '';
        }

        return (string) file_get_contents($file['tmp_name']);
    }
}
