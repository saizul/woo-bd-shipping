# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- Free shipping progress notice ("Add ৳X more to get free delivery.") on the cart and checkout pages, enabled in the settings, plus the `[woo_bd_free_shipping]` shortcode. The message is editable and supports an `{amount}` placeholder.
- `FreeShipping` helper with unit tests.
- Delivery estimate on the shipping rate ("Delivery in 1-2 days" or arrival dates such as "14 Nov - 16 Nov"): day ranges per zone, per-district overrides, an order cut-off time and weekly off days (Friday, or Friday and Saturday). The estimate is also saved on the order's shipping line.
- `woo_bd_delivery_estimate` filter and a `DeliveryEstimate` helper with unit tests.
- Cash on delivery restrictions: hide the COD gateway below a minimum or above a maximum order total, above an item limit, for selected districts, or until a district is chosen. An optional notice above the payment methods explains why, and a COD order that breaks a rule is rejected during checkout validation.
- `woo_bd_cod_order_total` and `woo_bd_cod_blocked_reason` filters and a `CodRules` helper with unit tests.

### Fixed
- The COD fee is no longer added when the Cash on Delivery gateway is unavailable but still remembered as the chosen payment method in the session.

## [1.0.0] - 2026-11-07

### Added
- All 8 divisions and 64 districts of Bangladesh with English and Bangla names.
- District select replacing the WooCommerce state field for Bangladesh, plus an optional upazila / thana field.
- "BD Shipping" shipping method: Inside Dhaka, Dhaka sub-area and Outside Dhaka rates, per-district overrides, weight based extra charge per kg and a free shipping threshold.
- Cash on delivery fee (fixed or percentage) added to the cart when COD is the chosen payment method.
- Checkout tweaks, each toggleable: Bangladeshi mobile validation (`01[3-9]XXXXXXXX`, `+880`/`880` prefixes, Bangla digits), remove postcode and company, phone required, Bangla order note label.
- Settings page under WooCommerce built on the Settings API.
- Filters `woo_bd_districts`, `woo_bd_shipping_rate`, `woo_bd_cod_fee` and `woo_bd_cod_fee_base`.
- Translation template and Bangla (`bn_BD`) translation.
- HPOS compatibility declaration. Cart and Checkout blocks are declared as not supported.
- PHPUnit tests for the pure PHP logic and a GitHub Actions workflow for PHP 8.1 to 8.3.
