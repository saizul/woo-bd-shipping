<?php
/**
 * Plugin Name:       BD Shipping & Checkout for WooCommerce
 * Plugin URI:        https://github.com/saizul/woo-bd-shipping
 * Description:       Bangladesh districts, zone based shipping rates, COD fee and checkout tweaks (mobile validation, Bangla labels) for WooCommerce.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Requires Plugins:  woocommerce
 * Author:            Saizul Amin
 * Author URI:        https://saizul.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       woo-bd-shipping
 * Domain Path:       /languages
 * WC requires at least: 8.0
 *
 * @package WooBDShipping
 */

defined( 'ABSPATH' ) || exit;

define( 'WOO_BD_SHIPPING_VERSION', '1.0.0' );
define( 'WOO_BD_SHIPPING_FILE', __FILE__ );

if ( is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
} else {
	// Fallback PSR-4 autoloader so the plugin works without running Composer.
	spl_autoload_register(
		static function ( string $class ): void {
			$prefix = 'WooBDShipping\\';
			if ( 0 !== strncmp( $class, $prefix, strlen( $prefix ) ) ) {
				return;
			}
			$file = __DIR__ . '/src/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
			if ( is_readable( $file ) ) {
				require_once $file;
			}
		}
	);
}

// Declare HPOS compatibility. The classic checkout is supported, the Cart/Checkout blocks are not.
add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, false );
		}
	}
);

add_action(
	'plugins_loaded',
	static function (): void {
		if ( version_compare( PHP_VERSION, '8.1', '<' ) || ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					if ( current_user_can( 'activate_plugins' ) ) {
						echo '<div class="notice notice-error"><p>' . esc_html__( 'BD Shipping & Checkout requires PHP 8.1+ and an active WooCommerce installation.', 'woo-bd-shipping' ) . '</p></div>';
					}
				}
			);
			return;
		}
		\WooBDShipping\Plugin::instance()->boot();
	}
);
