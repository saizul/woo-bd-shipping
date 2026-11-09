# BD Shipping & Checkout for WooCommerce

District based shipping, COD fee and checkout tweaks for Bangladeshi WooCommerce stores.

![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue) ![PHP](https://img.shields.io/badge/PHP-8.1%2B-777bb4) ![WooCommerce](https://img.shields.io/badge/WooCommerce-8%2B-96588a)

## Features

- **All 8 divisions and 64 districts** with English and Bangla names. The Bangladesh state field becomes a district select (English, Bangla or both), with an optional upazila / thana text field.
- **Shipping method** with Inside Dhaka, Dhaka sub-area and Outside Dhaka rates, per-district overrides, extra charge per kg above an included weight, and a free shipping threshold.
- **Free shipping notice**: optional "Add ৳X more to get free delivery" message on the cart and checkout pages, or anywhere with the `[woo_bd_free_shipping]` shortcode.
- **COD fee**, fixed or percentage, added when Cash on Delivery is selected (the totals refresh as the customer switches payment method).
- **Checkout tweaks**, each one toggleable: Bangladeshi mobile validation (`01[3-9]XXXXXXXX`, `+880` / `880` prefixes, Bangla digits, saved as `01XXXXXXXXX`), remove postcode and company, phone required, Bangla order note label.
- Settings page under **WooCommerce > BD Shipping** (Settings API, nonces, capability checks).
- Translation ready (`woo-bd-shipping` text domain, `.pot` and a Bangla `bn_BD` translation).
- HPOS compatible. Core logic is plain PHP in namespaced classes, covered by PHPUnit.

## Requirements

PHP 8.1+, WordPress 6.4+, WooCommerce 8+. The classic shortcode checkout is supported. The Cart and Checkout blocks are **not** supported and the plugin declares that to WooCommerce.

## Installation

1. Download the repository as a ZIP, or clone it into `wp-content/plugins/woo-bd-shipping`.
2. Activate **BD Shipping & Checkout for WooCommerce**. Composer is only needed for development; the plugin ships with a fallback autoloader.
3. Go to **WooCommerce > Settings > Shipping**, open a zone that covers Bangladesh and add the **BD Shipping** method.
4. Configure rates and checkout options under **WooCommerce > BD Shipping**.

## How rates are calculated

1. If the cart subtotal reaches the free shipping threshold (when greater than 0) the cost is 0.
2. Otherwise the base rate is the per-district override if one exists, else the zone rate (Dhaka district, a selected sub-area district, or everywhere else).
3. Every started kilogram above the included weight adds the per-kg charge.
4. The result passes through the `woo_bd_shipping_rate` filter.

The free shipping notice is off by default. Enable **Free shipping notice** in the settings (the threshold must be above 0) to show it on the cart and checkout pages. The text is editable; `{amount}` is replaced with the missing amount. To place it elsewhere, use the `[woo_bd_free_shipping]` shortcode. Nothing is shown for empty carts or for customers shipping outside Bangladesh.

Override syntax, one per line: `Sylhet=150`, `BD-60: 150` or `ঢাকা,70`. Lines starting with `#` are ignored.

## Hooks

### Filters

| Filter | Arguments | Purpose |
| --- | --- | --- |
| `woo_bd_districts` | `array $districts` | Add, remove or rename districts. Rows are keyed by code: `array( 'en' => '', 'bn' => '', 'division' => 'dhaka' )`. |
| `woo_bd_shipping_rate` | `float $cost, string $district_code, array $package, string $zone` | Adjust the calculated cost. `$zone` is `inside_dhaka`, `sub_area` or `outside`. |
| `woo_bd_cod_fee` | `float $fee, string $type, float $value` | Adjust the COD fee. |
| `woo_bd_cod_fee_base` | `float $base` | Change the amount a percentage COD fee is applied to (default: subtotal plus chosen shipping, ex tax). |

Examples:

```php
// Surcharge for hill districts.
add_filter( 'woo_bd_shipping_rate', function ( $cost, $code ) {
    return in_array( $code, array( 'BD-01', 'BD-29', 'BD-56' ), true ) ? $cost + 50 : $cost;
}, 10, 2 );

// Rename a district label.
add_filter( 'woo_bd_districts', function ( $districts ) {
    $districts['BD-10']['en'] = 'Chittagong';
    return $districts;
} );
```

## Development

```bash
composer install
vendor/bin/phpunit
find . -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l
```

Tested classes: `Districts`, `PhoneNormalizer`, `RateConfig`, `RateCalculator`, `FreeShipping` in `src/`. See [CONTRIBUTING.md](CONTRIBUTING.md) and [CHANGELOG.md](CHANGELOG.md).

## FAQ

**Does it work with the block-based Cart and Checkout?**
No. Use the classic checkout (`[woocommerce_checkout]` shortcode) for now.

**The district list is empty or shows the old states.**
Make sure "District select" is enabled and the store sells to Bangladesh. Clear any object cache.

**Which phone formats are accepted?**
`01712345678`, `+8801712345678`, `8801712345678`, with spaces or dashes, and Bangla digits such as `০১৭১২৩৪৫৬৭৮`. Operator digit must be 3 to 9.

**Can I change which districts count as Dhaka sub-area?**
Yes, in the settings page. By default Gazipur, Narayanganj, Narsingdi, Munshiganj and Manikganj.

**Does uninstalling remove data?**
Yes. Deleting the plugin removes its settings and the shipping method instance settings.

## License

GPL-2.0-or-later. Copyright Saizul Amin, [saizul.com](https://saizul.com).
