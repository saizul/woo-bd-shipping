<?php
/**
 * Plugin bootstrap.
 *
 * @package WooBDShipping
 */

declare(strict_types=1);

namespace WooBDShipping;

use WooBDShipping\Admin\SettingsPage;
use WooBDShipping\Checkout\CheckoutTweaks;
use WooBDShipping\Checkout\CodFee;
use WooBDShipping\Shipping\ShippingMethod;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin into WordPress and WooCommerce.
 */
final class Plugin {

	private static ?Plugin $instance = null;

	private static ?Districts $districts = null;

	/**
	 * Singleton accessor.
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Districts lookup, filtered through `woo_bd_districts`.
	 */
	public static function districts(): Districts {
		if ( null === self::$districts ) {
			$base = new Districts();
			/**
			 * Filters the district data.
			 *
			 * @param array $districts Rows keyed by code: array( 'en' => '', 'bn' => '', 'division' => '' ).
			 */
			$rows = apply_filters( 'woo_bd_districts', $base->all() );
			self::$districts = is_array( $rows ) ? new Districts( $rows ) : $base;
		}
		return self::$districts;
	}

	/**
	 * Rate calculator built from the saved settings.
	 */
	public static function calculator(): RateCalculator {
		return new RateCalculator( RateConfig::fromSettings( Settings::all(), self::districts() ) );
	}

	/**
	 * Register hooks.
	 */
	public function boot(): void {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_filter( 'woocommerce_shipping_methods', array( $this, 'register_shipping_method' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WOO_BD_SHIPPING_FILE ), array( $this, 'action_links' ) );

		( new SettingsPage() )->register();
		( new CheckoutTweaks() )->register();
		( new CodFee() )->register();
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'woo-bd-shipping', false, dirname( plugin_basename( WOO_BD_SHIPPING_FILE ) ) . '/languages' );
	}

	/**
	 * Add the shipping method class.
	 *
	 * @param array<string,string> $methods Registered methods.
	 * @return array<string,string>
	 */
	public function register_shipping_method( array $methods ): array {
		$methods['woo_bd_shipping'] = ShippingMethod::class;
		return $methods;
	}

	/**
	 * Settings link on the plugins screen.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public function action_links( array $links ): array {
		$url = admin_url( 'admin.php?page=' . SettingsPage::SLUG );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'woo-bd-shipping' ) . '</a>' );
		return $links;
	}
}
