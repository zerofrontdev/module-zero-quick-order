# Zero_QuickOrder

A /quickorder page for shoppers who know the SKUs: rows of SKU and
quantity, a pasted list or a CSV file, one click into the cart.

## Install

Requires Magento Open Source 2.4.7–2.4.9 and PHP 8.2–8.4 with the GD extension built with WebP (`php -r 'var_dump(function_exists("imagewebp"));'` says `true`; Magento's own PHP requirements cover the rest). Without WebP in GD, Zero skips its WebP image copies and serves the originals.

```bash
composer require zerofront/module-zero-quick-order
bin/magento setup:upgrade
bin/magento setup:di:compile           # production mode
bin/magento setup:static-content:deploy # production mode
bin/magento cache:flush
```

Composer installs `zerofront/module-zero-core` with it.

Part of [`zerofront/zero`](https://zerofront.dev), which installs the Zero theme and every Zero module at once.

## Storefront

- **Rows**: SKU and quantity, five to start with (configurable), "5 more
  rows" adds five (HTMX fragment). As a SKU is typed the row shows what it
  is — name, price, a note when it is out of stock or needs options
  (`Controller\Product\Lookup`).
- **A pasted list**: one SKU per line, the quantity after a comma, a
  semicolon, a tab or a space; `24-MB01, 2`, `24-UG06 1`, `24-WB02`.
- **A CSV file**: SKU in the first column, quantity in the second, a header
  row skipped, comma, semicolon or tab delimited, up to 1 MB.
- **Add to Cart**: one plain POST (`Controller\Cart\Add`). Everything that
  could go in goes in; what couldn't is listed by SKU with the reason
  (unknown, disabled, not in this website, out of stock, needs options —
  order a configurable product by its variant's SKU). The same SKU twice
  adds up; at most 200 lines per submit.
- Links: My Account menu, and the footer links (switchable).

Nothing on the page needs JavaScript beyond the theme's htmx: the lookups
and the extra rows are enhancements, the form posts without them.

## Configuration

Stores > Configuration > Zero > Quick Order: Enabled, Rows Shown, Link in
the Footer.

## Tests

- `Test/Unit/Model/ParserTest.php`: the three inputs and their merge.
- `dev/tests/zero-e2e/tests/quick-order.spec.ts`: rows plus a pasted list
  into the cart, an unknown SKU reported, the SKU lookup.

What changed in each release: [CHANGELOG.md](CHANGELOG.md).

## License

OSL-3.0 or AFL-3.0, your choice: see `NOTICE.md`.

---

<a href="https://vilmate.com"><picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/zerofrontdev/zero/main/docs/images/vilmate-logo-dark.svg">
  <img src="https://raw.githubusercontent.com/zerofrontdev/zero/main/docs/images/vilmate-logo.svg" alt="Vilmate" height="34" align="right">
</picture></a>

Zero is made by [Fixstacks](https://fixstacks.com) in partnership with [Vilmate](https://vilmate.com), a software development company.
Documentation: https://zerofront.dev/docs. Demo store: https://demo.zerofront.dev.
