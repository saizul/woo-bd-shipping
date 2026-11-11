# Contributing

Thanks for helping improve BD Shipping & Checkout for WooCommerce.

## Getting set up

```bash
git clone https://github.com/saizul/woo-bd-shipping.git
cd woo-bd-shipping
composer install
vendor/bin/phpunit
```

Requirements: PHP 8.1+, Composer. The tested classes live in `src/` and do not need WordPress. Classes that call WordPress or WooCommerce
functions (`Plugin`, `Settings`, `Admin/`, `Checkout/`, `Shipping/`) are kept thin so the logic stays in the pure classes.

## Guidelines

- Put calculation, parsing and lookup logic in pure classes (`Districts`, `PhoneNormalizer`, `RateConfig`, `RateCalculator`, `FreeShipping`, `DeliveryEstimate`) and cover it with tests.
- Run `php -l` on every PHP file you touch. CI lints all files and runs PHPUnit on PHP 8.1, 8.2 and 8.3.
- Escape output (`esc_html`, `esc_attr`, ...), sanitize input, check capabilities and keep the `defined( 'ABSPATH' ) || exit;` guard in every WordPress-facing file.
- Make user-facing strings translatable with the `woo-bd-shipping` text domain. After adding strings regenerate the template:
  `xgettext --language=PHP --from-code=UTF-8 --keyword=__ --keyword=_e --keyword=_x:1,2c --keyword=esc_html__ --keyword=esc_attr__ --add-comments=translators -o languages/woo-bd-shipping.pot woo-bd-shipping.php $(find src -name '*.php')`
- Add a line to `CHANGELOG.md` under "Unreleased".

## Pull requests

1. Fork the repository and create a branch from `main`.
2. Keep changes focused and describe the behaviour change and how you tested it.
3. Make sure CI is green.

By contributing you agree that your work is released under GPL-2.0-or-later.

## Reporting bugs

Open an issue with your WordPress, WooCommerce and PHP versions, what you expected and what happened. Never post customer data.
