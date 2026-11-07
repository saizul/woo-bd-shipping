# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

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
