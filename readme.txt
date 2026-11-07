=== BD Shipping & Checkout for WooCommerce ===
Contributors: saizulamin
Tags: woocommerce, bangladesh, shipping, cash on delivery, checkout
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.1
Requires Plugins: woocommerce
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Bangladesh districts, zone based shipping rates, COD fee and checkout tweaks for WooCommerce.

== Description ==

BD Shipping & Checkout for WooCommerce prepares a store for Bangladeshi customers.

* All 8 divisions and 64 districts in English and Bangla, used as a district select instead of the WooCommerce state field. Optional upazila / thana field.
* A shipping method with Inside Dhaka, Dhaka sub-area and Outside Dhaka rates, per-district overrides, extra charge per kg and a free shipping threshold.
* Cash on delivery fee, fixed or percentage.
* Checkout tweaks you can switch on or off: mobile number validation (01[3-9]XXXXXXXX, +880 and 880 prefixes, Bangla digits), remove postcode and company, phone required, Bangla order note label.
* Translation ready with a Bangla translation.
* Compatible with High-Performance Order Storage (HPOS).

The plugin supports the classic checkout. The Cart and Checkout blocks are not supported.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/woo-bd-shipping` or install it from the Plugins screen.
2. Activate it.
3. In WooCommerce > Settings > Shipping, add the "BD Shipping" method to a zone that includes Bangladesh.
4. Set rates and checkout options under WooCommerce > BD Shipping.

== Frequently Asked Questions ==

= Does it work with the block-based checkout? =

No. Use the classic checkout shortcode.

= Which phone formats are accepted? =

01712345678, +8801712345678, 8801712345678, with spaces or dashes, and Bangla digits. Valid numbers are saved as 01XXXXXXXXX.

= How do per-district overrides work? =

Enter one per line, for example `Sylhet=150` or `BD-60=150`. An override replaces the zone rate for that district. Free shipping still wins when the threshold is reached.

= How is the COD fee calculated? =

A fixed amount, or a percentage of the cart subtotal plus the chosen shipping cost. It is added only when Cash on Delivery is selected and the customer ships to Bangladesh.

= Are there hooks for developers? =

Yes: `woo_bd_districts`, `woo_bd_shipping_rate`, `woo_bd_cod_fee` and `woo_bd_cod_fee_base`. See the README on GitHub for details.

= Does uninstalling remove data? =

Yes, the plugin options and shipping method instance settings are deleted.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
